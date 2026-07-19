<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/catalogue/pir', \App\Livewire\PirCatalogue::class)->name('catalogue.pir');
    Route::get('/reports/{report:slug}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show');
    Route::get('/basket', \App\Livewire\BasketPage::class)->name('basket.show');
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

require __DIR__.'/auth.php';
