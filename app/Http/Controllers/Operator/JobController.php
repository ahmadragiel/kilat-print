<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ProductionStatus;
use App\Http\Controllers\Controller;
use App\Models\DesignFile;
use App\Models\ProductionOrder;
use App\Models\ProductionPhoto;
use App\Services\ProductionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'deadline' => ['nullable', 'date'],
        ]);
        $this->authorize('viewAny', ProductionOrder::class);
        $query = ProductionOrder::where('operator_id', $request->user()->operator->id)->with(['order.customer.user', 'order.items']);
        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('order', fn ($order) => $order->where('number', 'like', "%{$search}%")->orWhereHas('customer.user', fn ($user) => $user->where('name', 'like', "%{$search}%")));
        }
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->value()));
        $query->when($request->filled('deadline'), fn ($q) => $q->whereDate('deadline', '<=', $request->date('deadline')));

        return view('operator.jobs.index', [
            'jobs' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => ProductionStatus::cases(),
            'filters' => $request->only(['search', 'status', 'deadline']),
        ]);
    }

    public function show(ProductionOrder $job): View
    {
        $this->authorize('view', $job);
        $job->load(['order.customer.user', 'order.address', 'order.items.product', 'order.items.customDesignDraft.assets', 'order.designFiles', 'qualityChecks.checker', 'photos']);

        return view('operator.jobs.show', ['job' => $job]);
    }

    public function status(Request $request, ProductionOrder $job, ProductionService $production): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'status' => ['required', 'in:PRINTING,FINISHING,PACKING,QUALITY_CONTROL'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $target = ProductionStatus::from($data['status']);
        $progress = $data['progress'] ?? match ($target) {
            ProductionStatus::Printing => 25,
            ProductionStatus::Finishing => 60,
            ProductionStatus::Packing => 80,
            ProductionStatus::QualityControl => 90,
            default => 0,
        };
        $production->transition($job, $target, $request->user(), $progress, $data['note'] ?? null);

        return back()->with('success', "Status produksi diperbarui ke {$target->label()}.");
    }

    public function qualityCheck(Request $request, ProductionOrder $job, ProductionService $production): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate([
            'result' => ['required', 'in:PASS,FAIL,REWORK'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = $data['result'] === 'PASS' ? 'PASS' : 'FAIL';
        if ($result !== 'PASS' && blank($data['notes'] ?? null)) {
            return back()->withErrors(['notes' => 'Catatan wajib diisi untuk QC FAIL/REWORK.'])->withInput();
        }
        $production->qualityCheck($job, $request->user(), $result, $data['notes'] ?? null);

        return back()->with('success', $result === 'PASS' ? 'Quality check lulus; pesanan siap.' : 'Quality check gagal; job dikembalikan untuk rework.');
    }

    public function progress(Request $request, ProductionOrder $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate(['progress' => ['required', 'integer', 'min:0', 'max:100'], 'note' => ['nullable', 'string', 'max:1000']]);
        $old = $job->status;
        $job->update(['progress' => $data['progress']]);
        $job->statusHistories()->create([
            'changed_by' => $request->user()->id,
            'old_status' => $old,
            'new_status' => $old,
            'progress' => $data['progress'],
            'note' => $data['note'] ?? 'Progres diperbarui.',
        ]);

        return back()->with('success', 'Progres produksi diperbarui.');
    }

    public function note(Request $request, ProductionOrder $job): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate(['notes' => ['required', 'string', 'max:5000']]);
        $job->update(['notes' => $data['notes']]);

        return back()->with('success', 'Catatan produksi disimpan.');
    }

    public function photo(Request $request, ProductionOrder $job, ProductionService $production): RedirectResponse
    {
        $this->authorize('update', $job);
        $data = $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $production->addPhoto($job, $request->user(), $data['photo']);

        return back()->with('success', 'Foto progres berhasil diunggah.');
    }

    public function design(DesignFile $design)
    {
        $this->authorize('view', $design);
        abort_unless(Storage::disk('local')->exists($design->path), 404);

        return Storage::disk('local')->download($design->path, $design->original_filename);
    }

    public function downloadPhoto(ProductionPhoto $photo)
    {
        $photo->load('productionOrder');
        $this->authorize('view', $photo->productionOrder);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->download($photo->path, $photo->original_filename);
    }
}
