<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Settings for the school sign-up platform. They live only in the main portal's .env, never in a
 * school's own .env, so a school copy can never reach the registry or the provisioning account.
 */
final class PlatformConfig
{
    /** Subdomains a school may not take: they belong to the main site or to mail/DNS. */
    public const RESERVED = ['www', 'portal', 'admin', 'api', 'app', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'cpanel', 'webmail', 'ns1', 'ns2', 'dev', 'test', 'staging', 'static', 'cdn', 'assets', 'platform', 'support', 'help', 'blog', 'shop', 'status'];

    public static function enabled(): bool
    {
        return school_slug() === '' && filter_var(getenv('PLATFORM_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = getenv('PLATFORM_' . $key);
        return $value === false || $value === '' ? $default : (string) $value;
    }

    public static function domain(): string { return strtolower(self::get('DOMAIN', 'aretecoachingcenter.com')); }

    /** Folder holding one site folder per school (its .env, uploads, sessions and logs). */
    public static function schoolsPath(): string { return rtrim(str_replace('\\', '/', self::get('SCHOOLS_PATH', BASE_PATH . '/storage/schools')), '/'); }

    /**
     * Code every school runs. On the server this must be the `current` symlink, not the release
     * folder, so a deploy moves every school to the new release at once.
     */
    public static function codePath(): string { return rtrim(str_replace('\\', '/', self::get('CODE_PATH', BASE_PATH)), '/'); }

    public static function schoolUrl(string $slug): string
    {
        $template = self::get('SCHOOL_URL', 'https://{slug}.' . self::domain());
        return rtrim(str_replace('{slug}', $slug, $template), '/');
    }
}
