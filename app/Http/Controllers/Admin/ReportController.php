<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = ($request->date('from') ?: now()->startOfYear())->startOfDay();
        $to = ($request->date('to') ?: now())->endOfDay();
        $base = Order::whereBetween('created_at', [$from, $to]);
        $revenueStatuses = [
            OrderStatus::PaymentConfirmed, OrderStatus::DesignReview, OrderStatus::DesignRevision, OrderStatus::DesignApproved,
            OrderStatus::WaitingProduction, OrderStatus::InProduction, OrderStatus::Finishing, OrderStatus::QualityCheck,
            OrderStatus::Ready, OrderStatus::Shipped, OrderStatus::Completed,
        ];

        $orders = (clone $base)->get();
        $revenueStatusValues = array_map(fn (OrderStatus $status) => $status->value, $revenueStatuses);
        $statusValue = fn (Order $order) => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $paid = $orders->filter(fn (Order $order) => in_array($statusValue($order), $revenueStatusValues, true));
        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->select('order_items.product_id', 'order_items.product_name', DB::raw('SUM(order_items.quantity) as quantity_sold'), DB::raw('SUM(order_items.line_total) as revenue'))
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('quantity_sold')->limit(10)->get();

        $newCustomers = Customer::whereBetween('created_at', [$from, $to])->count();
        $returningCustomers = Customer::whereHas('orders')->whereNotIn('id', Customer::whereBetween('created_at', [$from, $to])->select('id'))->count();
        $daily = $paid->groupBy(fn (Order $order) => $order->created_at->format('Y-m-d'))->map->sum('grand_total')->sortKeys();

        return view('admin.reports', [
            'from' => $from,
            'to' => $to,
            'kpi' => [
                'revenue' => (int) $paid->sum('grand_total'),
                'orders' => $orders->count(),
                'average' => $orders->count() ? (int) round($orders->avg('grand_total')) : 0,
                'completed' => $orders->filter(fn (Order $order) => $statusValue($order) === OrderStatus::Completed->value)->count(),
                'cancelled' => $orders->filter(fn (Order $order) => $statusValue($order) === OrderStatus::Cancelled->value)->count(),
                'processing' => $orders->filter(fn (Order $order) => ! in_array($statusValue($order), [OrderStatus::Completed->value, OrderStatus::Cancelled->value, OrderStatus::PendingPayment->value, OrderStatus::PaymentReview->value], true))->count(),
                'newCustomers' => $newCustomers,
                'returningCustomers' => $returningCustomers,
            ],
            'topProducts' => $topProducts,
            'dailyLabels' => $daily->keys(),
            'dailyRevenue' => $daily->values(),
            'statusCounts' => $orders->groupBy(fn (Order $order) => $order->status->label())->map->count(),
        ]);
    }
}
