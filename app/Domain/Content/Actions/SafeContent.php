<?php

namespace App\Domain\Content\Actions;

use Illuminate\Support\Facades\Storage;

final class SafeContent
{
    public static function link(?string $url): bool
    {
        if (! $url) {
            return true;
        }
        if (preg_match('/[\\\\\x00-\x20\x7f]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && ! empty($p['host']) && ! isset($p['user']) && ! isset($p['pass']);
    }

    public static function image(?string $path): bool
    {
        return ! $path || (bool) preg_match('~^content/[a-zA-Z0-9/_-]+\.(?:jpg|jpeg|png|webp|avif)$~D', $path);
    }

    public static function html(?string $html): string
    {
        return (string) str($html ?? '')->sanitizeHtml();
    }

    public static function imageUrl(?string $path): ?string
    {
        return $path && self::image($path) ? Storage::disk('public')->url($path) : null;
    }

    public static function video(?string $url): ?string
    {
        if (! self::link($url) || ! $url) {
            return null;
        }$p = parse_url($url);
        $host = strtolower($p['host'] ?? '');
        if (in_array($host, ['youtu.be', 'www.youtube.com', 'youtube.com'], true)) {
            $id = $host === 'youtu.be' ? trim($p['path'] ?? '', '/') : (function () use ($p) {
                parse_str($p['query'] ?? '', $q);

                return $q['v'] ?? '';
            })();

            return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/D', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true) && preg_match('~^/([0-9]{1,12})$~D', $p['path'] ?? '', $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }
}
