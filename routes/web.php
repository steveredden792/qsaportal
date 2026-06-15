<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('/catalogue/far', fn () => abort(404))->name('catalogue.far');
Route::get('/catalogue/ppr', fn () => abort(404))->name('catalogue.ppr');
Route::get('/catalogue/pmr', fn () => abort(404))->name('catalogue.pmr');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
