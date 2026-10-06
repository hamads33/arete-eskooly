<?php
declare(strict_types=1);
namespace App\Services\Sms;

use DomainException;

/**
 * The single place phone numbers are normalised for SMS.
 *
 * Output is E.164 with a leading "+", e.g. +923001234567. National numbers are completed with
 * the institute's configured country code; numbers already carrying a country code (+44…,
 * 0044…) are kept as they are, so international guardians still work.
 */
final class PhoneNumber
{
    /** @throws DomainException with a message safe to show to the admin */
    public static function normalize(string $input, string $defaultCountryCode = '92'): string
    {
        $cc = preg_replace('/\D/', '', $defaultCountryCode) ?: '92';
        $raw = trim($input);
        if ($raw === '') {
            throw new DomainException('Phone number is missing.');
        }
        if (preg_match('/[^\d\s\-().+\/]/', $raw)) {
            throw new DomainException("\"{$raw}\" is not a valid phone number.");
        }
        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (!$plus && str_starts_with($digits, '00')) {
            $plus = true;
            $digits = substr($digits, 2);
        }
        if (!$plus) {
            if (str_starts_with($digits, '0')) {
                // National trunk prefix: 03001234567 -> 92 3001234567.
                $digits = $cc . substr($digits, 1);
            } elseif (!str_starts_with($digits, $cc) || strlen($digits) <= 10) {
                // Bare national number: 3001234567 -> 92 3001234567.
                $digits = $cc . $digits;
            }
        }
        if (!preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            throw new DomainException("\"{$raw}\" is not a valid phone number.");
        }
        // Pakistani numbers must be mobiles (3XX XXXXXXX); SMS cannot reach landlines.
        if (str_starts_with($digits, '92') && !preg_match('/^923\d{9}$/', $digits)) {
            throw new DomainException("\"{$raw}\" is not a valid Pakistani mobile number (expected 03XX XXXXXXX).");
        }
        return '+' . $digits;
    }

    /** Normalised number, or null when the input is empty or invalid. */
    public static function tryNormalize(?string $input, string $defaultCountryCode = '92'): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }
        try {
            return self::normalize($input, $defaultCountryCode);
        } catch (DomainException) {
            return null;
        }
    }
}
