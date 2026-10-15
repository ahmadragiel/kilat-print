<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Read-only payment reporting.
 *
 * There is no approve or reject action here by design: the payment provider
 * decides the outcome, so an admin only ever observes it.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::in(PaymentStatus::values())]]);

        return view('admin.payments', [
            'orders' => Order::with(['customer.user', 'payment'])
                ->whereHas('payment')
                ->when($request->string('status')->value, fn ($query, $status) => $query->whereHas('payment', fn ($payment) => $payment->where('status', $status)))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'statuses' => PaymentStatus::cases(),
        ]);
    }

    /**
     * Legacy proof download.
     *
     * Current transactions are settled by the gateway and never carry a proof,
     * but the `proof_*` columns stay so historical records remain readable.
     */
    public function proof(Order $order)
    {
        $path = $order->payment?->proof_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $order->payment->proof_original_filename);
    }
}
