<?php

namespace App\Support;

class StorageUrl
{
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return route('files.show', ['path' => ltrim($path, '/')]);
    }
}
