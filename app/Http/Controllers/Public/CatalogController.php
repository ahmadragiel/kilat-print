<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'gte:min_price'],
            'sort' => ['nullable', 'in:popular,newest,price_low,price_high'],
        ]);

        $products = Product::query()
            ->select('products.*')
            ->addSelect(['starting_price' => PriceRule::query()
                ->select('price')
                ->whereColumn('price_rules.product_id', 'products.id')
                ->where('price_rules.active', true)
                ->orderBy('price_rules.price')
                ->limit(1)])
            ->with(['category', 'priceRules'])
            ->where('status', 'active')
            ->whereHas('priceRules', fn (Builder $price) => $price->where('active', true))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('search')->trim().'%'))
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $request->string('category'))))
            ->when($request->filled('min_price'), fn (Builder $query) => $query->whereHas('priceRules', fn (Builder $price) => $price->where('price', '>=', (int) $request->input('min_price'))))
            ->when($request->filled('max_price'), fn (Builder $query) => $query->whereHas('priceRules', fn (Builder $price) => $price->where('price', '<=', (int) $request->input('max_price'))));

        match ($request->string('sort')->value) {
            'price_low' => $products->orderBy('starting_price'),
            'price_high' => $products->orderByDesc('starting_price'),
            'popular' => $products->orderByDesc('popularity_count'),
            default => $products->latest(),
        };

        return view('products.index', [
            'products' => $products->paginate(12)->withQueryString(),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => $request->only(['search', 'category', 'min_price', 'max_price', 'sort']),
        ]);
    }
}
