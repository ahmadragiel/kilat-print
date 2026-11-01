<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredProducts = Product::query()
            ->with(['category', 'priceRules'])
            ->visible()
            ->where('is_featured', true)
            ->latest()
            ->limit(4)
            ->get();

        return view('home', [
            'categories' => Category::query()->where('status', 'active')->withCount(['products' => fn ($query) => $query->where('status', 'active')->whereHas('priceRules', fn ($price) => $price->where('active', true)->where('is_wholesale', false))])->orderBy('name')->get(),
            'popularProducts' => Product::query()->with(['category', 'priceRules'])->visible()->orderByDesc('popularity_count')->latest()->limit(4)->get(),
            'latestProducts' => Product::query()->with(['category', 'priceRules'])->visible()->latest()->limit(4)->get(),
            'featuredProducts' => $featuredProducts,
        ]);
    }
}
