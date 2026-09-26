<?php

namespace App\Services;

use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Policies\CustomDesignDraftPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Persistence for the product customiser.
 *
 * Guarantees enforced here:
 *  - only known, bounded specification fields and canvas element properties are stored;
 *  - image elements reference an uploaded asset by `asset_id` (client `src` is discarded)
 *    and that asset must belong to the draft of the acting customer;
 *  - stickers may only point at the approved local `/images/stickers/*.svg` set;
 *  - private files left unreferenced by a successful save are removed;
 *  - filesystem paths are never returned to the client.
 */
class CustomDesignService
{
    public const SIDES = CustomDesignDraft::SIDES;

    public const ELEMENT_TYPES = ['image', 'text', 'sticker'];

    public const MAX_ELEMENTS_PER_SIDE = 50;

    public const MAX_ASSETS_PER_DRAFT = 50;

    public const STICKER_SRC_PATTERN = '#^/images/stickers/[A-Za-z0-9._-]+\.svg$#';

    public const STATUSES = [CustomDesignDraft::STATUS_DRAFT, CustomDesignDraft::STATUS_COMPLETED];

    public const PRODUCTION_METHODS = ['digital', 'offset', 'large_format', 'sublimation'];

    /**
     * Bounded numeric element properties: field => [min, max].
     *
     * Keys are canonical (snake_case); the client may send camelCase and the casing is
     * preserved on the persisted payload. The bounds mirror the editor's own clamps.
     */
    public const ELEMENT_NUMERIC_FIELDS = [
        'x' => [-100000, 100000],
        'y' => [-100000, 100000],
        'width' => [0, 100000],
        'height' => [0, 100000],
        'scale' => [0, 100],
        'scale_x' => [0, 100],
        'scale_y' => [0, 100],
        'rotation' => [-360, 360],
        'opacity' => [0, 1],
        'radius' => [0, 100000],
        'font_size' => [6, 400],
        'line_height' => [0, 20],
        'letter_spacing' => [-50, 200],
        'z_index' => [0, 1000],
    ];

    /** Numeric element properties persisted as integers. */
    public const ELEMENT_INTEGER_FIELDS = ['z_index', 'layer'];

    /** Bounded string element properties: field => max length. */
    public const ELEMENT_STRING_FIELDS = [
        'id' => 64,
        'text' => 5000,
        'color' => 64,
        'font_family' => 100,
        'font_weight' => 32,
        'font_style' => 32,
        'text_align' => 32,
        'fill' => 64,
        'sticker_key' => 64,
        'side' => 5,
        'name' => 120,
        'src' => 500,
        'asset_id' => 36,
    ];

    public const ELEMENT_BOOLEAN_FIELDS = [
        'locked',
        'locked_position',
        'locked_rotation',
        'locked_scale',
        'flip_x',
        'flip_y',
        'visible',
        'hidden',
    ];

    /** Bounded specification fields: integers. */
    public const SPECIFICATION_INTEGER_FIELDS = ['quantity', 'material_id', 'finishing_id'];

    /** Bounded specification fields: decimals. */
    public const SPECIFICATION_DECIMAL_FIELDS = ['length_cm', 'width_cm'];

    /** Bounded specification fields: field => max length. */
    public const SPECIFICATION_STRING_FIELDS = [
        'size' => 100,
        'color' => 100,
        'production_method' => 80,
        'notes' => 2000,
    ];

    /* ---------------------------------------------------------------------
    | Configuration
    | ------------------------------------------------------------------ */

    public function diskName(): string
    {
        return (string) config('printing.custom_design_asset_disk', 'local');
    }

    public function maxAssetKilobytes(): int
    {
        return (int) config('printing.custom_design_asset_max_kilobytes', 5120);
    }

    public function maxAssetsPerDraft(): int
    {
        return (int) config('printing.custom_design_assets_per_draft', self::MAX_ASSETS_PER_DRAFT);
    }

    /* ---------------------------------------------------------------------
    | Validation rules (shared with the form requests)
    | ------------------------------------------------------------------ */

