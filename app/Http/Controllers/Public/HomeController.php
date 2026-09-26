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
        return view('home', [
            'categories' => Category::query()->where('status', 'active')->withCount(['products' => fn ($query) => $query->where('status', 'active')->whereHas('priceRules', fn ($price) => $price->where('active', true))])->orderBy('name')->limit(6)->get(),
            'popularProducts' => Product::query()->with(['category', 'priceRules'])->where('status', 'active')->whereHas('priceRules', fn ($query) => $query->where('active', true))->orderByDesc('popularity_count')->latest()->limit(4)->get(),
            'latestProducts' => Product::query()->with(['category', 'priceRules'])->where('status', 'active')->whereHas('priceRules', fn ($query) => $query->where('active', true))->latest()->limit(4)->get(),
        ]);
    }
}
