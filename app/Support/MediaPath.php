<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves a stored catalog media reference into a browser URL.
 *
 * Two storage locations are in play:
 *  - illustrations shipped with the project live in `public/images/...`;
 *  - admin uploads live on the `public` disk (`storage/app/public/...`).
 *
 * Both must resolve to something the browser can load, otherwise seeded
 * products would fall back to the placeholder image.
 */
final class MediaPath
{
    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (is_file(public_path($path))) {
            return asset($path);
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}