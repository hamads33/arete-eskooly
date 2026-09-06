<?php

declare(strict_types=1);

namespace App\Support;

final class UploadPolicy
{
    public function __construct(
        private readonly array $allowedMimeTypes,
        private readonly array $allowedExtensions,
        private readonly int $maxBytes,
    ) {
    }

    public function validate(array $file): bool
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $this->maxBytes) return false;
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) ($file['tmp_name'] ?? ''));
        return in_array($extension, $this->allowedExtensions, true) && in_array($mime, $this->allowedMimeTypes, true);
    }

    public function safeFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    }
}
