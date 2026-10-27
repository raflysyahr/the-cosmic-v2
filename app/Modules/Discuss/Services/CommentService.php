<?php

namespace App\Modules\Discuss\Services;

use App\Models\Chapter;
use App\Models\Series;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Komentar di halaman baca komik.
 *
 * Komentar BUKAN tabel baru: ia pesan biasa (`discuss_messages`) di grup diskusi komik
 * (`comic-{slug}`) yang ditandai chapter-nya (`comment_series_slug` + `comment_chapter_index`).
 * Karena itu:
 *  - room selalu diturunkan dari slug komik di sini, tidak pernah diterima dari klien, sehingga
 *    komentar dan grup diskusi PASTI sama (dicek lagi di MessageService::send());
 *  - Discuss menampilkan komentar sebagai pesan biasa (dengan label dari `MessageResource::comment`);
 *  - reply dari mana pun (Discuss atau kolom komentar) cukup `reply_to_id`, jadi muncul di kedua sisi;
 *  - reaksi memakai ReactionService/tabel reaksi Discuss yang sama.
 */
class CommentService
{
    public const MAX_BODY = 2000;

    public const PER_PAGE = 20;

    /** Kedalaman maksimum balasan yang ditampilkan di bawah satu komentar. */
    public const MAX_DEPTH = 8;

    /** Jumlah balasan yang dimuat sekali buka thread. */
    public const REPLIES_LIMIT = 200;

    public function __construct(
        private readonly MessageService $messages,
        private readonly MemberService $members,
        private readonly ReactionService $reactions,
        private readonly EmoteService $emotes,
    ) {}

    /**
     * Komik + chapter harus ada (404 bila tidak). Room bisa null: grup diskusi komik hanya dibuat
     * admin (lihat PageController::room()), jadi komik tanpa grup belum punya komentar.
     *
     * @return array{series: Series, chapter: Chapter, room: ?Room}
     */
    public function resolve(string $seriesSlug, int $chapterIndex): array
    {
        $series = Series::select('id', 'slug', 'title')->where('slug', $seriesSlug)->firstOrFail();

        $chapter = Chapter::select('id', 'series_id', 'index', 'title')
            ->where('series_id', $series->id)
            ->where('index', $chapterIndex)
            ->firstOrFail();

        $room = Room::where('context_type', 'comic')
            ->where('context_id', $series->slug)
            ->where('is_active', true)
            ->first();

        return ['series' => $series, 'chapter' => $chapter, 'room' => $room];
    }

    /**
     * Komentar tingkat atas chapter ini, terbaru dulu. Hanya pesan yang dikirim lewat kolom
     * komentar; chat Discuss biasa tidak ikut.
     *
     * @return array{room: ?Room, paginator: ?LengthAwarePaginator, thread_counts: array<string, int>, emotes: Collection}
     */
    public function list(string $viewerId, string $seriesSlug, int $chapterIndex, int $page): array
    {
        ['series' => $series, 'chapter' => $chapter, 'room' => $room] = $this->resolve($seriesSlug, $chapterIndex);

        if (! $room) {
            return ['room' => null, 'paginator' => null, 'thread_counts' => [], 'emotes' => collect()];
        }

        $this->assertCanRead($room, $viewerId);

        $paginator = Message::where('room_id', $room->id)
            ->where('comment_series_slug', $series->slug)
            ->where('comment_chapter_index', $chapter->index)
            ->whereNull('reply_to_id')
            ->notDeleted()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $rootIds = $paginator->getCollection()->pluck('id')->all();
        $threadCounts = array_map('count', $this->threadIds($rootIds, $room->id));

        return [
            'room' => $room,
            'paginator' => $paginator,
            'thread_counts' => $threadCounts,
            'emotes' => $this->emotes->availableFor($room->id),
        ];
    }

    /**
     * Semua balasan di bawah satu komentar, terlama dulu: balasan dari kolom komentar MAUPUN dari
     * chat Discuss (keduanya hanya `reply_to_id`).
     *
     * @return array{room: Room, messages: Collection, total: int}
     */
    public function replies(string $viewerId, string $seriesSlug, int $chapterIndex, string $commentId): array
    {
        ['series' => $series, 'chapter' => $chapter, 'room' => $room] = $this->resolve($seriesSlug, $chapterIndex);

        abort_unless($room, 404);
        $this->assertCanRead($room, $viewerId);

        $root = Message::where('id', $commentId)
            ->where('room_id', $room->id)
            ->where('comment_series_slug', $series->slug)
            ->where('comment_chapter_index', $chapter->index)
            ->whereNull('reply_to_id')
            ->notDeleted()
            ->firstOrFail();

        $ids = $this->threadIds([$root->id], $room->id)[$root->id] ?? [];

        $messages = $ids === []
            ? collect()
            : Message::whereIn('id', array_slice($ids, 0, self::REPLIES_LIMIT))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

        return ['room' => $room, 'messages' => $messages, 'total' => count($ids)];
    }

