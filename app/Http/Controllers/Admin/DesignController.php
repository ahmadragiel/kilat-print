<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DesignStatus;
use App\Http\Controllers\Controller;
use App\Models\DesignFile;
use Illuminate\Contracts\View\View;
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

    public function download(DesignFile $design)
    {
        $this->authorize('view', $design);
        abort_unless(Storage::disk('local')->exists($design->path), 404);

        return Storage::disk('local')->download($design->path, $design->original_filename);
    }
}
