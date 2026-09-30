<?php

namespace App\Content;

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class MediaStorage
{
    public const DISK = 's3';

    public static function disk(): Cloud|FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }

    public static function productionReady(): bool
    {
        if (config('filesystems.disks.'.self::DISK.'.driver') !== 's3') {
            return false;
        }

        if (! filled(config('filesystems.disks.'.self::DISK.'.bucket'))) {
            return false;
        }

        $endpoint = (string) config('filesystems.disks.'.self::DISK.'.endpoint');

        return ! self::looksLocal($endpoint);
    }

    public static function looksLocal(string $endpoint): bool
    {
        if ($endpoint === '') {
            return false;
        }

        $host = strtolower((string) (parse_url($endpoint, PHP_URL_HOST) ?: $endpoint));

        return in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'host.docker.internal'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost')
            || parse_url($endpoint, PHP_URL_PORT) === 4566;
    }
}
