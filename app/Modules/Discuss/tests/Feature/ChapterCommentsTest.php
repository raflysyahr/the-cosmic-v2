<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Models\Chapter;
use App\Models\Series;
use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Komentar halaman baca = pesan di grup diskusi komik yang sama, ditandai chapter-nya.
 */
class ChapterCommentsTest extends TestCase
{
    use RefreshDatabase;

    private Series $series;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->series = Series::create(['slug' => 'solo-leveling', 'title' => 'Solo Leveling']);
        foreach ([1, 2] as $i) {
            Chapter::create(['series_id' => $this->series->id, 'index' => $i, 'title' => "Chapter {$i}"]);
        }

        $this->room = Room::factory()->create([
            'slug' => 'comic-solo-leveling',
            'type' => 'public',
            'context_type' => 'comic',
            'context_id' => 'solo-leveling',
        ]);
    }

    private function url(string $suffix = '', int $chapter = 1, string $slug = 'solo-leveling'): string
    {
        return "/api/comics/{$slug}/chapters/{$chapter}/comments" . $suffix;
    }

    private function member(?User $user = null, array $extra = []): User
    {
        $user ??= User::factory()->create();

        Member::create(array_merge([
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_banned' => false,
            'joined_at' => now(),
            'last_read_at' => now(),
        ], $extra));

        return $user;
    }

    private function comment(User $user, string $body = 'Nice chapter', ?string $replyTo = null, int $chapter = 1): array
    {
        return $this->actingAs($user)
            ->postJson($this->url('', $chapter), array_filter(['body' => $body, 'reply_to_id' => $replyTo]))
            ->assertCreated()
            ->json('message');
    }

    private function plainMessage(User $user, string $body = 'general chat', ?string $replyTo = null): Message
    {
        return Message::create([
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'reply_to_id' => $replyTo,
            'type' => 'text',
            'body' => $body,
        ]);
    }

    // ------------------------------------------------------------ posting

    public function test_guests_cannot_read_or_write_comments(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        $this->postJson($this->url(), ['body' => 'hi'])->assertUnauthorized();
    }

    public function test_posting_creates_a_message_in_the_comics_own_group_tagged_with_the_chapter(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson($this->url(), ['body' => 'Great art!'])->assertCreated();

        $res->assertJsonPath('message.body', 'Great art!')
            ->assertJsonPath('message.comment.series_slug', 'solo-leveling')
            ->assertJsonPath('message.comment.chapter_index', 1)
            ->assertJsonPath('message.thread_reply_count', 0)
            ->assertJsonPath('room.slug', 'comic-solo-leveling');

        $this->assertDatabaseHas('discuss_messages', [
            'id' => $res->json('message.id'),
            'room_id' => $this->room->id,
            'user_id' => $user->id,
            'type' => 'text',
            'comment_series_slug' => 'solo-leveling',
            'comment_chapter_index' => 1,
        ]);

        // Pengirim otomatis jadi member grup.
        $this->assertDatabaseHas('discuss_members', ['room_id' => $this->room->id, 'user_id' => $user->id]);
    }

    public function test_comment_body_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson($this->url(), ['body' => ''])->assertUnprocessable();
        $this->actingAs($user)->postJson($this->url(), [])->assertUnprocessable();
        $this->actingAs($user)->postJson($this->url(), ['body' => str_repeat('a', 2001)])->assertUnprocessable();
        $this->actingAs($user)->postJson($this->url(), ['body' => str_repeat('a', 2000)])->assertCreated();
    }

    public function test_client_cannot_choose_the_room_or_the_comment_tag(): void
    {
        $user = User::factory()->create();
        $otherRoom = Room::factory()->create(['type' => 'public']);

        $res = $this->actingAs($user)->postJson($this->url(), [
            'body' => 'sneaky',
            'room_id' => $otherRoom->id,
            'comment_series_slug' => 'another-comic',
            'comment_chapter_index' => 99,
        ])->assertCreated();

        $this->assertDatabaseHas('discuss_messages', [
            'id' => $res->json('message.id'),
            'room_id' => $this->room->id,
            'comment_series_slug' => 'solo-leveling',
            'comment_chapter_index' => 1,
        ]);
    }

    public function test_discuss_chat_cannot_create_comments(): void
    {
        $user = $this->member();

        $res = $this->actingAs($user)->postJson("/api/rooms/{$this->room->slug}/messages", [
            'body' => 'pretending to be a comment',
            'comment_series_slug' => 'solo-leveling',
            'comment_chapter_index' => 1,
            'metadata' => ['comment_series_slug' => 'solo-leveling', 'comment_chapter_index' => 1],
        ])->assertCreated();

        $this->assertDatabaseHas('discuss_messages', [
            'id' => $res->json('message.id'),
            'comment_series_slug' => null,
            'comment_chapter_index' => null,
        ]);
    }

    public function test_the_message_service_refuses_a_comment_outside_its_own_comics_group(): void
    {
        $user = $this->member();
        $service = app(MessageService::class);

        $send = fn (Room $room, string $slug, string $type = 'text') => $service->send(new SendMessageData(
            roomId: $room->id, userId: $user->id, replyToId: null, type: $type, body: 'x',
            attachments: [], metadata: [], commentSeriesSlug: $slug, commentChapterIndex: 1,
        ));

        // Room bukan grup komik.
        $plain = Room::factory()->create(['type' => 'public']);
        $this->member($user, ['room_id' => $plain->id]);

        foreach ([
            fn () => $send($plain, 'solo-leveling'),
            // Grup komik lain.
            fn () => $send($this->room, 'another-comic'),
            // Komentar harus teks.
            fn () => $send($this->room, 'solo-leveling', 'image'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the comment to be rejected.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('comment', $e->errors());
            }
        }

        $this->assertSame(0, Message::whereNotNull('comment_chapter_index')->count());
    }

    public function test_unknown_comic_chapter_or_index_is_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson($this->url('', 1, 'no-such-comic'))->assertNotFound();
        $this->actingAs($user)->getJson($this->url('', 99))->assertNotFound();
        $this->actingAs($user)->postJson($this->url('', 99), ['body' => 'x'])->assertNotFound();
        $this->actingAs($user)->getJson('/api/comics/solo-leveling/chapters/abc/comments')->assertNotFound();
    }

    public function test_a_comic_without_a_discussion_group_has_no_comments_yet(): void
    {
        $other = Series::create(['slug' => 'no-group-yet', 'title' => 'No Group']);
        Chapter::create(['series_id' => $other->id, 'index' => 1, 'title' => 'Chapter 1']);
        $user = User::factory()->create();

        $this->actingAs($user)->getJson($this->url('', 1, 'no-group-yet'))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('data', []);

        $this->actingAs($user)->postJson($this->url('', 1, 'no-group-yet'), ['body' => 'hello'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_banned_and_muted_members_cannot_comment(): void
    {
        $banned = $this->member(null, ['is_banned' => true]);
        $muted = $this->member(null, ['muted_until' => now()->addHour()]);

        $this->actingAs($banned)->postJson($this->url(), ['body' => 'hi'])->assertUnprocessable();
        $this->actingAs($muted)->postJson($this->url(), ['body' => 'hi'])->assertUnprocessable();

        $this->assertSame(0, Message::whereNotNull('comment_chapter_index')->count());
    }

    public function test_private_comic_groups_are_members_only(): void
    {
        $this->room->update(['type' => 'private']);
        $outsider = User::factory()->create();
        $insider = $this->member();

        $this->actingAs($outsider)->getJson($this->url())->assertForbidden();
        $this->actingAs($outsider)->postJson($this->url(), ['body' => 'let me in'])->assertUnprocessable();
        $this->actingAs($insider)->getJson($this->url())->assertOk();
    }

    // ------------------------------------------------------------ listing

    public function test_the_comment_list_only_contains_messages_sent_through_the_comment_box_for_that_chapter(): void
    {
        $a = User::factory()->create();
        $mine = $this->comment($a, 'chapter one comment', null, 1);
        $this->comment($a, 'chapter two comment', null, 2);
        $this->plainMessage($a, 'just chatting in discuss');

        $res = $this->actingAs($a)->getJson($this->url())->assertOk();

        $res->assertJsonPath('available', true)
            ->assertJsonPath('room.slug', 'comic-solo-leveling')
            ->assertJsonPath('meta.total', 1);
        $this->assertSame([$mine['id']], collect($res->json('data'))->pluck('id')->all());
    }

    public function test_the_comment_list_is_newest_first_and_paginated(): void
    {
        $user = User::factory()->create();
        // Dibuat langsung (bukan lewat HTTP): limiter `discuss-send` hanya mengizinkan 20 pesan/menit.
        foreach (range(1, 25) as $i) {
            Message::create([
                'room_id' => $this->room->id,
                'user_id' => $user->id,
                'type' => 'text',
                'body' => "comment {$i}",
                'comment_series_slug' => 'solo-leveling',
                'comment_chapter_index' => 1,
            ]);
        }

        $page1 = $this->actingAs($user)->getJson($this->url())->assertOk();
        $this->assertCount(20, $page1->json('data'));
        $page1->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.total', 25);
        $this->assertSame('comment 25', $page1->json('data.0.body'));

        $page2 = $this->actingAs($user)->getJson($this->url() . '?page=2')->assertOk();
        $this->assertCount(5, $page2->json('data'));
        $this->assertSame('comment 1', $page2->json('data.4.body'));
    }

    public function test_deleted_comments_disappear_from_the_list(): void
    {
        $user = $this->member();
        $comment = $this->comment($user);

        $this->actingAs($user)->deleteJson("/api/rooms/{$this->room->slug}/messages/{$comment['id']}")->assertOk();

        $this->actingAs($user)->getJson($this->url())->assertOk()->assertJsonPath('meta.total', 0);
    }

    // ------------------------------------------------- label di Discuss

    public function test_discuss_shows_comments_with_a_label_and_plain_chat_without_one(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment($user, 'from the reader page');
        $plain = $this->plainMessage($user, 'from the chat');

        $data = collect($this->actingAs($user)->getJson("/api/rooms/{$this->room->slug}/messages")->assertOk()->json('data'));

        $this->assertSame(
            ['series_slug' => 'solo-leveling', 'chapter_index' => 1],
            $data->firstWhere('id', $comment['id'])['comment'],
        );
        $this->assertNull($data->firstWhere('id', $plain->id)['comment']);
    }

    // ------------------------------------------------------------ replies

    public function test_a_reply_from_discuss_shows_up_under_the_comment(): void
    {
        $author = User::factory()->create();
        $comment = $this->comment($author, 'what a twist');

        $replier = $this->member();
        $reply = $this->actingAs($replier)->postJson("/api/rooms/{$this->room->slug}/messages", [
            'body' => 'replying from the discuss chat',
            'reply_to_id' => $comment['id'],
        ])->assertCreated()->json('message');

        $replies = $this->actingAs($author)->getJson($this->url("/{$comment['id']}/replies"))->assertOk();

        $this->assertSame([$reply['id']], collect($replies->json('data'))->pluck('id')->all());
        $replies->assertJsonPath('total', 1)->assertJsonPath('data.0.reply_to_id', $comment['id']);

        // Balasan dari Discuss sendiri BUKAN komentar tingkat atas.
        $list = $this->actingAs($author)->getJson($this->url())->assertOk();
        $this->assertSame([$comment['id']], collect($list->json('data'))->pluck('id')->all());
        $list->assertJsonPath('data.0.thread_reply_count', 1);
    }

    public function test_a_reply_from_the_comment_box_shows_up_as_a_reply_in_discuss(): void
    {
        $author = User::factory()->create();
        $comment = $this->comment($author, 'first!');

        $replier = User::factory()->create();
        $reply = $this->comment($replier, 'second!', $comment['id']);

        $this->assertSame($comment['id'], $reply['reply_to_id']);
        $this->assertSame($comment['id'], $reply['reply_to']['id']);

        $data = collect($this->actingAs($author)->getJson("/api/rooms/{$this->room->slug}/messages")->json('data'));
        $inDiscuss = $data->firstWhere('id', $reply['id']);

        $this->assertSame($comment['id'], $inDiscuss['reply_to']['id']);
        $this->assertSame(1, $inDiscuss['comment']['chapter_index']);

        // Balasan tidak masuk daftar komentar tingkat atas.
        $list = $this->actingAs($author)->getJson($this->url())->assertOk();
        $this->assertSame([$comment['id']], collect($list->json('data'))->pluck('id')->all());
    }

    public function test_the_thread_contains_replies_of_replies_from_both_sides_in_order(): void
    {
        $a = User::factory()->create();
        $comment = $this->comment($a, 'root');

        $b = $this->member();
        $replyB = $this->actingAs($b)->postJson("/api/rooms/{$this->room->slug}/messages", [
            'body' => 'b replies in discuss', 'reply_to_id' => $comment['id'],
        ])->assertCreated()->json('message');

        $c = User::factory()->create();
        $replyC = $this->comment($c, 'c replies to b from the comment box', $replyB['id']);

        $thread = $this->actingAs($a)->getJson($this->url("/{$comment['id']}/replies"))->assertOk();

        $this->assertSame([$replyB['id'], $replyC['id']], collect($thread->json('data'))->pluck('id')->all());
        $thread->assertJsonPath('total', 2);
        $this->actingAs($a)->getJson($this->url())->assertJsonPath('data.0.thread_reply_count', 2);
    }

    public function test_deleted_replies_are_not_listed_or_counted(): void
    {
        $a = $this->member();
        $comment = $this->comment($a, 'root');
        $b = User::factory()->create();
        $reply = $this->comment($b, 'will be deleted', $comment['id']);

        $this->actingAs($b)->deleteJson("/api/rooms/{$this->room->slug}/messages/{$reply['id']}")->assertOk();

        $this->actingAs($a)->getJson($this->url("/{$comment['id']}/replies"))->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($a)->getJson($this->url())->assertJsonPath('data.0.thread_reply_count', 0);
    }

    public function test_replies_can_only_target_this_chapters_thread(): void
    {
        $user = User::factory()->create();
        $other = $this->member();

        $plain = $this->plainMessage($other, 'plain discuss message');
        $chapterTwo = $this->comment($other, 'comment on chapter 2', null, 2);
        $foreignRoom = Room::factory()->create(['type' => 'public']);
        $foreign = Message::create(['room_id' => $foreignRoom->id, 'user_id' => $other->id, 'type' => 'text', 'body' => 'elsewhere']);

        foreach ([$plain->id, $chapterTwo['id'], $foreign->id, 'does-not-exist'] as $target) {
            $this->actingAs($user)->postJson($this->url(), ['body' => 'reply', 'reply_to_id' => $target])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['reply_to_id']);
        }

        // Balasan ke komentar yang sudah dihapus juga ditolak.
        $gone = $this->comment($other, 'deleted soon');
        $this->actingAs($other)->deleteJson("/api/rooms/{$this->room->slug}/messages/{$gone['id']}")->assertOk();
        $this->actingAs($user)->postJson($this->url(), ['body' => 'reply', 'reply_to_id' => $gone['id']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reply_to_id']);
    }

    public function test_thread_of_another_chapter_or_a_reply_cannot_be_opened_as_a_comment(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment($user, 'root', null, 1);
        $reply = $this->comment(User::factory()->create(), 'reply', $comment['id']);

        $this->actingAs($user)->getJson($this->url("/{$comment['id']}/replies", 2))->assertNotFound();
        $this->actingAs($user)->getJson($this->url("/{$reply['id']}/replies"))->assertNotFound();
    }

    // ----------------------------------------------------------- reaksi

    private function emote(): Emote
    {
        return Emote::create(['code' => ':like:', 'name' => 'Like', 'unicode' => '👍']);
    }

    public function test_the_comment_list_includes_the_emotes_that_can_be_used(): void
    {
        $emote = $this->emote();
        $user = User::factory()->create();

        $emotes = $this->actingAs($user)->getJson($this->url())->assertOk()->json('emotes');

        $this->assertContains($emote->id, collect($emotes)->pluck('id')->all());
    }

    public function test_reacting_in_the_comment_section_uses_the_discuss_reaction_table(): void
    {
        $emote = $this->emote();
        $author = User::factory()->create();
        $comment = $this->comment($author);
        $reactor = User::factory()->create();

        $this->actingAs($reactor)
            ->postJson($this->url("/{$comment['id']}/reactions"), ['emote_id' => $emote->id])
            ->assertOk();

        $this->assertDatabaseHas('discuss_reactions', [
            'message_id' => $comment['id'], 'user_id' => $reactor->id, 'emote_id' => $emote->id,
        ]);
        // Reaktor otomatis jadi member (reaksi Discuss mensyaratkan member).
        $this->assertDatabaseHas('discuss_members', ['room_id' => $this->room->id, 'user_id' => $reactor->id]);

        // Bentuknya sama dengan di Discuss (grouped reactions), baik di kolom komentar maupun chat.
        $inList = $this->actingAs($author)->getJson($this->url())->json('data.0.reactions.0');
        $this->assertSame($emote->id, $inList['emoteId']);
        $this->assertSame(1, $inList['count']);
        $this->assertSame([$reactor->id], $inList['userIds']);

        $inDiscuss = collect($this->actingAs($author)->getJson("/api/rooms/{$this->room->slug}/messages")->json('data'))
            ->firstWhere('id', $comment['id']);
        $this->assertSame($inList, $inDiscuss['reactions'][0]);

        // Menekan lagi = mencabut (aturan Discuss).
        $this->actingAs($reactor)
            ->postJson($this->url("/{$comment['id']}/reactions"), ['emote_id' => $emote->id])
            ->assertOk();
        $this->assertDatabaseMissing('discuss_reactions', ['message_id' => $comment['id'], 'user_id' => $reactor->id]);
    }

    public function test_reaction_rules_of_discuss_still_apply(): void
    {
        $emote = $this->emote();
        $author = User::factory()->create();
        $comment = $this->comment($author);

        // Tidak bisa bereaksi ke komentar sendiri.
        $this->actingAs($author)
            ->postJson($this->url("/{$comment['id']}/reactions"), ['emote_id' => $emote->id])
            ->assertUnprocessable();

        // Member yang di-ban tidak bisa bereaksi.
        $banned = $this->member(null, ['is_banned' => true]);
        $this->actingAs($banned)
            ->postJson($this->url("/{$comment['id']}/reactions"), ['emote_id' => $emote->id])
            ->assertUnprocessable();

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_reactions_through_the_comment_section_are_limited_to_this_chapters_thread_and_real_emotes(): void
    {
        $emote = $this->emote();
        $user = User::factory()->create();
        $other = $this->member();

        $plain = $this->plainMessage($other, 'plain chat message');
        $chapterTwo = $this->comment($other, 'chapter two', null, 2);
        $comment = $this->comment($other, 'chapter one');

        foreach ([$plain->id, $chapterTwo['id']] as $target) {
            $this->actingAs($user)
                ->postJson($this->url("/{$target}/reactions"), ['emote_id' => $emote->id])
                ->assertUnprocessable();
        }

        $this->actingAs($user)
            ->postJson($this->url("/{$comment['id']}/reactions"), ['emote_id' => 'not-a-real-emote'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['emote_id']);

        $this->assertDatabaseCount('discuss_reactions', 0);
    }

    public function test_replies_inside_a_thread_can_be_reacted_to(): void
    {
        $emote = $this->emote();
        $a = User::factory()->create();
        $comment = $this->comment($a, 'root');
        $replyAuthor = $this->member();
        $reply = $this->actingAs($replyAuthor)->postJson("/api/rooms/{$this->room->slug}/messages", [
            'body' => 'reply from discuss', 'reply_to_id' => $comment['id'],
        ])->assertCreated()->json('message');

        $this->actingAs($a)
            ->postJson($this->url("/{$reply['id']}/reactions"), ['emote_id' => $emote->id])
            ->assertOk();

        $this->assertDatabaseHas('discuss_reactions', ['message_id' => $reply['id'], 'user_id' => $a->id]);
    }
}
