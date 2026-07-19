<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/catalogue/pir', \App\Livewire\PirCatalogue::class)->name('catalogue.pir');
    Route::get('/reports/{report:slug}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show');
    Route::get('/basket', \App\Livewire\BasketPage::class)->name('basket.show');
    Route::post('/checkout', [\App\Http\Controllers\CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/success/{order}', [\App\Http\Controllers\CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/my-reports', \App\Livewire\MyReports::class)->name('my-reports');
});

Route::get('/assets/{asset}/download', [\App\Http\Controllers\DownloadController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('assets.download');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::post('/webhooks/stripe', \App\Http\Controllers\StripeWebhookController::class)->name('webhooks.stripe');

require __DIR__.'/auth.php';
