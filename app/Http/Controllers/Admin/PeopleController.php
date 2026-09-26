<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Operator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function customers(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $query = Customer::with('user')->withCount('orders');
        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('admin.customers', ['customers' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function operators(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $query = Operator::with('user')->withCount('productionOrders');
        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('admin.operators', ['operators' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function toggle(Request $request, string $type, int $user): RedirectResponse
    {
        abort_unless(in_array($type, ['customer', 'operator'], true), 404);
        $model = $type === 'customer' ? Customer::findOrFail($user) : Operator::findOrFail($user);
        $model->user->update(['is_active' => ! $model->user->is_active]);

        return back()->with('success', 'Status akun berhasil diperbarui.');
    }
}
