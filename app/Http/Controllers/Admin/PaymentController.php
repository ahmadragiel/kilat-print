<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'string', 'max:40']]);

        return view('admin.payments', [
            'orders' => Order::with(['customer.user', 'payment'])->whereHas('payment')->when($request->string('status')->value, fn ($q, $status) => $q->whereHas('payment', fn ($payment) => $payment->where('status', $status)))->latest()->paginate(20)->withQueryString(),
            'statuses' => PaymentStatus::cases(),
        ]);
    }

    public function verify(Request $request, Order $order, PaymentVerificationService $payments): RedirectResponse
    {
        $payments->approve($order, $request->user());

        return back()->with('success', 'Pembayaran disetujui.');
    }

    public function reject(Request $request, Order $order, PaymentVerificationService $payments): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payments->reject($order, $request->user(), $data['reason']);

        return back()->with('success', 'Pembayaran ditolak dan customer diberi alasan.');
    }

    public function proof(Order $order)
    {
        $path = $order->payment?->proof_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $order->payment->proof_original_filename);
    }
}
