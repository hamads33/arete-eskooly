<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Logger;
use Throwable;

final class App
{
    private static ?self $instance = null;

    public function __construct(
        private readonly Router $router,
        private readonly Session $session,
        private readonly Database $database,
        private readonly Logger $logger,
        private readonly array $config,
    ) {
        self::$instance = $this;
        date_default_timezone_set((string) $this->config('app.timezone', 'UTC'));
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \RuntimeException('Application has not been bootstrapped.');
    }

    public function run(Request $request): Response
    {
        try {
            return $this->router->dispatch($request, $this);
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled application error', ['exception' => $exception::class, 'message' => $exception->getMessage()]);
            $debug = (bool) $this->config('app.debug', false);
            return Response::html(View::render('errors/500', [
                'pageTitle' => 'Server Error',
                'debugMessage' => $debug ? $exception->getMessage() : null,
            ], 'layouts/error'), 500);
        }
    }

    public function router(): Router { return $this->router; }
    public function session(): Session { return $this->session; }
    public function database(): Database { return $this->database; }

    public function config(string $key, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
