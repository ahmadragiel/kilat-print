<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DesignFile;
use App\Models\Order;
use App\Services\DesignReviewService;
use App\Services\OrderStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DesignController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', 'string', 'max:40']]);

        return view('admin.designs', [
            'designs' => DesignFile::with(['order.customer.user', 'order.items', 'uploader', 'reviewer'])
                ->when($request->string('status')->value, fn ($query, $status) => $query->where('status', $status))
                ->latest()->paginate(20)->withQueryString(),
            'statuses' => DesignStatus::cases(),
        ]);
    }

    public function approve(Request $request, DesignFile $design, DesignReviewService $designs): RedirectResponse
    {
        $this->authorize('view', $design);
        $designs->approve($design, $request->user());

        return back()->with('success', 'Desain disetujui dan pesanan siap dijadwalkan.');
    }

    public function requestRevision(Request $request, DesignFile $design, DesignReviewService $designs): RedirectResponse
    {
        $this->authorize('view', $design);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $designs->requestRevision($design, $request->user(), $data['reason']);

        return back()->with('success', 'Permintaan revisidesign dikirim ke customer.');
    }

    public function sendToReview(Request $request, Order $order, OrderStatusService $statuses): RedirectResponse
    {
        $this->authorize('update', $order);
        $statuses->transition($order, OrderStatus::DesignReview, $request->user(), 'Pesanan masuk antrean review desain.');

        return back()->with('success', 'Pesanan dikirim ke review desain.');
    }

    public function download(DesignFile $design)
    {
        $this->authorize('view', $design);
        abort_unless(Storage::disk('local')->exists($design->path), 404);

        return Storage::disk('local')->download($design->path, $design->original_filename);
    }
}