    /** @return array<string, list<mixed>> */
    public static function specificationRules(string $prefix = 'specification'): array
    {
        $rules = [
            $prefix => ['sometimes', 'nullable', 'array'],
            $prefix.'.quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            $prefix.'.size' => ['sometimes', 'nullable', 'string', 'max:100'],
            $prefix.'.length_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            $prefix.'.width_cm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            $prefix.'.material_id' => ['sometimes', 'nullable', 'integer', Rule::exists('materials', 'id')],
            $prefix.'.finishing_id' => ['sometimes', 'nullable', 'integer', Rule::exists('finishings', 'id')],
            $prefix.'.production_method' => ['sometimes', 'nullable', Rule::in(self::PRODUCTION_METHODS)],
            $prefix.'.color' => ['sometimes', 'nullable', 'string', 'max:100'],
            $prefix.'.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];

        return $rules;
    }

    /** @return array<string, list<mixed>> */
    public static function elementRules(string $prefix, bool $checkAssetExistence = true): array
    {
        $rules = [
            $prefix => ['required', 'array'],
            $prefix.'.type' => ['required', 'string', Rule::in(self::ELEMENT_TYPES)],
        ];

        foreach (self::ELEMENT_STRING_FIELDS as $field => $max) {
            $rules[$prefix.'.'.$field] = ['sometimes', 'nullable', 'string', 'max:'.$max];
        }

        foreach (self::ELEMENT_NUMERIC_FIELDS as $field => [$min, $max]) {
            $rules[$prefix.'.'.$field] = ['sometimes', 'nullable', 'numeric', 'min:'.$min, 'max:'.$max];
        }

        foreach (self::ELEMENT_BOOLEAN_FIELDS as $field) {
            $rules[$prefix.'.'.$field] = ['sometimes', 'nullable', 'boolean'];
        }

        $rules[$prefix.'.side'] = ['sometimes', 'nullable', 'string', Rule::in(self::SIDES)];

        $rules[$prefix.'.asset_id'] = $checkAssetExistence
            ? ['sometimes', 'nullable', 'uuid', Rule::exists('custom_design_assets', 'id')]
            : ['sometimes', 'nullable', 'uuid'];

        return $rules;
    }

    /** @return array<string, list<mixed>> */
    public static function designRules(string $prefix = 'design', bool $checkAssetExistence = true): array
    {
        $rules = [
            $prefix => ['sometimes', 'nullable', 'array'],
        ];

        foreach (self::SIDES as $side) {
            $rules[$prefix.'.'.$side] = ['sometimes', 'nullable', 'array'];
            $rules[$prefix.'.'.$side.'.elements'] = ['sometimes', 'nullable', 'array', 'max:'.self::MAX_ELEMENTS_PER_SIDE];
            $rules[$prefix.'.'.$side.'.elements.*'] = ['required', 'array'];
            $rules = array_merge($rules, self::elementRules($prefix.'.'.$side.'.elements.*', $checkAssetExistence));
        }

        return $rules;
    }

    /* ---------------------------------------------------------------------
    | Saving drafts
    | ------------------------------------------------------------------ */

    /**
     * Create a new draft or replace an existing one, bumping its version.
     *
     * @param  array<string, mixed>  $payload  raw `product_id`, `specification`, `design`, `status`
     */
    public function save(Customer $customer, ?CustomDesignDraft $draft, array $payload): CustomDesignDraft
    {
        $product = $this->resolveProduct($payload['product_id'] ?? null);

        $normalizedSpecification = $this->snakeCaseKeys(
            is_array($payload['specification'] ?? null) ? $payload['specification'] : []
        );

        [$design, $normalizedDesign, $imageAssets] = $this->inspectDesign($payload['design'] ?? []);

        // Bounded, whitelist-based validation that also covers camelCase payloads.
        Validator::make(
            ['specification' => $normalizedSpecification, 'design' => $normalizedDesign],
            array_merge(self::specificationRules(), self::designRules('design', false))
        )->validate();

        $this->assertStickersAreApproved($normalizedDesign);
        $this->assertImagesHaveAssets($normalizedDesign, $draft);
        $this->assertAssetsBelongToDraft($customer, $draft, $imageAssets);

        $specification = $this->sanitizeSpecification($normalizedSpecification);
        $status = in_array($payload['status'] ?? null, self::STATUSES, true) ? $payload['status'] : null;

        [$saved, $orphaned] = DB::transaction(function () use ($customer, $draft, $product, $specification, $design, $status): array {
            $current = null;

            if ($draft instanceof CustomDesignDraft) {
                $current = CustomDesignDraft::query()->lockForUpdate()->find($draft->getKey());

                abort_if($current === null, 404, 'Draft desain tidak ditemukan.');
                abort_unless((new CustomDesignDraftPolicy)->owns($this->actingUser($customer), $current), 403, 'Draft desain tidak dapat diakses.');
            }

            $attributes = [
                'product_id' => $product->getKey(),
                'specification' => $specification,
                'design' => $design,
            ];

            if ($status !== null) {
                $attributes['status'] = $status;
            }

            if ($current === null) {
                $current = new CustomDesignDraft;
                $current->forceFill(array_merge($attributes, [
                    'id' => (string) Str::uuid(),
                    'customer_id' => $customer->getKey(),
                    'version' => 1,
                    'status' => CustomDesignDraft::STATUS_DRAFT,
                ]));
            } else {
                $current->fill($attributes + ['version' => (int) $current->version + 1]);
            }

            $current->save();

            $referenced = $current->referencedAssetIds();
            $orphaned = $current->assets()
                ->when($referenced !== [], fn ($query) => $query->whereNotIn('id', $referenced))
                ->get();
            $orphaned->each->delete();

            $current->setRelation('assets', $current->assets()->get());

            return [$current->fresh(), $orphaned];
        });

        $this->deleteAssetFiles($orphaned);

        return $saved;
    }

    /* ---------------------------------------------------------------------
    | Asset uploads
    | ------------------------------------------------------------------ */

    public function storeAsset(Customer $customer, CustomDesignDraft $draft, UploadedFile $file, ?User $uploader = null): CustomDesignAsset
    {
        abort_unless((new CustomDesignDraftPolicy)->owns($this->actingUser($customer), $draft), 403, 'Draft desain tidak dapat diakses.');

        if ($draft->assets()->count() >= $this->maxAssetsPerDraft()) {
            throw ValidationException::withMessages([
                'file' => 'Jumlah aset untuk draft ini sudah mencapai batas.',
            ]);
        }

        $disk = $this->diskName();
        $directory = 'custom-design-assets/'.$customer->getKey().'/'.$draft->getKey();
        $name = Str::uuid()->toString().'.'.$this->extensionFor($file);

        $path = $file->storeAs($directory, $name, $disk);

        abort_if($path === false, 500, 'Gagal menyimpan aset desain.');

        try {
            [$width, $height] = $this->dimensions($file, $disk, $path);

            return $draft->assets()->create([
                'id' => (string) Str::uuid(),
                'uploaded_by' => ($uploader ?? $customer->user)?->getKey(),
                'disk' => $disk,
                'path' => $path,
                'original_filename' => $this->safeOriginalFilename($file),
                'mime_type' => $this->mimeTypeFor($file),
                'size' => (int) $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    public function deleteAsset(CustomDesignAsset $asset): void
    {
        $asset->delete();

        $this->deleteAssetFiles(new Collection([$asset]));
    }

    /** Remove a draft together with every private file it owns. */
    public function deleteDraft(CustomDesignDraft $draft): void
    {
        $assets = $draft->assets()->get();

        $draft->delete();

        $this->deleteAssetFiles($assets);
    }

    /* ---------------------------------------------------------------------
    | Response helpers
    | ------------------------------------------------------------------ */

    /** Same-origin, authorised URL for an asset. */
    public function assetUrl(CustomDesignAsset $asset): string
    {
        return CustomDesignAsset::urlFor($asset);
    }

    /** @return array<string, string> asset id => authorised URL */
    public function assetUrls(CustomDesignDraft $draft): array
    {
        return $this->assetsOf($draft)
            ->mapWithKeys(fn (CustomDesignAsset $asset): array => [(string) $asset->getKey() => $this->assetUrl($asset)])
            ->all();
    }

    /**
     * Design payload with every image `src` replaced by its authorised route URL.
     *
     * @return array<string, mixed>
     */
    public function hydrateDesign(CustomDesignDraft $draft): array
    {
        $design = is_array($draft->design) ? $draft->design : [];
        $assets = $this->assetsOf($draft)->keyBy(fn (CustomDesignAsset $asset): string => (string) $asset->getKey());

        foreach (self::SIDES as $side) {
            $elements = $design[$side]['elements'] ?? [];

            if (! is_array($elements)) {
                continue;
            }

            foreach ($elements as $index => $element) {
                if (! is_array($element) || ($element['type'] ?? null) !== 'image') {
                    continue;
                }

                $assetId = $element['asset_id'] ?? $element['assetId'] ?? null;
                $asset = is_string($assetId) ? $assets->get($assetId) : null;

                $design[$side]['elements'][$index]['src'] = $asset instanceof CustomDesignAsset
                    ? $this->assetUrl($asset)
                    : null;
            }
        }

        return $design;
    }

    /** @return array{id: string, version: int, design: array<string, mixed>, asset_urls: array<string, string>} */
    public function draftPayload(CustomDesignDraft $draft): array
    {
        return [
            'id' => (string) $draft->getKey(),
            'version' => (int) $draft->version,
            'design' => $this->hydrateDesign($draft),
            'asset_urls' => $this->assetUrls($draft),
        ];
    }

    /** @return array{id: string, url: string, width: int|null, height: int|null, original_filename: string} */
    public function assetPayload(CustomDesignAsset $asset): array
    {
        return [
            'id' => (string) $asset->getKey(),
            'url' => $this->assetUrl($asset),
            'width' => $asset->width === null ? null : (int) $asset->width,
            'height' => $asset->height === null ? null : (int) $asset->height,
            'original_filename' => (string) $asset->original_filename,
        ];
    }

    /* ---------------------------------------------------------------------
    | Authorisation helpers
    | ------------------------------------------------------------------ */

    public function canViewDraft(?User $user, ?CustomDesignDraft $draft): bool
    {
        if (! $user instanceof User || ! $draft instanceof CustomDesignDraft) {
            return false;
        }

        if (Gate::getPolicyFor($draft) !== null) {
            return Gate::forUser($user)->allows('view', $draft);
        }

        return (new CustomDesignDraftPolicy)->view($user, $draft);
    }

    public function canUpdateDraft(?User $user, ?CustomDesignDraft $draft): bool
    {
        if (! $user instanceof User || ! $draft instanceof CustomDesignDraft) {
            return false;
        }

        if (Gate::getPolicyFor($draft) !== null) {
            return Gate::forUser($user)->allows('update', $draft);
        }

        return (new CustomDesignDraftPolicy)->update($user, $draft);
    }

    public function canViewAsset(?User $user, ?CustomDesignAsset $asset): bool
    {
        if (! $user instanceof User || ! $asset instanceof CustomDesignAsset) {
            return false;
        }

        if (Gate::getPolicyFor($asset) !== null) {
            return Gate::forUser($user)->allows('view', $asset);
        }

        return $this->canViewDraft($user, $asset->draft);
    }

    /* ---------------------------------------------------------------------
    | Internals
    | ------------------------------------------------------------------ */

    protected function actingUser(Customer $customer): User
    {
        $user = $customer->user;

        abort_if($user === null, 403, 'Profil pelanggan tidak ditemukan.');

        return $user;
    }

    protected function assetsOf(CustomDesignDraft $draft): Collection
    {
        return $draft->relationLoaded('assets')
            ? $draft->assets
            : $draft->assets()->get();
    }

    protected function resolveProduct(mixed $productId): Product
    {
        $product = is_numeric($productId)
            ? Product::query()->where('status', 'active')->whereKey((int) $productId)->first()
            : null;

        if (! $product instanceof Product) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk tidak tersedia atau tidak aktif.',
            ]);
        }

        return $product;
    }

    /**
     * Accept both canonical `{side: {elements: [...]}}` and shorthand `{side: [...]}`.
     *
     * The canonical shape is what the editor sends, but silently persisting an empty
     * design when only the shorthand arrives would lose a customer's work without any
     * error, so the shorthand is normalised here rather than ignored.
     */
    protected function elementsOfSide(mixed $side): array
    {
        if (! is_array($side)) {
            return [];
        }

        if (array_is_list($side)) {
            return $side;
        }

        $elements = $side['elements'] ?? [];

        return is_array($elements) ? $elements : [];
    }

    /**
     * Build the persistable design plus a canonical (snake_case) copy for validation.
     *
     * Client key casing is preserved on the persisted payload so a camelCase
     * front-end round-trips its own payload untouched.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, string>}
     */
    protected function inspectDesign(mixed $design): array
    {
        $design = is_array($design) ? $design : [];

        $persisted = [];
        $normalized = [];
        $imageAssets = [];

        foreach (self::SIDES as $side) {
            $rawElements = $this->elementsOfSide($design[$side] ?? null);

            if (! is_array($rawElements)) {
                $rawElements = [];
            }

            $sidePersisted = [];
            $sideNormalized = [];

            foreach (array_values($rawElements) as $index => $rawElement) {
                if (! is_array($rawElement)) {
                    continue;
                }

                [$spellings, $values] = $this->normalizeElementKeys($rawElement);

                if (($values['type'] ?? null) === 'sticker') {
                    $values['src'] = $this->normalizeStickerSource($this->stickerSource($rawElement));
                }

                $sideNormalized[$index] = $values;
                $sidePersisted[$index] = $this->castElement($values, $spellings, $side);

                if (($values['type'] ?? null) !== 'image') {
                    continue;
                }

                $assetId = $values['asset_id'] ?? null;

                if (is_string($assetId) && $assetId !== '') {
                    $imageAssets[$assetId] ??= "design.{$side}.elements.{$index}.asset_id";
                }
            }

            $persisted[$side] = ['elements' => array_values($sidePersisted)];
            $normalized[$side] = ['elements' => array_values($sideNormalized)];
        }

        return [$persisted, $normalized, $imageAssets];
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>} spellings and canonical values
     */
    protected function normalizeElementKeys(array $element): array
    {
        $spellings = [];
        $values = [];

        foreach ($element as $key => $value) {
            $canonical = Str::snake((string) $key);
            $spellings[$canonical] = (string) $key;
            $values[$canonical] = $value;
        }

        return [$spellings, $values];
    }

    /**
     * Keep only whitelisted properties, cast them, and reuse the client's key casing.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $spellings
     * @return array<string, mixed>
     */
    protected function castElement(array $values, array $spellings, string $side): array
    {
        $type = is_string($values['type'] ?? null) ? $values['type'] : '';
        $element = ['type' => $type];

        foreach ($values as $key => $value) {
            if ($key === 'type') {
                continue;
            }

            // A source URL is only meaningful for stickers (approved local SVG set).
            // For uploaded bitmaps the client `src` is never trusted, and for text it
            // is meaningless, so it is dropped in both cases.
            if ($key === 'src') {
                if ($type === 'sticker') {
                    $element['src'] = $values['src'] ?? null;
                }

                continue;
            }

            if (! $this->isKnownElementField($key)) {
                continue;
            }

            $target = $spellings[$key] ?? $key;

            // The side the element is stored under is authoritative: a stored value that
            // disagrees with its location would make the draft impossible to reopen.
            if ($key === 'side') {
                $element[$target] = $side;

                continue;
            }

            if (in_array($key, self::ELEMENT_INTEGER_FIELDS, true)) {
                $element[$target] = (int) round((float) $value);

                continue;
            }

            if (isset(self::ELEMENT_NUMERIC_FIELDS[$key])) {
                $element[$target] = round((float) $value, 4);

                continue;
            }

            if (in_array($key, self::ELEMENT_BOOLEAN_FIELDS, true)) {
                $element[$target] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;

                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '' && $key !== 'text') {
                    continue;
                }

                $max = self::ELEMENT_STRING_FIELDS[$key] ?? null;
                $element[$target] = $max === null ? $value : Str::limit($value, $max, '');

                continue;
            }

            if (is_bool($value) || $value === null) {
                $element[$target] = $value;
            }
        }

        if ($type === 'sticker') {
            $element['src'] = $values['src'] ?? null;
        }

        return $element;
    }

    /**
     * Whether a canonical element property may be persisted at all.
     */
    protected function isKnownElementField(string $key): bool
    {
        return isset(self::ELEMENT_NUMERIC_FIELDS[$key])
            || isset(self::ELEMENT_STRING_FIELDS[$key])
            || in_array($key, self::ELEMENT_INTEGER_FIELDS, true)
            || in_array($key, self::ELEMENT_BOOLEAN_FIELDS, true);
    }

    protected function stickerSource(array $element): mixed
    {
        foreach (['src', 'sticker', 'sticker_src', 'icon', 'url'] as $candidate) {
            foreach ($element as $key => $value) {
                if (Str::snake((string) $key) === $candidate && is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Reduce any supplied URL form to an approved `/images/stickers/*.svg` path,
     * or null when the sticker is not part of the approved local set.
     */
    protected function normalizeStickerSource(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $candidate = $value;

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) === 1) {
            $path = parse_url($value, PHP_URL_PATH);
            $candidate = is_string($path) ? $path : '';
        }

        $candidate = '/'.ltrim($candidate, '/');

        return preg_match(self::STICKER_SRC_PATTERN, $candidate) === 1 ? $candidate : null;
    }

    /** @param array<string, mixed> $design */
    protected function assertStickersAreApproved(array $design): void
    {
        $errors = [];

        foreach (self::SIDES as $side) {
            foreach ($design[$side]['elements'] ?? [] as $index => $element) {
                if (! is_array($element) || ($element['type'] ?? null) !== 'sticker') {
                    continue;
                }

                if (! is_string($element['src'] ?? null) || $element['src'] === '') {
                    $errors["design.{$side}.elements.{$index}.src"] = 'Stiker harus menggunakan aset SVG lokal di /images/stickers.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $design */
    protected function assertImagesHaveAssets(array $design, ?CustomDesignDraft $draft): void
    {
        $errors = [];
        $message = $draft instanceof CustomDesignDraft
            ? 'Gambar harus merujuk pada aset yang sudah diunggah.'
            : 'Unggah aset gambar terlebih dahulu, lalu simpan ulang desain.';

        foreach (self::SIDES as $side) {
            foreach ($design[$side]['elements'] ?? [] as $index => $element) {
                if (! is_array($element) || ($element['type'] ?? null) !== 'image') {
                    continue;
                }

                $assetId = $element['asset_id'] ?? null;

                if (! is_string($assetId) || $assetId === '') {
                    $errors["design.{$side}.elements.{$index}.asset_id"] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, string> $imageAssets asset id => field path */
    protected function assertAssetsBelongToDraft(Customer $customer, ?CustomDesignDraft $draft, array $imageAssets): void
    {
        if ($imageAssets === []) {
            return;
        }

        $ids = array_keys($imageAssets);
        $owned = $draft instanceof CustomDesignDraft
            ? CustomDesignAsset::query()
                ->where('draft_id', (string) $draft->getKey())
                ->whereHas('draft', fn ($query) => $query->where('customer_id', $customer->getKey()))
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all()
            : [];

        $errors = [];
        $message = $draft instanceof CustomDesignDraft
            ? 'Aset gambar tidak valid untuk draft ini.'
            : 'Unggah aset gambar terlebih dahulu, lalu simpan ulang desain.';

        foreach ($ids as $id) {
            if (! in_array((string) $id, $owned, true)) {
                $errors[$imageAssets[$id]] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $specification  already validated, canonical keys
     * @return array<string, mixed>
     */
    protected function sanitizeSpecification(array $specification): array
    {
        $clean = [];

        foreach (self::SPECIFICATION_INTEGER_FIELDS as $field) {
            if (isset($specification[$field]) && is_numeric($specification[$field])) {
                $clean[$field] = (int) $specification[$field];
            }
        }

        foreach (self::SPECIFICATION_DECIMAL_FIELDS as $field) {
            if (isset($specification[$field]) && is_numeric($specification[$field])) {
                $clean[$field] = round((float) $specification[$field], 2);
            }
        }

        foreach (self::SPECIFICATION_STRING_FIELDS as $field => $max) {
            if (isset($specification[$field]) && is_string($specification[$field]) && trim($specification[$field]) !== '') {
                $clean[$field] = Str::limit(trim($specification[$field]), $max, '');
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $data */
    protected function snakeCaseKeys(array $data): array
    {
        $snake = [];

        foreach ($data as $key => $value) {
            $snake[Str::snake((string) $key)] = $value;
        }

        return $snake;
    }

    protected function deleteAssetFiles(Collection $assets): void
    {
        $disk = $this->diskName();

        foreach ($assets as $asset) {
            $path = (string) $asset->path;

            if ($path === '') {
                continue;
            }

            try {
                Storage::disk($asset->disk ?: $disk)->delete($path);
            } catch (Throwable) {
                // A missing file must never break a successful save.
            }
        }
    }

    protected function extensionFor(UploadedFile $file): string
    {
        $extension = match ($this->mimeTypeFor($file)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };

        abort_if($extension === null, 422, 'Format gambar tidak didukung. Gunakan JPG atau PNG.');

        return $extension;
    }

    protected function mimeTypeFor(UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 422, 'Format gambar tidak didukung. Gunakan JPG atau PNG.');

        return $mimeType;
    }

    /** @return array{0: int|null, 1: int|null} */
    protected function dimensions(UploadedFile $file, string $disk, string $path): array
    {
        $size = @getimagesize($file->getRealPath() ?: $file->getPathname());

        if ($size === false) {
            try {
                $size = @getimagesize(Storage::disk($disk)->path($path));
            } catch (Throwable) {
                $size = false;
            }
        }

        return [
            isset($size[0]) ? (int) $size[0] : null,
            isset($size[1]) ? (int) $size[1] : null,
        ];
    }

    protected function safeOriginalFilename(UploadedFile $file): string
    {
        $name = basename((string) $file->getClientOriginalName());
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', $name) ?? '';
        $name = trim($name, '_.');

        return Str::limit($name === '' ? 'gambar' : $name, 200, '');
    }
}
