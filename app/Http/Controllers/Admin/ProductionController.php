<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Models\ProductionOrder;
use App\Notifications\OrderNotification;
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

    public function status(Request $request, ProductionOrder $production): RedirectResponse
    {
        $this->authorize('update', $production);
        $data = $request->validate(['status' => ['required'], 'note' => ['nullable', 'string', 'max:1000']]);
        $status = ProductionStatus::tryFrom($data['status']);
        abort_unless($status && $status !== ProductionStatus::Cancelled, 422, 'Status tidak valid.');
        if ($status === ProductionStatus::Completed) {
            abort(422, 'Penyelesaian produksi hanya melalui Quality Control PASS.');
        }
        $oldProductionStatus = $production->status;
        app(ProductionService::class)->transition($production, $status, $request->user(), $production->progress, $data['note'] ?? null);
        if ($oldProductionStatus !== $production->fresh()->status) {
            $order = $production->order;
            if ($order) {
                $order->customer?->user?->notify(new OrderNotification('Status produksi diperbarui', "Produksi pesanan {$order->number}: {$status->label()}.", route('customer.orders.show', $order)));
            }
        }

        return back()->with('success', 'Status produksi diperbarui.');
    }
}
