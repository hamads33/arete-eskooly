<?php
declare(strict_types=1);
namespace App\Services\Google;
use App\Core\Session;
use App\Repositories\GoogleConnectionRepository;
use DomainException;

/**
 * The institute-level OAuth handshake (§11).
 *
 * Security properties this class is responsible for:
 *  - the `state` parameter is random per attempt, stored in the admin's own session, compared with
 *    hash_equals, and consumed on use, so a callback replayed from elsewhere cannot land;
 *  - the state also carries the institute the admin was administering, so a callback cannot be
 *    made to write another tenant's connection;
 *  - the redirect URI is fixed by the environment, never taken from the request, so there is no
 *    open redirect;
 *  - tokens are encrypted before they touch the database and never enter a URL, view or log.
 */
final class GoogleOAuthService
{
    private const STATE_KEY = 'google_oauth_state';

    public function __construct(private readonly GoogleClientFactory $factory, private readonly GoogleConnectionRepository $connections, private readonly TokenCipher $cipher, private readonly Session $session) {}

    /** Consent URL, with a freshly minted state bound to this session and institute. */
    public function authorizationUrl(int $institute): string
    {
        $state = bin2hex(random_bytes(32));
        $this->session->set(self::STATE_KEY, ['state' => $state, 'institute_id' => $institute, 'created_at' => time()]);
        $client = $this->factory->base();
        $client->setState($state);
        return $client->createAuthUrl();
    }

    /**
     * Validates the callback and stores the authorisation.
     *
     * @return array{account_email:?string}
     * @throws DomainException on any failure the admin can act on; the message is already
     *         user-safe and carries no Google internals.
     */
    public function completeCallback(string $code, string $state): array
    {
        $expected = $this->session->get(self::STATE_KEY);
        $this->session->forget(self::STATE_KEY); // single use, success or failure
        if (!is_array($expected) || !isset($expected['state'], $expected['institute_id']) || $state === '' || !hash_equals((string) $expected['state'], $state)) {
            throw new DomainException('That Google authorisation could not be verified. Please start the connection again.');
        }
        if (time() - (int) ($expected['created_at'] ?? 0) > 900) {
            throw new DomainException('The Google authorisation took too long. Please start the connection again.');
        }
        if ($code === '') {
            throw new DomainException('Google did not return an authorisation code.');
        }

        $client = $this->factory->base();
        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);
        } catch (\Throwable) {
            throw new DomainException('Google refused the authorisation. Please try connecting again.');
        }
        if (!is_array($token) || isset($token['error']) || !isset($token['access_token'])) {
            throw new DomainException('Google refused the authorisation. Please try connecting again.');
        }
        $client->setAccessToken($token);

        $institute = (int) $expected['institute_id'];
        $existing = $this->connections->find($institute);
        $refresh = isset($token['refresh_token']) ? $this->cipher->encrypt((string) $token['refresh_token']) : null;
        // Google omits refresh_token when the account has already consented and prompt=consent was
        // not honoured. Keeping the stored one is correct; having neither is a dead connection.
        if ($refresh === null && ($existing['refresh_token_encrypted'] ?? null) === null) {
            throw new DomainException('Google did not return a refresh token. Remove this application from your Google account permissions and connect again.');
        }

        $this->connections->connect($institute, (int) (user()['id'] ?? 0), [
            'account_email' => $this->accountEmail($client),
            'calendar_id' => $this->factory->defaultCalendarId(),
            'access_token_encrypted' => $this->cipher->encrypt((string) $token['access_token']),
            'refresh_token_encrypted' => $refresh,
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600)),
            'scopes' => implode(' ', GoogleClientFactory::SCOPES),
        ]);
        return ['account_email' => $this->connections->find($institute)['account_email'] ?? null];
    }

    public function disconnect(int $institute): void
    {
        $this->connections->disconnect($institute);
    }

    /**
     * The connected account's address, shown so an admin can confirm which account is linked.
     * Best-effort: a missing address is not worth failing an otherwise valid connection over.
     */
    private function accountEmail(\Google\Client $client): ?string
    {
        try {
            $token = $client->getAccessToken();
            if (isset($token['id_token'])) {
                $payload = $client->verifyIdToken((string) $token['id_token']);
                if (is_array($payload) && isset($payload['email'])) {
                    return (string) $payload['email'];
                }
            }
            $info = (new \Google\Service\Oauth2($client))->userinfo->get();
            return $info->getEmail() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
