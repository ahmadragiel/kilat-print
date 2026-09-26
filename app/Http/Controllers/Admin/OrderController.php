<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Notifications\OrderNotification;
use App\Services\OrderStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'payment' => ['nullable', 'string', 'max:40'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = Order::query()->with(['customer.user', 'payment']);
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function (Builder $nested) use ($search) {
                $nested->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn (Builder $item) => $item->where('product_name', 'like', "%{$search}%"));
            });
        }
        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->value()));
        $query->when($request->filled('payment'), fn (Builder $q) => $q->whereHas('payment', fn (Builder $p) => $p->where('status', $request->string('payment')->value())));
        $query->when($request->filled('customer'), fn (Builder $q) => $q->where('customer_id', $request->integer('customer')));
        $query->when($request->filled('date_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('date_from')));
        $query->when($request->filled('date_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('date_to')));

        return view('admin.orders.index', [
            'orders' => $query->latest()->paginate(20)->withQueryString(),
            'customers' => Customer::with('user')->orderBy('user_id')->get(),
            'statuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'filters' => $request->only(['search', 'status', 'payment', 'customer', 'date_from', 'date_to']),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['customer.user', 'items.product', 'items.customDesignDraft.assets', 'payment', 'address', 'statusHistories.changer', 'designFiles', 'production.operator.user', 'production.qualityChecks.checker']);

        return view('admin.orders.show', compact('order'));
    }

    public function status(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorize('update', $order);
        $data = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $status = OrderStatus::tryFrom($data['status']);
        abort_unless($status, 422, 'Status tidak valid.');
        $order = $statuses->transition($order, $status, $request->user(), $data['note'] ?? null);

        if ($status === OrderStatus::Shipped && $order->production) {
            $production = $order->production;
            $oldProductionStatus = $production->status;
            $production->update(['status' => ProductionStatus::Shipped, 'finished_at' => now()]);
            $production->statusHistories()->create(['changed_by' => $request->user()->id, 'old_status' => $oldProductionStatus, 'new_status' => ProductionStatus::Shipped, 'progress' => 100, 'note' => $data['note'] ?? 'Pesanan dikirim.']);
            $order->customer?->user?->notify(new OrderNotification('Pesanan dikirim', "Pesanan {$order->number} telah dikirim.", route('customer.orders.show', $order), 'success'));
        } elseif ($status === OrderStatus::Completed && $order->production) {
            $production = $order->production;
            $oldProductionStatus = $production->status;
            $production->update(['status' => ProductionStatus::Completed, 'completed_at' => now(), 'progress' => 100]);
            $production->statusHistories()->create(['changed_by' => $request->user()->id, 'old_status' => $oldProductionStatus, 'new_status' => ProductionStatus::Completed, 'progress' => 100, 'note' => $data['note'] ?? 'Produksi dan order selesai.']);
            $order->update(['completed_at' => now()]);
            $order->customer?->user?->notify(new OrderNotification('Pesanan selesai', "Pesanan {$order->number} telah selesai. Terima kasih!", route('customer.orders.show', $order), 'success'));
        }

        return back()->with('success', 'Status order berhasil diperbarui.');
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);
        $data = $request->validate(['internal_notes' => ['nullable', 'string', 'max:5000']]);
        $order->update($data);

        return back()->with('success', 'Catatan internal disimpan.');
    }
}
