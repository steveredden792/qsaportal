<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::view('/contact', 'contact')->name('contact');

// Report detail pages are public (like the catalogue); purchasing and downloads still require an account.
Route::get('/reports/{report:slug}', [\App\Http\Controllers\ReportController::class, 'show'])
    ->middleware('ensure-search-access')
    ->name('reports.show');

// The cart is available to guests (kept in the session); an account is created or
// signed into as part of checkout, and the guest cart is merged into it on login.
Route::get('/basket', \App\Livewire\BasketPage::class)->name('basket.show');
Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');

// PIR purchases only need an account, not a verified email. Email verification is a
// FAR requirement (financially sensitive, professional service providers only) and is
// enforced per-asset in DownloadController.
Route::middleware(['auth'])->group(function () {
    Route::get('/checkout/success/{order}', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/my-reports', \App\Livewire\MyReports::class)->name('my-reports');
});

Route::get('/catalogue/pir', \App\Livewire\PirCatalogue::class)
    ->middleware('ensure-search-access')
    ->name('catalogue.pir');

Route::get('/assets/{asset}/download', [\App\Http\Controllers\DownloadController::class, 'show'])
    ->middleware(['auth'])
    ->name('assets.download');

Route::redirect('dashboard', '/my-reports')->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('/webhooks/stripe', \App\Http\Controllers\StripeWebhookController::class)->name('webhooks.stripe');

require __DIR__.'/auth.php';
