<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        private readonly array $queryData,
        private readonly array $bodyData,
        private readonly array $fileData,
        private readonly array $serverData,
        private array $routeParameters = [],
    ) {
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        $method = strtoupper((string) ($this->serverData['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = strtoupper((string) ($this->bodyData['_method'] ?? $this->header('X-HTTP-Method-Override', '')));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $path = parse_url((string) ($this->serverData['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        return '/' . trim(rawurldecode($path), '/');
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->queryData : ($this->queryData[$key] ?? $default);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyData[$key] ?? $this->queryData[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryData, $this->bodyData);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->bodyData) || array_key_exists($key, $this->queryData);
    }

    public function file(string $key): ?array
    {
        return $this->fileData[$key] ?? null;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->serverData[$key] ?? $default;
    }

    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }
}
