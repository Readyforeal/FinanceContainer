<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('/transactions', 'pages::transactions')->name('transactions');
    Route::livewire('/budgets', 'pages::budgets')->name('budgets');
    Route::livewire('/categories', 'pages::categories')->name('categories');
    Route::livewire('/accounts', 'pages::accounts')->name('accounts');
    Route::livewire('/summaries', 'pages::summaries')->name('summaries');
    Route::livewire('/goals', 'pages::goals')->name('goals');
    Route::livewire('/chat', 'pages::chat')->name('chat');
    Route::livewire('/settings', 'pages::settings')->name('settings');
});

require __DIR__.'/auth.php';
