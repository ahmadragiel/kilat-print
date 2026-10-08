<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $operatorId = $request->user()->operator->id;
        $jobs = ProductionOrder::where('operator_id', $operatorId);
        $today = (clone $jobs)->whereHas('order', fn ($order) => $order->whereDate('created_at', today()))->count();
        $completed = (clone $jobs)->whereIn('status', [ProductionStatus::Completed])->count();

        return view('operator.dashboard', [
            'totalJobs' => (clone $jobs)->count(),
            'todayJobs' => $today,
            'waitingProduction' => (clone $jobs)->where('status', ProductionStatus::InDesign)->count(),
            'inProduction' => (clone $jobs)->whereIn('status', [ProductionStatus::Printing, ProductionStatus::Finishing, ProductionStatus::Packing])->count(),
            'finishing' => (clone $jobs)->where('status', ProductionStatus::Finishing)->count(),
            'qualityCheck' => (clone $jobs)->where('status', ProductionStatus::QualityControl)->count(),
            'completed' => $completed,
            'recentJobs' => (clone $jobs)->with(['order.customer.user', 'order.items'])->latest()->limit(6)->get(),
        ]);
    }
}
