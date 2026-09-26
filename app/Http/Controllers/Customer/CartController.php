<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomDesignDraft;
use App\Models\Product;
use App\Services\PriceCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $cart = $this->cart($request);
        $cart->load(['items.product.category', 'items.material', 'items.finishing']);

        return view('cart.index', [
            'cart' => $cart,
            'grandTotal' => (int) $cart->items->sum('price_at_addition'),
        ]);
    }

    public function store(StoreCartItemRequest $request, PriceCalculationService $calculator): RedirectResponse
    {
        $data = $request->validated();
        $product = Product::findOrFail($data['product_id']);
        $calculation = $calculator->calculate($product, $data);
        $customerId = $request->user()->customer->id;

        $draft = null;
        if (! empty($data['design_draft_id'])) {
            $draft = CustomDesignDraft::query()
                ->whereKey($data['design_draft_id'])
                ->where('customer_id', $customerId)
                ->where('product_id', $product->id)
                ->firstOrFail();
        }

        $editing = null;
        if (! empty($data['editing_cart_item_id'])) {
            $editing = CartItem::findOrFail($data['editing_cart_item_id']);
            $this->authorizeItem($request, $editing);
            abort_unless((int) $editing->product_id === (int) $product->id, 422, 'Item cart yang diedit tidak sesuai dengan produk.');
        }

        $path = null;
        $customParameters = $request->except([
            'design_file', '_token', 'design_draft_id', 'editing_cart_item_id',
        ]);
        if ($editing && ! $draft) {
            $customParameters = array_merge($editing->custom_parameters ?? [], $customParameters);
        }
        if ($draft) {
            $customParameters['design_draft_id'] = $draft->id;
            $customParameters['design_version'] = $draft->version;
            $customParameters['design_configuration'] = $draft->design;
            $customParameters['design_mockups'] = [
                'front' => $product->front_mockup,
                'back' => $product->back_mockup,
            ];
        }
        if ($request->hasFile('design_file')) {
            $designFile = $request->file('design_file');
            $path = $designFile->store('design-references/'.$customerId, 'local');
            $customParameters['design_original_filename'] = $designFile->getClientOriginalName();
        }

        $cart = $this->cart($request);
        $oldReference = $editing?->design_reference;
        try {
            DB::transaction(function () use ($cart, $editing, $product, $data, $calculation, $customParameters, $path, $draft) {
                $attributes = [
                    'product_id' => $product->id,
                    'quantity' => $data['quantity'],
                    'size' => $data['size'] ?? null,
                    'length_cm' => $data['length_cm'] ?? null,
                    'width_cm' => $data['width_cm'] ?? null,
                    'material_id' => $data['material_id'] ?? null,
                    'finishing_id' => $data['finishing_id'] ?? null,
                    'color' => $data['color'] ?? null,
                    'production_method' => $data['production_method'],
                    'custom_parameters' => $customParameters,
                    'custom_design_draft_id' => $draft?->id,
                    'price_at_addition' => $calculation['total'],
                    'design_reference' => $path ?: $editing?->design_reference,
                    'notes' => $data['notes'] ?? null,
                ];

                if ($editing) {
                    $editing->update($attributes);
                } else {
                    $cart->items()->create($attributes);
                }

                $draft?->update(['status' => 'completed']);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        if ($path && $oldReference && $oldReference !== $path) {
            Storage::disk('local')->delete($oldReference);
        }

        return redirect()->route('cart.index')
            ->with('success', $editing ? 'Konfigurasi dan desain produk berhasil diperbarui.' : 'Konfigurasi produk ditambahkan ke keranjang.');
    }

    public function customize(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        abort_unless($item->product?->status === 'active', 404);

        return redirect()->route('products.customize', [
            'product' => $item->product,
            'draft' => $item->custom_design_draft_id,
            'cart_item' => $item->id,
        ]);
    }

    public function update(Request $request, CartItem $item, PriceCalculationService $calculator): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'size' => ['nullable', 'string', 'max:80'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'finishing_id' => ['nullable', 'integer', 'exists:finishings,id'],
            'color' => ['nullable', 'string', 'max:100'],
            'production_method' => ['required', Rule::in(['digital', 'offset', 'large_format', 'sublimation'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $calculation = $calculator->calculate($item->product, $data);
        $customParameters = array_merge($item->custom_parameters ?? [], $data);
        $item->update($data + ['price_at_addition' => $calculation['total'], 'custom_parameters' => $customParameters]);

        return back()->with('success', 'Item keranjang diperbarui.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        if ($item->design_reference) {
            Storage::disk('local')->delete($item->design_reference);
        }
        $item->delete();

        return back()->with('success', 'Item dihapus dari keranjang.');
    }

    private function cart(Request $request): Cart
    {
        return Cart::firstOrCreate(['customer_id' => $request->user()->customer->id]);
    }

    private function authorizeItem(Request $request, CartItem $item): void
    {
        abort_unless($item->cart->customer_id === $request->user()->customer->id, 403);
    }
}
