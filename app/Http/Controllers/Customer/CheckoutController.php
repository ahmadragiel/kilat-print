<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Services\OrderService;
use App\Services\PriceCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request, PriceCalculationService $calculator): View
    {
        $cart = Cart::with(['items.product', 'items.material', 'items.finishing'])->where('customer_id', $request->user()->customer->id)->first();
        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('warning', 'Keranjang masih kosong.');
        }

        $estimate = $cart->items->map(function ($item) use ($calculator) {
            return $calculator->calculate($item->product, $item->only(['quantity', 'length_cm', 'width_cm', 'material_id', 'finishing_id']));
        });
        $subtotal = $estimate->sum('total');

        return view('checkout.index', [
            'cart' => $cart,
            'addresses' => $request->user()->customer->addresses()->orderByDesc('is_primary')->get(),
            'subtotal' => $subtotal,
            'deliveryFee' => (int) config('printing.delivery_fee'),
            'bank' => config('printing'),
        ]);
    }

    public function store(CheckoutRequest $request, OrderService $orders): RedirectResponse
    {
        $cartCount = Cart::where('customer_id', $request->user()->customer->id)->withCount('items')->first()?->items_count ?? 0;
        if ($cartCount === 0) {
            throw ValidationException::withMessages(['cart' => 'Keranjang belanja kosong.']);
        }

        $order = $orders->checkout($request->user()->customer, $request->validated());

        return redirect()->route('customer.orders.show', $order)->with('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran transfer bank.');
    }
}
