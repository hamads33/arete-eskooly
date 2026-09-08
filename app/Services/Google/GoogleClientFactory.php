<?php
declare(strict_types=1);
namespace App\Services\Google;
use App\Repositories\GoogleConnectionRepository;
use Google\Client;

/**
 * The single place Google\Client is configured (§41). Controllers never construct one.
 *
 * Credentials come from the environment only — GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET are never
 * admin-editable database fields (§6), so a compromised admin session cannot rewrite them and no
 * secret reaches a form, a view, or the repository.
 */
final class GoogleClientFactory
{
    /**
     * Minimum scopes for the job (§7): manage the events this app creates, and read the connected
     * account's email so the admin can confirm which account is linked. No Drive, no Gmail.
     */
    public const SCOPES = [
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/userinfo.email',
        // Lets the app create a Meet space it can configure as OPEN, so that holding the link is
        // enough to walk in. Access is decided by this application's roles and audience rules; a
        // second gate at Google's door only strands students the app has already authorised.
        'https://www.googleapis.com/auth/meetings.space.created',
    ];

    public function __construct(private readonly GoogleConnectionRepository $connections, private readonly TokenCipher $cipher) {}

    public function configured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function clientId(): string { return trim((string) (getenv('GOOGLE_CLIENT_ID') ?: '')); }
    private function clientSecret(): string { return trim((string) (getenv('GOOGLE_CLIENT_SECRET') ?: '')); }
    public function redirectUri(): string { return trim((string) (getenv('GOOGLE_REDIRECT_URI') ?: '')); }
    public function defaultCalendarId(): string { return trim((string) (getenv('GOOGLE_CALENDAR_ID') ?: '')) ?: 'primary'; }

    /** A bare client: credentials and scopes, no token. Used for the consent redirect and callback. */
    public function base(): Client
    {
        if (!$this->configured()) {
            throw new GoogleCalendarException('Google integration is not configured on this server.');
        }
        $client = new Client();
        $client->setClientId($this->clientId());
        $client->setClientSecret($this->clientSecret());
        $client->setRedirectUri($this->redirectUri());
        $client->setScopes(self::SCOPES);
        // Offline access plus forced consent is what makes Google return a refresh token (§10);
        // without prompt=consent a re-authorising account silently returns none.
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        return $client;
    }

    /**
     * An authorised client for this institute, refreshing the access token when it has expired and
     * persisting the replacement so the next request starts fresh.
     *
     * @throws GoogleReconnectRequired when there is no usable authorisation left.
     */
    public function forInstitute(int $institute): Client
    {
        $connection = $this->connections->find($institute);
        if ($connection === null || $connection['status'] === 'disconnected') {
            throw new GoogleReconnectRequired('Google Calendar is not connected.');
        }
        if ($connection['status'] === 'reconnect_required') {
            throw new GoogleReconnectRequired('Google authorisation expired or was revoked.');
        }

        $client = $this->base();
        $accessToken = $this->cipher->decrypt($connection['access_token_encrypted']);
        $refreshToken = $this->cipher->decrypt($connection['refresh_token_encrypted']);
        if ($refreshToken === null && $accessToken === null) {
            throw new GoogleReconnectRequired('Google authorisation is missing.');
        }
        $expiresAt = $connection['token_expires_at'] !== null ? strtotime((string) $connection['token_expires_at']) : 0;
        if ($accessToken !== null) {
            // created + expires_in is how the SDK decides expiry; deriving created from our stored
            // absolute expiry keeps isAccessTokenExpired() honest across requests.
            $client->setAccessToken(['access_token' => $accessToken, 'expires_in' => max(0, $expiresAt - time()), 'created' => time()]);
        }
        if ($accessToken === null || $client->isAccessTokenExpired()) {
            if ($refreshToken === null) {
                $this->connections->markReconnectRequired($institute, 'No refresh token stored.');
                throw new GoogleReconnectRequired('Google authorisation expired and cannot be renewed.');
            }
            $this->refresh($client, $institute, $refreshToken);
        }
        return $client;
    }

    /** Exchanges the refresh token, stores the replacement, or marks the connection unusable. */
    private function refresh(Client $client, int $institute, string $refreshToken): void
    {
        try {
            $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (\Throwable $exception) {
            $this->connections->markReconnectRequired($institute, 'Google refused the token refresh.');
            throw new GoogleReconnectRequired('Google authorisation expired or was revoked.', 0, $exception);
        }
        if (!is_array($token) || isset($token['error']) || !isset($token['access_token'])) {
            // invalid_grant is the revoked/expired case; anything else here is equally unusable.
            $this->connections->markReconnectRequired($institute, 'Google refused the token refresh.');
            throw new GoogleReconnectRequired('Google authorisation expired or was revoked.');
        }
        $client->setAccessToken($token);
        $this->connections->storeRefreshedToken($institute, $this->cipher->encrypt((string) $token['access_token']), date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)));
    }

    public function cipher(): TokenCipher { return $this->cipher; }
    public function connections(): GoogleConnectionRepository { return $this->connections; }
}
