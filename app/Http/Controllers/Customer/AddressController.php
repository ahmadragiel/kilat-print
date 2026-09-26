<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->view('customer.addresses', ['addresses' => $request->user()->customer->addresses()->orderByDesc('is_primary')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $customer = $request->user()->customer;

        DB::transaction(function () use ($customer, $data) {
            if (($data['is_primary'] ?? false) || $customer->addresses()->doesntExist()) {
                $customer->addresses()->update(['is_primary' => false]);
                $data['is_primary'] = true;
            }
            $customer->addresses()->create($data);
        });

        return back()->with('success', 'Alamat berhasil ditambahkan.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);
        $data = $this->validated($request, $address);
        DB::transaction(function () use ($address, $data) {
            if ($data['is_primary'] ?? false) {
                $address->customer->addresses()->whereKeyNot($address->id)->update(['is_primary' => false]);
            }
            $address->update($data);
        });

        return back()->with('success', 'Alamat berhasil diperbarui.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);
        $address->delete();

        return back()->with('success', 'Alamat berhasil dihapus.');
    }

    public function primary(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);
        DB::transaction(function () use ($address) {
            $address->customer->addresses()->update(['is_primary' => false]);
            $address->update(['is_primary' => true]);
        });

        return back()->with('success', 'Alamat utama berhasil diubah.');
    }

    private function validated(Request $request, ?Address $address = null): array
    {
        return $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'is_primary' => ['nullable', 'boolean'],
        ]) + ['is_primary' => $request->boolean('is_primary')];
    }
}
