<?php
declare(strict_types=1);
namespace App\Controllers\Concerns;
use App\Repositories\{GoogleConnectionRepository, LiveClassRepository, SettingsRepository};
use App\Services\Google\{GoogleCalendarClient, GoogleCalendarService, GoogleClientFactory, GoogleOAuthService, TokenCipher};
use App\Services\{LiveClassAuthorizationService, LiveClassService};

/**
 * Wiring shared by the admin, teacher and student live-class controllers.
 *
 * The project has no DI container, so this trait is where the object graph is assembled once
 * instead of three times. The CalendarClient is resolved through a static override hook so the
 * test suite can substitute FakeCalendarClient and stay deterministic (§45) — production code
 * never sets it, and it is reset between tests.
 */
trait ResolvesLiveClasses
{
    /** Test seam. Null in production, where the real Google client is always built. */
    private static ?object $calendarClientOverride = null;

    public static function useCalendarClient(?object $client): void
    {
        self::$calendarClientOverride = $client;
    }

    protected function liveClassRepository(): LiveClassRepository
    {
        return new LiveClassRepository($this->app->database());
    }

    protected function googleConnections(): GoogleConnectionRepository
    {
        return new GoogleConnectionRepository($this->app->database());
    }

    protected function googleFactory(): GoogleClientFactory
    {
        return new GoogleClientFactory($this->googleConnections(), new TokenCipher());
    }

    protected function googleOAuth(): GoogleOAuthService
    {
        return new GoogleOAuthService($this->googleFactory(), $this->googleConnections(), new TokenCipher(), $this->app->session());
    }

    protected function calendar(): GoogleCalendarService
    {
        $client = self::$calendarClientOverride ?? new GoogleCalendarClient($this->googleFactory());
        return new GoogleCalendarService($client, $this->googleConnections());
    }

    protected function liveClassService(): LiveClassService
    {
        return new LiveClassService($this->app->database(), $this->liveClassRepository(), $this->calendar(), $this->liveClassAuthorization(), $this->app->logger());
    }

    protected function liveClassAuthorization(): LiveClassAuthorizationService
    {
        return new LiveClassAuthorizationService($this->app->database());
    }

    protected function joinWindowMinutes(): int
    {
        return max(0, (int) $this->app->config('app.live_classes.join_before_start_minutes', 10));
    }

    protected function instituteId(): int
    {
        return (int) ((new SettingsRepository($this->app->database()))->institute()['id'] ?? 0);
    }
}
