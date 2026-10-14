<?php

declare(strict_types=1);

namespace App\Platform;

use App\Support\UploadPolicy;

/**
 * A school's logo from its sign-up. It waits in the main portal's private storage until the
 * school is approved, then is copied into the school's own uploads and set as its institute logo,
 * which the school's header, login page, browser icon, app icon and PDFs already use.
 */
final class SchoolLogo
{
    public const MAX_BYTES = 1048576;

    private static function dir(): string
    {
        return rtrim(str_replace('\\', '/', PlatformConfig::get('LOGOS_PATH', BASE_PATH . '/storage/school-logos')), '/');
    }

    /** The waiting logo for a school address, if one was uploaded. */
    public static function pending(string $slug): ?string
    {
        if (!SchoolProvisioner::validSlug($slug)) return null;
        foreach (['png', 'jpg'] as $ext) {
            $path = self::dir() . '/' . $slug . '.' . $ext;
            if (is_file($path)) return $path;
        }
        return null;
    }

    /**
     * Validates and keeps an uploaded logo for a school address.
     * @return bool|null null when no file was chosen, false when the file is not an acceptable image
     */
    public static function storePending(?array $file, string $slug): ?bool
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (!SchoolProvisioner::validSlug($slug)) return false;
        $policy = new UploadPolicy(['image/png', 'image/jpeg'], ['png', 'jpg', 'jpeg'], self::MAX_BYTES);
        if (!$policy->validate($file)) return false;
        $info = @getimagesize((string) $file['tmp_name']);
        if (!$info || $info[0] < 16 || $info[1] < 16 || $info[0] > 4000 || $info[1] > 4000) return false;
        $ext = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0750, true) && !is_dir(self::dir())) return false;
        self::discard($slug);
        $target = self::dir() . '/' . $slug . '.' . $ext;
        $moved = is_uploaded_file((string) $file['tmp_name']) ? move_uploaded_file((string) $file['tmp_name'], $target) : copy((string) $file['tmp_name'], $target);
        if (!$moved) return false;
        @chmod($target, 0640);
        return true;
    }

    public static function discard(string $slug): void
    {
        if (!SchoolProvisioner::validSlug($slug)) return;
        foreach (['png', 'jpg'] as $ext) @unlink(self::dir() . '/' . $slug . '.' . $ext);
    }

    /**
     * Copies the waiting logo into the school and makes it the institute logo and app icon.
     * Returns false when there is no logo; throws if copying fails.
     */
    public static function install(string $slug, string $site, string $dbName): bool
    {
        $path = self::pending($slug);
        if ($path === null) return false;
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $dir = $site . '/public/uploads/institutes';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new \RuntimeException('Could not create the school uploads folder.');
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!copy($path, $dir . '/' . $name)) throw new \RuntimeException('Could not copy the school logo.');
        @chmod($dir . '/' . $name, 0644);
        $pdo = SchoolRegistry::serverConnection($dbName);
        $pdo->prepare('UPDATE institutes SET logo_path=:p, updated_at=:u ORDER BY id LIMIT 1')->execute(['p' => 'uploads/institutes/' . $name, 'u' => date('Y-m-d H:i:s')]);
        $manifestFile = $site . '/public/manifest.webmanifest';
        $manifest = json_decode((string) @file_get_contents($manifestFile), true);
        if (is_array($manifest)) {
            $manifest['icons'] = [['src' => '/uploads/institutes/' . $name, 'sizes' => 'any', 'type' => $ext === 'png' ? 'image/png' : 'image/jpeg', 'purpose' => 'any']];
            @file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }
        self::discard($slug);
        return true;
    }
}
