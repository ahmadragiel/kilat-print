<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

/**
 * A private bitmap uploaded for a {@see CustomDesignDraft}.
 *
 * Files live on a non-public disk (default `local`, rooted at storage/app/private);
 * the raw `path` is never serialised and is only ever consumed by the controller
 * that streams the file back through an authorised route.
 *
 * @property string $draft_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property-read CustomDesignDraft $draft
 * @property-read User|null $uploader
 */
class CustomDesignAsset extends Model
{
    use HasUuids;

    /** Route name wired by the main session; the asset is served through it. */
    public const ROUTE_NAME = 'custom-designs.show';

    /** Used only until the asset route is registered. */
    public const FALLBACK_PATH = '/custom-designs/assets/';

    protected $fillable = [
        'draft_id',
        'uploaded_by',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    /** Never expose filesystem locations through array/JSON serialisation. */
    protected $hidden = [
        'path',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(CustomDesignDraft::class, 'draft_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->uploader();
    }

    /** Same-origin, authorised URL that serves this asset through the app. */
    public function authorizedUrl(): string
    {
        return self::urlFor($this);
    }

    /**
     * Build the authorised, root-relative asset URL.
     *
     * The identifier is passed positionally so any route parameter name works.
     */
    public static function urlFor(self|string $asset): string
    {
        $key = $asset instanceof self ? (string) $asset->getKey() : $asset;

        if (Route::has(self::ROUTE_NAME)) {
            return route(self::ROUTE_NAME, $key, absolute: false);
        }

        return self::FALLBACK_PATH.$key;
    }
}
