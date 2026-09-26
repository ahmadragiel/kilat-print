<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('admin.invoices.index', ['orders' => Order::with('customer.user')->whereIn('status', [
            OrderStatus::PaymentConfirmed, OrderStatus::DesignReview, OrderStatus::DesignRevision,
            OrderStatus::DesignApproved, OrderStatus::WaitingProduction, OrderStatus::InProduction,
            OrderStatus::Finishing, OrderStatus::QualityCheck, OrderStatus::Ready,
            OrderStatus::Shipped, OrderStatus::Completed,
        ])->latest()->paginate(20)]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        $order->load(['customer.user', 'items', 'address', 'payment']);

        return view('admin.invoice', compact('order'));
    }
}
