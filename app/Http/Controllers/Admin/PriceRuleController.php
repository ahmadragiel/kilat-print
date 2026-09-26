<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PriceRuleRequest;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PriceRuleController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'product' => ['nullable', 'integer', 'exists:products,id'],
            'active' => ['nullable', 'boolean'],
        ]);
        $query = PriceRule::with('product');
        if ($request->filled('product')) {
            $query->where('product_id', $request->integer('product'));
        }
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return view('admin.prices.index', [
            'priceRules' => $query->latest()->paginate(20)->withQueryString(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'pricingTypes' => PricingType::cases(),
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function store(PriceRuleRequest $request): RedirectResponse
    {
        PriceRule::create($request->validated() + ['active' => $request->boolean('active')]);

        return redirect()->route('admin.prices.index')->with('success', 'Aturan harga berhasil ditambahkan.');
    }

    public function edit(PriceRule $priceRule): View
    {
        return $this->form($priceRule);
    }

    public function update(PriceRuleRequest $request, PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update($request->validated() + ['active' => $request->boolean('active')]);

        return redirect()->route('admin.prices.index')->with('success', 'Aturan harga berhasil diperbarui.');
    }

    public function destroy(PriceRule $priceRule): RedirectResponse
    {
        $priceRule->update(['active' => false]);

        return back()->with('success', 'Aturan harga dinonaktifkan.');
    }

    private function form(?PriceRule $priceRule): View
    {
        return view('admin.prices.form', [
            'priceRule' => $priceRule,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'pricingTypes' => PricingType::cases(),
        ]);
    }
}
