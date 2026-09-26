<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\ProductionOrder;
use App\Notifications\OrderNotification;
use App\Services\OrderStatusService;
use App\Services\ProductionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'operator' => ['nullable', 'integer', 'exists:operators,id'],
            'deadline' => ['nullable', 'date'],
        ]);
        $this->authorize('viewAny', ProductionOrder::class);
        $query = ProductionOrder::with(['order.customer.user', 'operator.user']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }
        if ($request->filled('operator')) {
            $query->where('operator_id', $request->integer('operator'));
        }
        if ($request->filled('deadline')) {
            $query->whereDate('deadline', '<=', $request->date('deadline'));
        }
        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('order', fn ($order) => $order->where('number', 'like', "%{$search}%")->orWhereHas('customer.user', fn ($user) => $user->where('name', 'like', "%{$search}%")));
        }

        return view('admin.production', [
            'productions' => $query->latest()->paginate(20)->withQueryString(),
            'operators' => Operator::with('user')->whereHas('user', fn ($query) => $query->where('is_active', true))->get(),
            'statuses' => ProductionStatus::cases(),
            'filters' => $request->only(['search', 'status', 'operator', 'deadline']),
        ]);
    }

    public function assign(Request $request, ProductionOrder $production, ProductionService $productionService): RedirectResponse
    {
        $this->authorize('update', $production);
        $data = $request->validate(['operator_id' => ['required', 'exists:operators,id'], 'deadline' => ['nullable', 'date', 'after_or_equal:today']]);
        $productionService->assign($production, Operator::with('user')->findOrFail($data['operator_id']), $request->user(), $data['deadline'] ?? null);

        return back()->with('success', 'Job produksi berhasil ditugaskan.');
    }

    public function status(Request $request, ProductionOrder $production, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorize('update', $production);
        $data = $request->validate(['status' => ['required'], 'note' => ['nullable', 'string', 'max:1000']]);
        $status = OrderStatus::tryFrom($data['status']);
        abort_unless($status, 422, 'Status tidak valid.');
        $order = $statuses->transition($production->order, $status, $request->user(), $data['note'] ?? null);
        $oldProductionStatus = $production->status;
        $updates = [];
        if ($status === OrderStatus::Shipped) {
            $updates = ['status' => ProductionStatus::Shipped, 'finished_at' => now(), 'progress' => 100];
        } elseif ($status === OrderStatus::Completed) {
            $updates = ['status' => ProductionStatus::Completed, 'completed_at' => now(), 'progress' => 100];
            $order->update(['completed_at' => now()]);
        }
        if ($updates) {
            $production->update($updates);
            $production->statusHistories()->create(['changed_by' => $request->user()->id, 'old_status' => $oldProductionStatus, 'new_status' => $updates['status'], 'progress' => 100, 'note' => $data['note'] ?? 'Status produksi diperbarui admin.']);
        }
        if ($status === OrderStatus::Shipped) {
            $order->customer?->user?->notify(new OrderNotification('Pesanan dikirim', "Pesanan {$order->number} telah dikirim.", route('customer.orders.show', $order), 'success'));
        } elseif ($status === OrderStatus::Completed) {
            $order->customer?->user?->notify(new OrderNotification('Pesanan selesai', "Pesanan {$order->number} telah selesai. Terima kasih!", route('customer.orders.show', $order), 'success'));
        }

        return back()->with('success', 'Status produksi diperbarui.');
    }
}
