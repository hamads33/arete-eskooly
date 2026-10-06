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

    /**
     * The bearer token from the Authorization header. Some Apache/CGI setups drop that header,
     * so the rewritten REDIRECT_HTTP_AUTHORIZATION copy and X-Device-Token are accepted too.
     */
    public function bearerToken(): ?string
    {
        $header = (string) ($this->serverData['HTTP_AUTHORIZATION'] ?? $this->serverData['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            return $m[1];
        }
        $fallback = trim((string) $this->header('X-Device-Token', ''));
        return $fallback !== '' ? $fallback : null;
    }

    /** A JSON request body as an array; form-encoded bodies (and tests) fall back to the parsed body. */
    public function json(): array
    {
        if ($this->bodyData !== []) {
            return $this->bodyData;
        }
        $raw = (string) ($this->serverData['__raw_body'] ?? file_get_contents('php://input'));
        if ($raw === '' || strlen($raw) > 1048576) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        return (string) ($this->serverData['REMOTE_ADDR'] ?? '0.0.0.0');
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
