<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DesignUploadRequest;
use App\Http\Requests\PaymentProofRequest;
use App\Models\DesignFile;
use App\Models\Order;
use App\Services\DesignReviewService;
use App\Services\OrderStatusService;
use App\Services\PaymentVerificationService;
use App\Services\RepeatOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);
        $orders = $request->user()->customer->orders()->with(['items', 'payment'])->latest()->paginate(10);

        return view('customer.orders.index', compact('orders'));
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['items.product', 'items.customDesignDraft.assets', 'payment', 'address', 'statusHistories.changer', 'designFiles.reviewer', 'production.operator.user']);

        return view('customer.orders.show', compact('order'));
    }

    public function uploadPayment(PaymentProofRequest $request, Order $order, PaymentVerificationService $payments): RedirectResponse
    {
        $payments->upload($order, $request->user(), $request->file('proof'));

        return back()->with('success', 'Bukti pembayaran diunggah dan menunggu verifikasi admin.');
    }

    public function uploadDesign(DesignUploadRequest $request, Order $order, DesignReviewService $designs): RedirectResponse
    {
        $designs->upload($order, $request->user(), $request->file('design'), $request->integer('order_item_id') ?: null, $request->input('notes'));

        return back()->with('success', 'Desain baru berhasil diunggah.');
    }

    public function downloadDesign(Order $order, DesignFile $design)
    {
        abort_unless($design->order_id === $order->id, 404);
        $this->authorize('view', $design);
        abort_unless(Storage::disk('local')->exists($design->path), 404, 'File desain tidak ditemukan.');

        return Storage::disk('local')->download($design->path, $design->original_filename);
    }

    public function repeat(Request $request, Order $order, RepeatOrderService $repeats): RedirectResponse
    {
        $this->authorize('view', $order);
        $repeats->repeat($request->user()->customer, $order->load('items.product'));

        return redirect()->route('cart.index')->with('success', 'Konfigurasi order disalin ke keranjang dengan harga terbaru.');
    }

    public function cancel(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorize('view', $order);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $statuses->transition($order, OrderStatus::Cancelled, $request->user(), $data['reason'] ?? 'Dibatalkan customer.');

        return back()->with('success', 'Pesanan dibatalkan.');
    }
}
