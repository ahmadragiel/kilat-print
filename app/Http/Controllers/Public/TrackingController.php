<?php

namespace App\Http\Controllers\Public;

use App\Enums\ProductionStatus;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TrackingController
{
    public function index(Request $request): View
    {
        $number = trim((string) $request->query('number', ''));
        $order = null;

        if ($number !== '') {
            $order = Order::query()
                ->with(['items', 'production.statusHistories'])
                ->where('number', $number)
                ->first();
        }

        $steps = [
            ProductionStatus::InDesign,
            ProductionStatus::Printing,
            ProductionStatus::Finishing,
            ProductionStatus::Packing,
            ProductionStatus::QualityControl,
            ProductionStatus::Completed,
        ];

        return view('tracking', compact('order', 'number', 'steps'));
    }
}
