<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $orders = $request->user()->customer->orders();
        $active = array_map(fn (OrderStatus $status) => $status->value, [OrderStatus::PaymentConfirmed, OrderStatus::DesignReview, OrderStatus::DesignRevision, OrderStatus::DesignApproved, OrderStatus::WaitingProduction, OrderStatus::InProduction, OrderStatus::Finishing, OrderStatus::QualityCheck, OrderStatus::Ready, OrderStatus::Shipped]);
        $pending = array_map(fn (OrderStatus $status) => $status->value, [OrderStatus::PendingPayment, OrderStatus::PaymentReview]);

        return view('customer.dashboard', [
            'totalOrders' => (clone $orders)->count(),
            'activeOrders' => (clone $orders)->whereIn('status', $active)->count(),
            'completedOrders' => (clone $orders)->where('status', OrderStatus::Completed->value)->count(),
            'pendingPayments' => (clone $orders)->whereIn('status', $pending)->count(),
            'recentOrders' => (clone $orders)->with(['items', 'payment'])->latest()->limit(6)->get(),
        ]);
    }
}