    /**
     * Kirim komentar (atau balasan di thread komentar). Pengirim otomatis menjadi member grup
     * (grup komik publik) agar komentarnya sah di room yang sama; yang di-ban/di-mute/kena slow
     * mode ditolak oleh aturan Discuss yang sama.
     */
    public function post(string $userId, string $seriesSlug, int $chapterIndex, string $body, ?string $replyToId): Message
    {
        ['series' => $series, 'chapter' => $chapter, 'room' => $room] = $this->resolve($seriesSlug, $chapterIndex);

        $room = $this->requireRoom($room);

        $body = trim($body);
        if ($body === '' || mb_strlen($body) > self::MAX_BODY) {
            throw ValidationException::withMessages([
                'body' => ['A comment must be 1–' . self::MAX_BODY . ' characters.'],
            ]);
        }

        if ($replyToId !== null && $replyToId !== '') {
            // Balasan baru harus masih terlihat di thread (kedalaman <= MAX_DEPTH).
            $this->assertInThread(
                $room, $series, $chapter, $replyToId,
                maxHops: self::MAX_DEPTH - 1,
                field: 'reply_to_id',
                error: 'You can only reply to comments on this chapter.',
            );
        } else {
            $replyToId = null;
        }

        $this->members->join($room->id, $userId);

        return $this->messages->send(new SendMessageData(
            roomId: $room->id,
            userId: $userId,
            replyToId: $replyToId,
            type: MessageType::Text->value,
            body: $body,
            attachments: [],
            metadata: [],
            commentSeriesSlug: $series->slug,
            commentChapterIndex: (int) $chapter->index,
        ));
    }

    /**
     * Reaksi pada komentar/balasan: SAMA dengan Discuss (tabel `discuss_reactions`, ReactionService,
     * emote room, CP), hanya pintu masuknya lewat halaman baca. Pesan harus bagian dari thread
     * komentar chapter ini.
     */
    public function react(string $userId, string $seriesSlug, int $chapterIndex, string $messageId, string $emoteId): void
    {
        ['series' => $series, 'chapter' => $chapter, 'room' => $room] = $this->resolve($seriesSlug, $chapterIndex);

        $room = $this->requireRoom($room);

        $this->assertInThread(
            $room, $series, $chapter, $messageId,
            maxHops: self::MAX_DEPTH,
            field: 'message',
            error: 'Message not found.',
        );

        if (! $this->emotes->availableFor($room->id)->contains('id', $emoteId)) {
            throw ValidationException::withMessages(['emote_id' => ['That reaction is not available.']]);
        }

        $this->members->join($room->id, $userId);

        $this->reactions->toggle($room->id, $messageId, $userId, $emoteId);
    }

    // ------------------------------------------------------------------ internal

    private function requireRoom(?Room $room): Room
    {
        if (! $room) {
            throw ValidationException::withMessages([
                'comment' => ['Comments open once this comic has a discussion group.'],
            ]);
        }

        return $room;
    }

    /** Room non-publik hanya untuk member, sama seperti membaca pesan di Discuss. */
    private function assertCanRead(Room $room, string $viewerId): void
    {
        if ($room->type->value !== 'public') {
            abort_unless($this->members->isMember($room->id, $viewerId), 403);
        }
    }

    /**
     * Pastikan pesan adalah komentar tingkat atas chapter ini atau turunannya (balasan dari mana
     * pun). Naik lewat `reply_to_id` sampai ke akar; akar harus komentar chapter ini dan tidak ada
     * pesan di jalur yang sudah dihapus (balasan di bawah pesan terhapus tidak tampil di thread).
     */
    private function assertInThread(Room $room, Series $series, Chapter $chapter, string $messageId, int $maxHops, string $field, string $error): void
    {
        $cursor = Message::where('id', $messageId)->where('room_id', $room->id)->first();

        for ($hops = 0; $cursor !== null && $hops <= $maxHops; $hops++) {
            if ($cursor->is_deleted) {
                break;
            }

            if ($cursor->reply_to_id === null) {
                if ($cursor->comment_series_slug === $series->slug
                    && (int) $cursor->comment_chapter_index === (int) $chapter->index) {
                    return;
                }
                break;
            }

            $cursor = Message::where('id', $cursor->reply_to_id)->where('room_id', $room->id)->first();
        }

        throw ValidationException::withMessages([$field => [$error]]);
    }

    /**
     * Turunan (balasan, balasan dari balasan, ...) tiap komentar, tanpa pesan terhapus dan
     * subtree di bawahnya. Satu query per tingkat, bukan per komentar.
     *
     * @param  list<string>  $rootIds
     * @return array<string, list<string>>  rootId => id turunan, terlama dulu
     */
    private function threadIds(array $rootIds, string $roomId): array
    {
        $result = array_fill_keys($rootIds, []);
        $rootOf = array_combine($rootIds, $rootIds) ?: [];
        $frontier = $rootIds;

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $rows = Message::where('room_id', $roomId)
                ->whereIn('reply_to_id', $frontier)
                ->notDeleted()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'reply_to_id']);

            $frontier = [];
            foreach ($rows as $row) {
                $root = $rootOf[$row->reply_to_id];
                $rootOf[$row->id] = $root;
                $result[$root][] = $row->id;
                $frontier[] = $row->id;
            }
        }

        return $result;
    }
}
