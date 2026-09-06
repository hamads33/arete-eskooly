<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly string $content = '',
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
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
        echo $this->content;
    }
}
