<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\Operator;
use App\Http\Controllers\Public\CatalogController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{product}/price', [ProductController::class, 'price'])->middleware(['auth', 'role:customer', 'throttle:60,1'])->name('products.price');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/dashboard', [Customer\DashboardController::class, '__invoke'])->name('customer.dashboard');
    Route::get('/products/{product}/customize', [ProductController::class, 'customize'])->name('products.customize');

    Route::get('/cart', [Customer\CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [Customer\CartController::class, 'store'])->name('cart.store');
    Route::get('/cart/{item}/customize', [Customer\CartController::class, 'customize'])->name('cart.customize');
    Route::patch('/cart/{item}', [Customer\CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{item}', [Customer\CartController::class, 'destroy'])->name('cart.destroy');

    Route::post('/custom-designs', [Customer\CustomDesignController::class, 'store'])->name('custom-designs.store');
    Route::put('/custom-designs/{draft}', [Customer\CustomDesignController::class, 'update'])->name('custom-designs.update');
    Route::post('/custom-designs/{draft}/assets', [Customer\CustomDesignController::class, 'storeAsset'])->middleware('throttle:uploads')->name('custom-designs.assets.store');

    Route::get('/checkout', [Customer\CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [Customer\CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/orders', [Customer\OrderController::class, 'index'])->name('customer.orders.index');
    Route::get('/orders/{order:number}', [Customer\OrderController::class, 'show'])->name('customer.orders.show');
    Route::post('/orders/{order:number}/payment-proof', [Customer\OrderController::class, 'uploadPayment'])->middleware('throttle:uploads')->name('customer.orders.payment');
    Route::post('/orders/{order:number}/design', [Customer\OrderController::class, 'uploadDesign'])->middleware('throttle:uploads')->name('customer.orders.design');
    Route::get('/orders/{order:number}/designs/{design}/download', [Customer\OrderController::class, 'downloadDesign'])->name('customer.orders.design-download');
    Route::post('/orders/{order:number}/repeat', [Customer\OrderController::class, 'repeat'])->name('customer.orders.repeat');
    Route::post('/orders/{order:number}/cancel', [Customer\OrderController::class, 'cancel'])->name('customer.orders.cancel');

    Route::get('/profile', [Customer\ProfileController::class, 'edit'])->name('customer.profile.edit');
    Route::put('/profile', [Customer\ProfileController::class, 'update'])->name('customer.profile.update');
    Route::get('/addresses', [Customer\AddressController::class, 'index'])->name('customer.addresses.index');
    Route::post('/addresses', [Customer\AddressController::class, 'store'])->name('customer.addresses.store');
    Route::put('/addresses/{address}', [Customer\AddressController::class, 'update'])->name('customer.addresses.update');
    Route::delete('/addresses/{address}', [Customer\AddressController::class, 'destroy'])->name('customer.addresses.destroy');
    Route::post('/addresses/{address}/primary', [Customer\AddressController::class, 'primary'])->name('customer.addresses.primary');

    Route::get('/notifications', [Customer\NotificationController::class, 'index'])->name('customer.notifications.index');
    Route::post('/notifications/read-all', [Customer\NotificationController::class, 'readAll'])->name('customer.notifications.read-all');
    Route::get('/notifications/{notification}', [Customer\NotificationController::class, 'read'])->name('customer.notifications.read');
});

// Custom design assets are streamed through the app on a private disk. This route is
// intentionally outside the `role:customer` group so an admin or the assigned operator
// can inspect a design through CustomDesignAssetPolicy -> CustomDesignDraftPolicy.
Route::get('/custom-designs/assets/{asset}', [Customer\CustomDesignController::class, 'show'])
    ->middleware('auth')
    ->name('custom-designs.show');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [Admin\DashboardController::class, '__invoke'])->name('dashboard');

    foreach (['products', 'categories', 'materials', 'finishings'] as $resourceType) {
        Route::get('/'.$resourceType, [Admin\MasterDataController::class, 'index'])->defaults('resourceType', $resourceType)->name("{$resourceType}.index");
        Route::get('/'.$resourceType.'/create', [Admin\MasterDataController::class, 'create'])->defaults('resourceType', $resourceType)->name("{$resourceType}.create");
        Route::post('/'.$resourceType, [Admin\MasterDataController::class, 'store'])->defaults('resourceType', $resourceType)->name("{$resourceType}.store");
        Route::get('/'.$resourceType.'/{record}/edit', [Admin\MasterDataController::class, 'edit'])->defaults('resourceType', $resourceType)->name("{$resourceType}.edit");
        Route::put('/'.$resourceType.'/{record}', [Admin\MasterDataController::class, 'update'])->defaults('resourceType', $resourceType)->name("{$resourceType}.update");
        Route::delete('/'.$resourceType.'/{record}', [Admin\MasterDataController::class, 'destroy'])->defaults('resourceType', $resourceType)->name("{$resourceType}.destroy");
    }

    Route::get('/prices', [Admin\PriceRuleController::class, 'index'])->name('prices.index');
    Route::get('/prices/create', [Admin\PriceRuleController::class, 'create'])->name('prices.create');
    Route::post('/prices', [Admin\PriceRuleController::class, 'store'])->name('prices.store');
    Route::get('/prices/{priceRule}/edit', [Admin\PriceRuleController::class, 'edit'])->name('prices.edit');
    Route::put('/prices/{priceRule}', [Admin\PriceRuleController::class, 'update'])->name('prices.update');
    Route::delete('/prices/{priceRule}', [Admin\PriceRuleController::class, 'destroy'])->name('prices.destroy');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:number}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order:number}/status', [Admin\OrderController::class, 'status'])->name('orders.status');
    Route::put('/orders/{order:number}/notes', [Admin\OrderController::class, 'note'])->name('orders.notes');

    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{order:number}/proof', [Admin\PaymentController::class, 'proof'])->name('payments.proof');
    Route::post('/payments/{order:number}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('/payments/{order:number}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');

    Route::get('/designs', [Admin\DesignController::class, 'index'])->name('designs.index');
    Route::get('/designs/{design}/download', [Admin\DesignController::class, 'download'])->name('designs.download');
    Route::post('/designs/{design}/approve', [Admin\DesignController::class, 'approve'])->name('designs.approve');
    Route::post('/designs/{design}/revision', [Admin\DesignController::class, 'requestRevision'])->name('designs.revision');
    Route::post('/orders/{order:number}/send-design-review', [Admin\DesignController::class, 'sendToReview'])->name('designs.send-review');

    Route::get('/production', [Admin\ProductionController::class, 'index'])->name('production.index');
    Route::post('/production/{production}/assign', [Admin\ProductionController::class, 'assign'])->name('production.assign');
    Route::post('/production/{production}/status', [Admin\ProductionController::class, 'status'])->name('production.status');

    Route::get('/customers', [Admin\PeopleController::class, 'customers'])->name('customers.index');
    Route::post('/customers/{user}/toggle', [Admin\PeopleController::class, 'toggle'])->whereNumber('user')->name('customers.toggle');
    Route::get('/operators', [Admin\PeopleController::class, 'operators'])->name('operators.index');
    Route::post('/operators/{user}/toggle', [Admin\PeopleController::class, 'toggle'])->whereNumber('user')->name('operators.toggle');

    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/invoices', [Admin\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{order:number}', [Admin\InvoiceController::class, 'show'])->name('invoices.show');
});

