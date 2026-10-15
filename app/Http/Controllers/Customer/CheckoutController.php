<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Cart;
use App\Services\OrderService;
use App\Services\Payment\GatewayException;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentTransactionService;
use App\Services\PriceCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function index(Request $request, PriceCalculationService $calculator): View|RedirectResponse
    {
        $cart = Cart::with(['items.product'])->where('customer_id', $request->user()->customer->id)->first();
        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('warning', 'Keranjang masih kosong.');
        }

        $estimate = $cart->items->map(function ($item) use ($calculator) {
            return $calculator->calculate($item->product, $item->only(['quantity', 'length_cm', 'width_cm']));
        });
        $subtotal = $estimate->sum('total');

        return view('checkout.index', [
            'cart' => $cart,
            'addresses' => $request->user()->customer->addresses()->orderByDesc('is_primary')->get(),
            'subtotal' => $subtotal,
            'deliveryFee' => (int) config('printing.delivery_fee'),
            'paymentMethods' => PaymentMethod::cases(),
            'gatewayName' => app(PaymentGatewayFactory::class)->gateway()->name(),
            'gatewayReady' => app(PaymentGatewayFactory::class)->gateway()->isConfigured(),
        ]);
    }

    public function store(
        CheckoutRequest $request,
        OrderService $orders,
        PaymentTransactionService $transactions,
    ): RedirectResponse {
        $cartCount = Cart::where('customer_id', $request->user()->customer->id)->withCount('items')->first()?->items_count ?? 0;
        if ($cartCount === 0) {
            throw ValidationException::withMessages(['cart' => 'Keranjang belanja kosong.']);
        }

        $order = $orders->checkout($request->user()->customer, $request->validated());

        // Creating the payable transaction is the only step that talks to the
        // provider. If it fails the order stays pending and unpaid, and the
        // customer can retry from the order page.
        try {
            $transactions->initiate($order, PaymentMethod::from($request->validated('payment_method')));
        } catch (GatewayException $exception) {
            report($exception);

            return redirect()
                ->route('customer.orders.show', $order)
                ->with('warning', 'Pesanan dibuat, tetapi transaksi pembayaran belum dapat dibuat. Silakan coba lagi dari halaman pesanan.');
        }

        return redirect()
            ->route('customer.orders.payment', $order)
            ->with('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran untuk memproses pesanan.');
    }
}
