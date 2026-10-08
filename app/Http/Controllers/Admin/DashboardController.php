<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $orders = Order::query()->get(['id', 'number', 'status', 'customer_id', 'grand_total', 'created_at']);
        $paidStatuses = [OrderStatus::PaymentConfirmed, OrderStatus::DesignReview, OrderStatus::DesignRevision, OrderStatus::DesignApproved, OrderStatus::InProduction, OrderStatus::Completed];
        $paidStatusValues = array_map(fn (OrderStatus $status) => $status->value, $paidStatuses);
        $statusValue = fn (Order $order) => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;

        $months = collect(range(11, 0))->map(function (int $offset) {
            return now()->startOfMonth()->subMonths($offset);
        });
        $monthlyOrders = $orders->filter(fn (Order $order) => $months->contains(fn (Carbon $month) => $order->created_at->isSameMonth($month)));
        $paid = $orders->filter(fn (Order $order) => in_array($statusValue($order), $paidStatusValues, true));

        $revenueByMonth = $months->map(fn (Carbon $month) => (int) $paid->filter(fn (Order $order) => $order->created_at->isSameMonth($month))->sum('grand_total'));
        $ordersByMonth = $months->map(fn (Carbon $month) => $monthlyOrders->filter(fn (Order $order) => $order->created_at->isSameMonth($month))->count());
        $statusDistribution = $orders->groupBy(fn (Order $order) => $order->status->label())->map->count();
        $cancelledCount = $orders->filter(fn (Order $order) => $statusValue($order) === OrderStatus::Cancelled->value)->count();
        $activeStatusValues = $paidStatusValues;
        $activeCustomers = Customer::whereHas('orders', function ($query) use ($activeStatusValues) {
            return $query->whereIn('status', $activeStatusValues);
        })->count();
        $customerDates = Customer::query()->pluck('created_at');

        $popularProducts = OrderItem::query()
            ->select('product_id', 'product_name', DB::raw('SUM(quantity) as quantity_sold'), DB::raw('SUM(line_total) as revenue'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'dashboardStats' => [
                ['label' => 'Total Orders', 'value' => $orders->count(), 'icon' => 'shopping-bag'],
                ['label' => 'Orders Today', 'value' => $orders->whereBetween('created_at', [today(), now()])->count(), 'icon' => 'clock'],
                ['label' => 'Customers', 'value' => Customer::count(), 'icon' => 'users'],
                ['label' => 'Active Orders', 'value' => $orders->filter(fn (Order $order) => in_array($statusValue($order), $activeStatusValues, true))->count(), 'icon' => 'chart'],
                ['label' => 'Completed Orders', 'value' => $orders->filter(fn (Order $order) => $statusValue($order) === OrderStatus::Completed->value)->count(), 'icon' => 'check-circle'],
                ['label' => 'Cancelled Orders', 'value' => $cancelledCount, 'icon' => 'x-circle'],
                ['label' => 'Revenue', 'value' => 'Rp '.number_format((int) $paid->sum('grand_total'), 0, ',', '.'), 'icon' => 'wallet'],
                ['label' => 'Pending Payments', 'value' => Payment::whereIn('status', [PaymentStatus::Unpaid, PaymentStatus::WaitingVerification])->count(), 'icon' => 'credit-card'],
                ['label' => 'Active Customers', 'value' => $activeCustomers, 'icon' => 'user'],
            ],
            'totalOrders' => $orders->count(),
            'ordersToday' => $orders->whereBetween('created_at', [today(), now()])->count(),
            'customers' => Customer::count(),
            'activeOrders' => $orders->filter(fn (Order $order) => in_array($statusValue($order), $activeStatusValues, true))->count(),
            'completedOrders' => $orders->filter(fn (Order $order) => $statusValue($order) === OrderStatus::Completed->value)->count(),
            'cancelledOrders' => $cancelledCount,
            'revenue' => (int) $paid->sum('grand_total'),
            'monthlyRevenue' => (int) $paid->filter(fn (Order $order) => $order->created_at->isSameMonth(now()))->sum('grand_total'),
            'averageOrderValue' => $paid->count() ? (int) round($paid->avg('grand_total')) : 0,
            'activeCustomers' => $activeCustomers,
            'cancellationRate' => $orders->count() ? round($cancelledCount / $orders->count() * 100, 1) : 0,
            'bestSellingProduct' => $popularProducts->first()?->product_name,
            'pendingPayments' => Payment::whereIn('status', [PaymentStatus::Unpaid, PaymentStatus::WaitingVerification])->count(),
            'latestOrders' => Order::with(['customer.user', 'items'])->latest()->limit(8)->get(),
            'pendingPaymentOrders' => Order::with(['customer.user', 'payment'])->whereHas('payment', fn ($query) => $query->whereIn('status', [PaymentStatus::Unpaid, PaymentStatus::WaitingVerification]))->latest()->limit(8)->get(),
            'waitingDesigns' => Order::with(['customer.user', 'designFiles'])->whereIn('status', [OrderStatus::DesignReview, OrderStatus::DesignRevision])->latest()->limit(8)->get(),
            'currentProduction' => Order::with(['production.operator.user'])->whereHas('production')->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->latest()->limit(8)->get(),
            'charts' => [
                'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M Y'))->values(),
                'revenue' => $revenueByMonth,
                'orders' => $ordersByMonth,
                'customerGrowth' => $months->map(fn (Carbon $month) => $customerDates->filter(fn ($date) => $date->isSameMonth($month))->count()),
                'products' => $popularProducts->pluck('product_name'),
                'productQuantities' => $popularProducts->pluck('quantity_sold'),
                'statusLabels' => $statusDistribution->keys(),
                'statusValues' => $statusDistribution->values(),
            ],
        ]);
    }
}
