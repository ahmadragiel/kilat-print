<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\CustomDesignDraft;
use App\Models\Product;
use App\Services\CustomDesignService;
use App\Services\PriceCalculationService;
use App\Support\MediaPath;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'category',
                'materials' => fn ($query) => $query->where('materials.status', 'active'),
                'finishings' => fn ($query) => $query->where('finishings.status', 'active'),
                'priceRules' => fn ($query) => $query->where('active', true),
            ])
            ->firstOrFail();
        $product->increment('popularity_count');

        return view('products.show', compact('product'));
    }

    public function customize(Request $request, Product $product): View
    {
        abort_unless($product->status === 'active', 404);
        $product->load([
            'category',
            'materials' => fn ($query) => $query->where('materials.status', 'active'),
            'finishings' => fn ($query) => $query->where('finishings.status', 'active'),
            'priceRules' => fn ($query) => $query->where('active', true),
        ]);

        $cartItem = null;
        if ($request->filled('cart_item')) {
            $cartItem = CartItem::query()
                ->whereKey($request->integer('cart_item'))
                ->where('product_id', $product->id)
                ->whereHas('cart', fn ($cart) => $cart->where('customer_id', $request->user()->customer->id))
                ->firstOrFail();
        }

        $draftId = $request->string('draft')->value() ?: $cartItem?->custom_design_draft_id;
        $draft = null;
        if (filled($draftId)) {
            $draft = CustomDesignDraft::query()
                ->whereKey($draftId)
                ->where('customer_id', $request->user()->customer->id)
                ->where('product_id', $product->id)
                ->firstOrFail();
        }

        $snapshot = $cartItem?->custom_parameters ?? [];
        $specification = $draft?->specification ?? ($cartItem ? [
            'quantity' => $cartItem->quantity,
            'size' => $cartItem->size,
            'length_cm' => $cartItem->length_cm,
            'width_cm' => $cartItem->width_cm,
            'material_id' => $cartItem->material_id,
            'finishing_id' => $cartItem->finishing_id,
            'color' => $cartItem->color,
            'production_method' => $cartItem->production_method,
            'notes' => $cartItem->notes,
        ] : [
            'quantity' => $product->minimum_order,
            'size' => null,
            'length_cm' => null,
            'width_cm' => null,
            'material_id' => null,
            'finishing_id' => null,
            'color' => null,
            'production_method' => 'digital',
            'notes' => null,
        ]);
        $design = $draft?->design ?? data_get($snapshot, 'design_configuration', [
            'front' => ['elements' => []],
            'back' => ['elements' => []],
        ]);
        $design = $this->hydrateDesignAssetUrls($design, $draft);
        $initialDraft = $draft ? [
            'id' => $draft->id,
            'version' => $draft->version,
            'specification' => $specification,
            'design' => $design,
        ] : null;

        $mockups = [
            'front' => $this->mockupUrl($product->front_mockup),
            'back' => $this->mockupUrl($product->back_mockup),
        ];
        $stickers = collect(glob(public_path('images/stickers/*.svg')) ?: [])
            ->sort()
            ->map(fn (string $path) => [
                'key' => pathinfo($path, PATHINFO_FILENAME),
                'name' => ucfirst(str_replace('-', ' ', pathinfo($path, PATHINFO_FILENAME))),
                'url' => '/images/stickers/'.basename($path),
            ])
            ->values();

        return view('products.customize', [
            'product' => $product,
            'materials' => $product->materials,
            'finishings' => $product->finishings,
            'cartItem' => $cartItem,
            'initialDraft' => $initialDraft,
            'initialDesign' => $design,
            'initialSpecification' => $specification,
            'mockups' => $mockups,
            'hasBackMockup' => filled($mockups['back']),
            'stickers' => $stickers,
        ]);
    }

    /**
     * Re-attach authorised, same-origin asset URLs to a stored design.
     *
     * The persisted JSON never contains filesystem paths or client-supplied URLs; image
     * elements only carry an `asset_id`, so the editor needs a resolved URL to load them.
     */
    private function hydrateDesignAssetUrls(array $design, ?CustomDesignDraft $draft): array
    {
        if (! $draft) {
            return $design;
        }

        return app(CustomDesignService::class)->hydrateDesign($draft);
    }

    private function mockupUrl(?string $path): ?string
    {
        return MediaPath::url($path);
    }

    public function price(Request $request, Product $product, PriceCalculationService $calculator): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'material_id' => ['nullable', 'integer'],
            'finishing_id' => ['nullable', 'integer'],
        ]);

        $calculation = $calculator->calculate($product, $data);

        return response()->json([
            'quantity' => $calculation['quantity'],
            'unit_price' => $calculation['unit_price'],
            'total' => $calculation['total'],
            'formatted' => [
                'unit_price' => 'Rp '.number_format($calculation['unit_price'], 0, ',', '.'),
                'total' => 'Rp '.number_format($calculation['total'], 0, ',', '.'),
            ],
            'breakdown' => $calculation['breakdown'],
            'dimensions' => $calculation['dimensions'],
        ]);
    }
}
