<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/catalogue/far', \App\Livewire\FarCatalogue::class)->name('catalogue.far');
Route::get('/catalogue/ppr', \App\Livewire\PprCatalogue::class)->name('catalogue.ppr');
Route::get('/catalogue/pmr', \App\Livewire\PmrCatalogue::class)->name('catalogue.pmr');

Route::get('/reports/{report:slug}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show');

Route::get('/assets/{asset}/download', [\App\Http\Controllers\DownloadController::class, 'show'])
    ->middleware('auth')
    ->name('assets.download');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
