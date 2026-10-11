<?php

namespace App\Modules\Discuss\Services;

/**
 * Format teks pesan ala Telegram: `body` tetap teks polos, sedangkan format
 * (bold, italic, spoiler, link, ...) disimpan sebagai daftar "entity" di
 * `metadata.entities` — `{type, offset, length, url?}`.
 *
 * `offset`/`length` dihitung dalam UTF-16 code unit (sama dengan indeks string
 * JavaScript), supaya frontend bisa langsung memakai `String.slice()`.
 *
 * Service ini adalah satu-satunya jalan masuk entity ke database: semua yang
 * tidak valid dibuang diam-diam (format hanya pemanis, pesan tetap terkirim).
 */
class MessageEntityService
{
    public const TYPES = ['bold', 'italic', 'underline', 'strike', 'code', 'spoiler', 'link'];

    public const MAX_ENTITIES = 100;

    private const URL_SCHEMES = ['http', 'https', 'mailto'];

    private const MAX_URL_LENGTH = 2048;

    /**
     * @param  array<int, mixed>  $entities  Input mentah dari request.
     * @return array<int, array{type: string, offset: int, length: int, url?: string}>
     */
    public function sanitize(?string $body, array $entities): array
    {
        if ($body === null || $body === '' || $entities === []) {
            return [];
        }

        $bodyLength = $this->utf16Length($body);
        $clean = [];

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $type = $entity['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                continue;
            }

            $offset = $this->toInt($entity['offset'] ?? null);
            $length = $this->toInt($entity['length'] ?? null);
            if ($offset === null || $length === null || $length < 1 || $offset + $length > $bodyLength) {
                continue;
            }

            $item = ['type' => $type, 'offset' => $offset, 'length' => $length];

            if ($type === 'link') {
                $url = $this->safeUrl($entity['url'] ?? null);
                if ($url === null) {
                    continue;
                }
                $item['url'] = $url;
            }

            // Kunci menghilangkan duplikat persis yang sama.
            $clean[$type . ':' . $offset . ':' . $length . ':' . ($item['url'] ?? '')] = $item;

            if (count($clean) >= self::MAX_ENTITIES) {
                break;
            }
        }

        $clean = array_values($clean);

        usort($clean, fn (array $a, array $b) => [$a['offset'], -$a['length'], $a['type']]
            <=> [$b['offset'], -$b['length'], $b['type']]);

        return $clean;
    }

    /** Panjang string dalam UTF-16 code unit (emoji di luar BMP = 2). */
    public function utf16Length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /** Hanya http(s) dan mailto — tidak pernah javascript:, data:, dst. */
    private function safeUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $url)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($scheme) || ! in_array(strtolower($scheme), self::URL_SCHEMES, true)) {
            return null;
        }

        if (strtolower($scheme) !== 'mailto' && ! parse_url($url, PHP_URL_HOST)) {
            return null;
        }

        return $url;
    }

    /** Request multipart mengirim angka sebagai string ("5"). */
    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value) && strlen($value) <= 9) {
            return (int) $value;
        }

        return null;
    }
}
