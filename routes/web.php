<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\PromptController;
use App\Http\Controllers\Dashboard\OverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'home'])->name('home');
Route::get('/prompts', [MarketplaceController::class, 'index'])->name('prompts.index');
Route::get('/categories/{category:slug}', [MarketplaceController::class, 'category'])->name('categories.show');
Route::get('/prompts/{prompt:slug}', [MarketplaceController::class, 'show'])->name('prompts.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/account/library', [AccountController::class, 'library'])->name('account.library');
    Route::get('/account/library/{prompt:slug}', [AccountController::class, 'purchasedPrompt'])->name('account.library.show');
    Route::get('/account/settings', [AccountController::class, 'settings'])->name('account.settings');
    Route::put('/account/settings', [AccountController::class, 'saveSettings'])->name('account.settings.update');
    Route::put('/account/password', [AccountController::class, 'changePassword'])->name('account.password.update');
    Route::post('/account/creator', [AccountController::class, 'becomeCreator'])->name('account.creator');
    Route::get('/account/prompts', [PromptController::class, 'index'])->name('account.prompts');
    Route::post('/account/prompts', [PromptController::class, 'store'])->name('account.prompts.store');
    Route::put('/account/prompts/{prompt}', [PromptController::class, 'update'])->name('account.prompts.update');
    Route::delete('/account/prompts/{prompt}', [PromptController::class, 'destroy'])->name('account.prompts.destroy');
    Route::post('/account/prompts/{prompt}/submit', [PromptController::class, 'submit'])->name('account.prompts.submit');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{prompt}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/checkout', [CartController::class, 'checkout'])->middleware('throttle:10,1')->name('checkout');

    Route::get('/dashboard', [OverviewController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/categories', [CategoryController::class, 'index'])->name('dashboard.categories');
    Route::post('/dashboard/categories', [CategoryController::class, 'store'])->name('dashboard.categories.store');
    Route::put('/dashboard/categories/{category}', [CategoryController::class, 'update'])->name('dashboard.categories.update');
    Route::delete('/dashboard/categories/{category}', [CategoryController::class, 'destroy'])->name('dashboard.categories.destroy');
    Route::get('/dashboard/prompts', [PromptController::class, 'index'])->name('dashboard.prompts');
    Route::post('/dashboard/prompts/{prompt}/moderate', [PromptController::class, 'moderate'])->name('dashboard.prompts.moderate');
});
