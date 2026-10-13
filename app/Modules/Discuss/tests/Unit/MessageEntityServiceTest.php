<?php

namespace App\Modules\Discuss\tests\Unit;

use App\Modules\Discuss\Services\MessageEntityService;
use Tests\TestCase;

class MessageEntityServiceTest extends TestCase
{
    private MessageEntityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MessageEntityService();
    }

    public function test_keeps_valid_entities_sorted_by_position(): void
    {
        $result = $this->service->sanitize('Hello brave world', [
            ['type' => 'italic', 'offset' => 6, 'length' => 5],
            ['type' => 'bold', 'offset' => 0, 'length' => 5],
        ]);

        $this->assertSame([
            ['type' => 'bold', 'offset' => 0, 'length' => 5],
            ['type' => 'italic', 'offset' => 6, 'length' => 5],
        ], $result);
    }

    public function test_returns_empty_for_empty_body_or_no_entities(): void
    {
        $this->assertSame([], $this->service->sanitize(null, [['type' => 'bold', 'offset' => 0, 'length' => 1]]));
        $this->assertSame([], $this->service->sanitize('', [['type' => 'bold', 'offset' => 0, 'length' => 1]]));
        $this->assertSame([], $this->service->sanitize('Hello', []));
    }

    public function test_drops_unknown_types_and_malformed_items(): void
    {
        $result = $this->service->sanitize('Hello', [
            ['type' => 'script', 'offset' => 0, 'length' => 5],
            ['type' => 'bold', 'offset' => 0],
            ['type' => 'bold', 'length' => 2],
            'not-an-array',
            ['type' => 'bold', 'offset' => -1, 'length' => 2],
            ['type' => 'bold', 'offset' => 0, 'length' => 0],
            ['type' => 'bold', 'offset' => 'abc', 'length' => 2],
        ]);

        $this->assertSame([], $result);
    }

    public function test_drops_ranges_outside_the_body(): void
    {
        $result = $this->service->sanitize('short', [
            ['type' => 'bold', 'offset' => 3, 'length' => 3],
            ['type' => 'italic', 'offset' => 10, 'length' => 1],
            ['type' => 'strike', 'offset' => 0, 'length' => 5],
        ]);

        $this->assertSame([['type' => 'strike', 'offset' => 0, 'length' => 5]], $result);
    }

    public function test_offsets_are_measured_in_utf16_code_units(): void
    {
        // "😀" takes 2 UTF-16 code units, so "😀 hi" has length 5.
        $this->assertSame(5, $this->service->utf16Length('😀 hi'));

        $result = $this->service->sanitize('😀 hi', [
            ['type' => 'bold', 'offset' => 3, 'length' => 2],
            ['type' => 'italic', 'offset' => 3, 'length' => 3],
        ]);

        $this->assertSame([['type' => 'bold', 'offset' => 3, 'length' => 2]], $result);
    }

    public function test_accepts_numeric_strings_from_multipart_requests(): void
    {
        $result = $this->service->sanitize('Hello', [
            ['type' => 'bold', 'offset' => '0', 'length' => '5'],
        ]);

        $this->assertSame([['type' => 'bold', 'offset' => 0, 'length' => 5]], $result);
    }

    public function test_links_only_allow_http_https_and_mailto(): void
    {
        $body = 'click here';

        $allowed = [
            'https://example.com/path?x=1',
            'http://example.com',
            'mailto:someone@example.com',
        ];
        foreach ($allowed as $url) {
            $result = $this->service->sanitize($body, [['type' => 'link', 'offset' => 0, 'length' => 5, 'url' => $url]]);
            $this->assertSame([['type' => 'link', 'offset' => 0, 'length' => 5, 'url' => $url]], $result, $url);
        }

        $rejected = [
            'javascript:alert(1)',
            'JaVaScRiPt:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=',
            'ftp://example.com',
            'https://',
            'https://exa mple.com',
            "https://example.com/a\nb",
            '//example.com',
            'example.com',
            '',
        ];
        foreach ($rejected as $url) {
            $result = $this->service->sanitize($body, [['type' => 'link', 'offset' => 0, 'length' => 5, 'url' => $url]]);
            $this->assertSame([], $result, $url);
        }

        $this->assertSame([], $this->service->sanitize($body, [['type' => 'link', 'offset' => 0, 'length' => 5]]));
    }

    public function test_removes_exact_duplicates(): void
    {
        $result = $this->service->sanitize('Hello', [
            ['type' => 'bold', 'offset' => 0, 'length' => 5],
            ['type' => 'bold', 'offset' => 0, 'length' => 5],
        ]);

        $this->assertCount(1, $result);
    }

    public function test_caps_the_number_of_entities(): void
    {
        $body = str_repeat('a', 300);
        $entities = [];
        for ($i = 0; $i < 200; $i++) {
            $entities[] = ['type' => 'bold', 'offset' => $i, 'length' => 1];
        }

        $this->assertCount(MessageEntityService::MAX_ENTITIES, $this->service->sanitize($body, $entities));
    }

    public function test_spoilers_only_filters_everything_else(): void
    {
        $result = $this->service->spoilersOnly([
            ['type' => 'bold', 'offset' => 0, 'length' => 2],
            ['type' => 'spoiler', 'offset' => 3, 'length' => 4],
            'junk',
        ]);

        $this->assertSame([['type' => 'spoiler', 'offset' => 3, 'length' => 4]], $result);
        $this->assertSame([], $this->service->spoilersOnly(null));
        $this->assertSame([], $this->service->spoilersOnly('nope'));
    }
}
