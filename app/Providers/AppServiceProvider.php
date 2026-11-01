<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\Category;
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
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\Drivers\NullGateway;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The driver is chosen per request from configuration, so credentials can
        // be rotated without a rebuild. `null` is the safe default: it refuses to
        // create transactions instead of pretending a payment succeeded.
        $this->app->singleton(PaymentSettings::class);

        $this->app->bind(PaymentGateway::class, function (Application $app): PaymentGateway {
            return match ($app->make(PaymentSettings::class)->driver()) {
                'midtrans' => $app->make(MidtransGateway::class),
                default => $app->make(NullGateway::class),
            };
        });
    }

    public function boot(): void
    {
        // Dates are shown in Indonesian regardless of the app locale, which is
        // also used for validation messages and must stay configurable.
        Carbon::setLocale('id');

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(DesignFile::class, DesignFilePolicy::class);
        Gate::policy(ProductionOrder::class, ProductionOrderPolicy::class);
        Gate::policy(CustomDesignDraft::class, CustomDesignDraftPolicy::class);
        Gate::policy(CustomDesignAsset::class, CustomDesignAssetPolicy::class);

        View::composer('components.customer-nav', function ($view): void {
            $view->with('navCategories', Category::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['name', 'slug']));
        });

        // Presentation only: paginate with the Kilat Print brand pagination view
        // instead of the framework default (neutral gray / blue focus rings).
        Paginator::defaultView('pagination.brand');
        Paginator::defaultSimpleView('pagination.brand');

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('uploads', fn (Request $request) => Limit::perHour(30)->by((string) $request->user()?->id));
    }
}
