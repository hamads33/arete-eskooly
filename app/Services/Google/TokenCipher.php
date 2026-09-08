<?php
declare(strict_types=1);
namespace App\Services\Google;
use RuntimeException;

/**
 * Authenticated encryption for OAuth tokens.
 *
 * AES-256-GCM through OpenSSL, keyed from APP_KEY, stored as base64(iv|tag|ciphertext) — the same
 * scheme CredentialService already uses for managed passwords, so the deployment has one cipher to
 * audit rather than two. GCM is used specifically because it authenticates: a tampered ciphertext
 * fails to decrypt instead of yielding attacker-chosen plaintext.
 *
 * Tokens must never be logged, echoed, or placed in a URL or view.
 */
final class TokenCipher
{
    public function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $data = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($data === false) {
            throw new RuntimeException('Could not protect the Google token.');
        }
        return base64_encode($iv . $tag . $data);
    }

    /** Null on any failure — a corrupt or foreign-keyed token is treated as "not connected". */
    public function decrypt(?string $cipher): ?string
    {
        if ($cipher === null || $cipher === '') {
            return null;
        }
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    private function key(): string
    {
        return hash('sha256', (string) (getenv('APP_KEY') ?: 'arete-managed-credentials'), true);
    }
}
