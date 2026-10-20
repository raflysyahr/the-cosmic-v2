<?php

namespace App\Modules\Discuss\tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Http\Resources\MessageResource;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\MessageMark;
use App\Modules\Discuss\Models\Reaction;
use App\Modules\Discuss\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageResourceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Room, 1: \Illuminate\Support\Collection} */
    private function seedRoom(int $messageCount): array
    {
        $room = Room::factory()->create(['type' => 'public']);
        $reactors = User::factory()->count(2)->create();
        $messages = collect();

        foreach (range(1, $messageCount) as $i) {
            $sender = User::factory()->create();
            $parent = Message::factory()->create(['room_id' => $room->id, 'user_id' => $sender->id, 'body' => "induk {$i}"]);
            $reply = Message::factory()->create([
                'room_id' => $room->id, 'user_id' => $sender->id, 'body' => "balasan {$i}", 'reply_to_id' => $parent->id,
            ]);

            foreach ($reactors as $reactor) {
                Reaction::create(['message_id' => $reply->id, 'user_id' => $reactor->id, 'emote_id' => (string) Str::ulid()]);
            }
            MessageMark::create(['room_id' => $room->id, 'message_id' => $reply->id, 'marked_by' => $reactors[0]->id, 'kind' => 'helpful']);

            $messages->push($reply->fresh());
        }

        return [$room, $messages];
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_does_not_grow_with_the_number_of_messages(): void
    {
        [, $few] = $this->seedRoom(3);
        [, $many] = $this->seedRoom(30);
        $request = Request::create('/');

        $forFew = $this->countQueries(fn () => MessageResource::collection($few)->resolve($request));
        $forMany = $this->countQueries(fn () => MessageResource::collection($many)->resolve($request));

        $this->assertSame($forFew, $forMany, 'Jumlah query tidak boleh bertambah per pesan (N+1).');
        $this->assertLessThanOrEqual(12, $forMany);
    }

    public function test_batched_output_matches_single_resource_output(): void
    {
        [, $messages] = $this->seedRoom(4);
        $request = Request::create('/');

        $batched = MessageResource::collection($messages)->resolve($request);

        foreach ($messages as $i => $message) {
            $single = (new MessageResource($message))->resolve($request);

            $this->assertEquals($single, $batched[$i]);
        }
    }

    public function test_batched_output_has_reactions_marks_reply_and_counts(): void
    {
        [, $messages] = $this->seedRoom(2);
        $reply = $messages->first();
        $request = Request::create('/');

        $data = MessageResource::collection($messages)->resolve($request)[0];

        $this->assertSame($reply->id, $data['id']);
        $this->assertCount(2, $data['reactions']);              // dua emote unik, masing-masing satu reaktor
        $this->assertSame(1, $data['reactions'][0]['count']);
        $this->assertSame(1, $data['marks']['helpful_count']);
        $this->assertFalse($data['marks']['is_best_answer']);
        $this->assertSame($reply->user_id, $data['user']['id']);
        $this->assertSame('induk 1', $data['reply_to']['body']);
        $this->assertSame(0, $data['reply_count']);
    }

    public function test_reply_count_counts_distinct_repliers(): void
    {
        $room = Room::factory()->create(['type' => 'public']);
        $parent = Message::factory()->create(['room_id' => $room->id, 'body' => 'induk']);
        $replier = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$replier, $replier, $other] as $u) {
            Message::factory()->create(['room_id' => $room->id, 'user_id' => $u->id, 'reply_to_id' => $parent->id]);
        }

        $data = MessageResource::collection(collect([$parent->fresh()]))->resolve(Request::create('/'))[0];

        $this->assertSame(2, $data['reply_count']);
    }

    public function test_empty_collection_runs_no_queries_and_does_not_fail(): void
    {
        $queries = $this->countQueries(fn () => MessageResource::collection(collect())->resolve(Request::create('/')));

        $this->assertSame(0, $queries);
    }
}
