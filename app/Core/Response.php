<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly string $content = '',
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
        private readonly ?\Closure $stream = null,
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self((string) json_encode($data, JSON_THROW_ON_ERROR), $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /**
     * Streams a file from disk instead of buffering it, honouring a single HTTP Range so
     * browsers can seek inside large videos.
     */
    public static function file(string $path, string $mime, ?string $range = null, array $headers = []): self
    {
        $size = (int) filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        if ($range !== null && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $end : min($end, (int) $m[2]);
            }
            if ($start > $end || $start >= $size) {
                return new self('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }
        $length = $end - $start + 1;
        $headers = ['Content-Type' => $mime, 'Content-Length' => (string) $length, 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, max-age=3600'] + $headers;

        return new self('', $status, $headers, static function () use ($path, $start, $length): void {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            fseek($handle, $start);
            while ($length > 0 && !feof($handle) && !connection_aborted()) {
                $chunk = fread($handle, (int) min(1048576, $length));
                if ($chunk === false) {
                    break;
                }
                $length -= strlen($chunk);
                echo $chunk;
                flush();
            }
            fclose($handle);
        });
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        if (!isset($this->headers['Cache-Control'])) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
        }
        if ($this->stream !== null) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ($this->stream)();
            return;
        }
        echo $this->content;
    }
}
