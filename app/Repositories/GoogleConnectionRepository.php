<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;

/**
 * The institute's single Google connection (§4: one connection per institute, authorised once by
 * an administrator and used on behalf of every teacher).
 *
 * Token columns hold ciphertext produced by App\Services\Google\TokenCipher. Nothing here ever
 * decrypts — callers that legitimately need a plaintext token go through GoogleClientFactory —
 * and `safe()` exists so views can render connection state without the ciphertext being anywhere
 * near the template.
 */
final class GoogleConnectionRepository
{
    private const PROVIDER = 'google_calendar';

    public function __construct(private readonly Database $db) {}

    public function find(int $institute): ?array
    {
        $row = $this->db->query('SELECT * FROM google_oauth_connections WHERE institute_id=:i AND provider=:p LIMIT 1', ['i' => $institute, 'p' => self::PROVIDER])->fetch();
        return is_array($row) ? $row : null;
    }

    /** Connection state safe to hand a view: identity and status only, never a token. */
    public function safe(int $institute): array
    {
        $row = $this->find($institute);
        if ($row === null) {
            return ['status' => 'disconnected', 'account_email' => null, 'calendar_id' => 'primary', 'last_error' => null, 'connected_at' => null];
        }
        return [
            'status' => (string) $row['status'],
            'account_email' => $row['account_email'],
            'calendar_id' => (string) $row['calendar_id'],
            'last_error' => $row['last_error'],
            'connected_at' => $row['connected_at'],
        ];
    }

    public function isConnected(int $institute): bool
    {
        return ($this->find($institute)['status'] ?? '') === 'connected';
    }

    /**
     * Stores a freshly granted authorisation. A refresh token is only overwritten when Google
     * actually returned one: on re-consent Google often omits it, and blanking the stored copy
     * would silently break every later refresh.
     */
    public function connect(int $institute, int $userId, array $data): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->find($institute);
        $fields = [
            'i' => $institute, 'p' => self::PROVIDER, 'u' => $userId,
            'email' => $data['account_email'] ?? null,
            'calendar' => $data['calendar_id'] ?? 'primary',
            'access' => $data['access_token_encrypted'] ?? null,
            'expires' => $data['token_expires_at'] ?? null,
            'scopes' => $data['scopes'] ?? null,
            'now' => $now,
        ];
        if ($existing === null) {
            $this->db->query(
                'INSERT INTO google_oauth_connections(institute_id,provider,connected_by_user_id,account_email,calendar_id,access_token_encrypted,refresh_token_encrypted,token_expires_at,scopes,status,last_error,connected_at,created_at,updated_at)
                 VALUES(:i,:p,:u,:email,:calendar,:access,:refresh,:expires,:scopes,\'connected\',NULL,:now,:now2,:now3)',
                $fields + ['refresh' => $data['refresh_token_encrypted'] ?? null, 'now2' => $now, 'now3' => $now]
            );
            return;
        }
        $refresh = $data['refresh_token_encrypted'] ?? null;
        $sql = 'UPDATE google_oauth_connections SET connected_by_user_id=:u,account_email=:email,calendar_id=:calendar,access_token_encrypted=:access,token_expires_at=:expires,scopes=:scopes,status=\'connected\',last_error=NULL,connected_at=:now'
            . ($refresh !== null ? ',refresh_token_encrypted=:refresh' : '')
            . ',updated_at=:now2 WHERE institute_id=:i AND provider=:p';
        $this->db->query($sql, $fields + ['now2' => $now] + ($refresh !== null ? ['refresh' => $refresh] : []));
    }

    /** Persists a token refreshed mid-request so the next request starts from the new one. */
    public function storeRefreshedToken(int $institute, string $accessCipher, ?string $expiresAt): void
    {
        $this->db->query('UPDATE google_oauth_connections SET access_token_encrypted=:a,token_expires_at=:e,status=\'connected\',last_error=NULL,updated_at=:u WHERE institute_id=:i AND provider=:p', ['a' => $accessCipher, 'e' => $expiresAt, 'u' => date('Y-m-d H:i:s'), 'i' => $institute, 'p' => self::PROVIDER]);
    }

    /** Refresh failed for good (invalid_grant, revoked consent): only a human can fix this. */
    public function markReconnectRequired(int $institute, string $reason): void
    {
        $this->db->query('UPDATE google_oauth_connections SET status=\'reconnect_required\',last_error=:r,updated_at=:u WHERE institute_id=:i AND provider=:p', ['r' => mb_substr($reason, 0, 255), 'u' => date('Y-m-d H:i:s'), 'i' => $institute, 'p' => self::PROVIDER]);
    }

    /** Clears the tokens rather than deleting the row, so the audit trail keeps its history. */
    public function disconnect(int $institute): void
    {
        $this->db->query('UPDATE google_oauth_connections SET access_token_encrypted=NULL,refresh_token_encrypted=NULL,token_expires_at=NULL,status=\'disconnected\',last_error=NULL,updated_at=:u WHERE institute_id=:i AND provider=:p', ['u' => date('Y-m-d H:i:s'), 'i' => $institute, 'p' => self::PROVIDER]);
    }
}
