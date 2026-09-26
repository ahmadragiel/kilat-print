<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCustomDesignRequest;
use App\Http\Requests\UploadCustomDesignAssetRequest;
use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\Customer;
use App\Services\CustomDesignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customiser persistence API.
 *
 * Route names expected (wired by the main session):
 *   POST   custom-designs.store         -> store()      create a draft
 *   PUT    custom-designs.update        -> update()     replace a draft (version + 1)
 *   POST   custom-designs.assets.store  -> storeAsset() upload a private bitmap
 *   GET    custom-designs.assets.show   -> show()       stream a private asset
 *
 * Every handler reads its route parameter loosely (any name, with or without explicit
 * route-model binding) so the integration only has to pick the names above.
 */
class CustomDesignController extends Controller
{
    public function __construct(private readonly CustomDesignService $designs) {}

    /* ---------------------------------------------------------------------
    | Drafts
    | ------------------------------------------------------------------ */

    /** POST: create a new draft (supplying a draft id switches this into update mode). */
    public function store(SaveCustomDesignRequest $request): JsonResponse
    {
        return $this->save($request);
    }

    /** PUT: save over an existing draft. */
    public function update(SaveCustomDesignRequest $request): JsonResponse
    {
        return $this->save($request);
    }

    /** Shared create/update handler; response is `{id, version, design, asset_urls}`. */
    public function save(SaveCustomDesignRequest $request): JsonResponse
    {
        $customer = $this->customer($request);

        $saved = $this->designs->save($customer, $this->resolveDraft($request), [
            'product_id' => $request->validated('product_id'),
            'specification' => $request->specificationPayload(),
            'design' => $request->designPayload(),
            'status' => $request->input('status'),
        ]);

        return response()->json($this->designs->draftPayload($saved));
    }

    /** Read a draft back; response is `{id, version, design, asset_urls}`. */
    public function showDraft(Request $request, mixed $draft = null): JsonResponse
    {
        $model = $this->resolveDraft($request, $draft, required: true);

        abort_unless($this->designs->canViewDraft($request->user(), $model), 403, 'Draft desain tidak dapat diakses.');

        return response()->json($this->designs->draftPayload($model->load('assets')));
    }

    /* ---------------------------------------------------------------------
    | Assets
    | ------------------------------------------------------------------ */

    /** POST: upload a private JPG/PNG bitmap; response is `{id, url, width, height, original_filename}`. */
    public function storeAsset(UploadCustomDesignAssetRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $draft = $this->resolveDraft($request, required: true);
        $file = $request->uploadedFile();

        abort_if($file === null, 422, 'Gambar wajib diunggah.');

        $asset = $this->designs->storeAsset($customer, $draft, $file, $request->user());

        return response()->json($this->designs->assetPayload($asset));
    }

    /** Alias for {@see storeAsset()}. */
    public function upload(UploadCustomDesignAssetRequest $request): JsonResponse
    {
        return $this->storeAsset($request);
    }

    /** GET: stream a private asset through the app (inline, or `?download=1`). */
    public function show(Request $request, mixed $asset = null, bool $forceDownload = false): StreamedResponse
    {
        $model = $this->resolveAsset($request, $asset);

        abort_unless($this->designs->canViewAsset($request->user(), $model), 403, 'Aset desain tidak dapat diakses.');

        return $this->stream($model, $forceDownload || $request->boolean('download'));
    }

    /** Alias for {@see show()} that forces an attachment download. */
    public function download(Request $request, mixed $asset = null): StreamedResponse
    {
        return $this->show($request, $asset, forceDownload: true);
    }

    /* ---------------------------------------------------------------------
    | Internals
    | ------------------------------------------------------------------ */

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'Profil pelanggan tidak ditemukan.');

        return $customer;
    }

    /**
     * Resolve the draft from the route parameter, `draft_id` or `id`.
     *
     * Returns null (meaning "create") only when no identifier was supplied at all.
     */
    private function resolveDraft(Request $request, mixed $draft = null, bool $required = false): ?CustomDesignDraft
    {
        $key = $draft;

        if (! $key instanceof CustomDesignDraft) {
            $key = $request->route('draft')
                ?? $request->route('customDesignDraft')
                ?? $request->route('customDesign')
                ?? $request->input('draft_id')
                ?? $request->input('id');
        }

        $key = $key instanceof CustomDesignDraft ? (string) $key->getKey() : $key;

        if (! is_string($key) || $key === '') {
            abort_if($required, 404, 'Draft desain tidak ditemukan.');

            return null;
        }

        $model = CustomDesignDraft::query()->find($key);

        abort_if($model === null, 404, 'Draft desain tidak ditemukan.');

        return $model;
    }

    /** Resolve an asset from the route parameter regardless of how it is named. */
    private function resolveAsset(Request $request, mixed $asset): CustomDesignAsset
    {
        if ($asset instanceof CustomDesignAsset) {
            return $asset;
        }

        $candidates = [];

        if (is_string($asset) && $asset !== '') {
            $candidates[] = $asset;
        }

        foreach ((array) $request->route()->parameters() as $value) {
            if (is_string($value) && $value !== '' && ! in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $candidate) {
            $model = CustomDesignAsset::query()->find($candidate);

            if ($model instanceof CustomDesignAsset) {
                return $model;
            }
        }

        abort(404, 'Aset desain tidak ditemukan.');
    }

    private function stream(CustomDesignAsset $asset, bool $download): StreamedResponse
    {
        $name = $asset->disk ?: $this->designs->diskName();
        $disk = Storage::disk($name);

        abort_unless($disk->exists($asset->path), 404, 'File aset desain tidak ditemukan.');

        $disposition = $download ? 'attachment' : 'inline';

        return response()->stream(function () use ($name, $asset): void {
            $stream = Storage::disk($name)->readStream($asset->path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) ($asset->size ?? 0),
            'Content-Disposition' => $disposition.'; filename="'.$this->safeFilename($asset->original_filename).'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeFilename(?string $value): string
    {
        $name = basename((string) $value);
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', $name) ?? '';
        $name = trim($name, '_.');

        return Str::limit($name === '' ? 'aset-desain' : $name, 180, '');
    }
}
