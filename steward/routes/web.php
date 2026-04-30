<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', fn () => view('pages.dashboard'))->name('dashboard');
    Route::get('/transactions', fn () => view('pages.transactions'))->name('transactions');
    Route::get('/budgets', fn () => view('pages.budgets'))->name('budgets');
    Route::get('/categories', fn () => view('pages.categories'))->name('categories');
    Route::get('/accounts', fn () => view('pages.accounts'))->name('accounts');
    Route::get('/summaries', fn () => view('pages.summaries'))->name('summaries');
    Route::get('/chat', fn () => view('pages.chat'))->name('chat');
    Route::get('/settings', fn () => view('pages.settings'))->name('settings');
});

require __DIR__.'/auth.php';
