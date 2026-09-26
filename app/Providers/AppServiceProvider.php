<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\DesignFile;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Policies\AddressPolicy;
use App\Policies\CustomDesignAssetPolicy;
use App\Policies\CustomDesignDraftPolicy;
use App\Policies\DesignFilePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductionOrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(DesignFile::class, DesignFilePolicy::class);
        Gate::policy(ProductionOrder::class, ProductionOrderPolicy::class);
        Gate::policy(CustomDesignDraft::class, CustomDesignDraftPolicy::class);
        Gate::policy(CustomDesignAsset::class, CustomDesignAssetPolicy::class);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by((string) $request->user()?->id));
    }
}