Route::middleware(['auth', 'role:operator'])->prefix('operator')->name('operator.')->group(function () {
    Route::get('/dashboard', [Operator\DashboardController::class, '__invoke'])->name('dashboard');
    Route::get('/jobs', [Operator\JobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', [Operator\JobController::class, 'show'])->whereNumber('job')->name('jobs.show');
    Route::post('/jobs/{job}/start', [Operator\JobController::class, 'start'])->whereNumber('job')->name('jobs.start');
    Route::post('/jobs/{job}/finishing', [Operator\JobController::class, 'finishing'])->whereNumber('job')->name('jobs.finishing');
    Route::post('/jobs/{job}/complete', [Operator\JobController::class, 'complete'])->whereNumber('job')->name('jobs.complete');
    Route::post('/jobs/{job}/quality-check', [Operator\JobController::class, 'qualityCheck'])->whereNumber('job')->name('jobs.quality-check');
    Route::post('/jobs/{job}/rework', [Operator\JobController::class, 'qualityCheck'])->whereNumber('job')->name('jobs.rework');
    Route::post('/jobs/{job}/progress', [Operator\JobController::class, 'progress'])->whereNumber('job')->name('jobs.progress');
    Route::post('/jobs/{job}/note', [Operator\JobController::class, 'note'])->whereNumber('job')->name('jobs.note');
    Route::post('/jobs/{job}/photo', [Operator\JobController::class, 'photo'])->whereNumber('job')->name('jobs.photo');
    Route::get('/designs/{design}/download', [Operator\JobController::class, 'design'])->name('designs.download');
    Route::get('/photos/{photo}/download', [Operator\JobController::class, 'downloadPhoto'])->name('photos.download');
});
