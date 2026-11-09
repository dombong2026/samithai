<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\SavedPromptController;
use App\Http\Controllers\CreatorInsightsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthSecurityController;
use App\Http\Controllers\Dashboard\LegalController;
use App\Http\Middleware\RequireVerifiedEmailWhenEnabled;
use App\Http\Controllers\CartController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\PromptController;
use App\Http\Controllers\Dashboard\OverviewController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\Dashboard\MediaController;
use App\Http\Controllers\Dashboard\ContentController;
use App\Http\Controllers\Dashboard\ContentTranslationController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\Dashboard\FooterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Dashboard\FinanceController;
use App\Http\Controllers\Dashboard\PlatformController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Dashboard\UsersController;
use App\Http\Controllers\Dashboard\AppearanceController;

Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
Route::prefix('{locale}')->where(['locale'=>'en|zh|ko|ja|de|km|vi|th|id|ms|ar|fr|es'])->group(function (): void {
    Route::get('/', fn (string $locale) => app(MarketplaceController::class)->home())->name('locale.home');
    Route::get('/prompts', fn (Request $request, string $locale) => app(MarketplaceController::class)->index($request))->name('locale.prompts.index');
    Route::get('/prompts/{prompt:slug}', fn (string $locale, \App\Models\Prompt $prompt) => app(MarketplaceController::class)->show($prompt))->name('locale.prompts.show');
    Route::get('/categories/{category:slug}', fn (string $locale, \App\Models\Category $category) => app(MarketplaceController::class)->category($category))->name('locale.categories.show');
    Route::get('/pages/{slug}', fn (string $locale, string $slug) => app(PublicContentController::class)->show('page',$slug))->name('locale.pages.show');
    Route::get('/blog', fn (string $locale) => app(PublicContentController::class)->posts())->name('locale.blog.index');
    Route::get('/blog/{slug}', fn (string $locale, string $slug) => app(PublicContentController::class)->show('post',$slug))->name('locale.blog.show');
    Route::get('/policies', fn (string $locale) => app(PublicContentController::class)->policies())->name('locale.policies.index');
    Route::get('/policies/{slug}', fn (string $locale, string $slug) => app(PublicContentController::class)->show('policy',$slug))->name('locale.policies.show');
    Route::get('/support-center', fn (string $locale) => app(SupportController::class)->center())->name('locale.support.center');
    Route::get('/support-center/articles/{slug}', fn (string $locale, string $slug) => app(PublicContentController::class)->show('article',$slug))->name('locale.support.articles.show');
});

Route::get('/legal/{kind}', [LegalController::class,'public'])->name('legal.show');

Route::get('/installer', [InstallerController::class, 'show'])->name('installer.show');
Route::post('/installer', [InstallerController::class, 'install'])->middleware('throttle:4,1')->name('installer.install');

Route::get('/media/{mediaAsset}/view', [MediaController::class, 'serve'])->name('media.serve');

Route::get('/support-center', [SupportController::class, 'center'])->name('support.center');
Route::get('/support-center/articles/{slug}', fn (PublicContentController $controller, string $slug) => $controller->show('article',$slug))->name('support.articles.show');

Route::get('/blog', [PublicContentController::class, 'posts'])->name('blog.index');
Route::get('/blog/{slug}', fn (PublicContentController $controller, string $slug) => $controller->show('post', $slug))->name('blog.show');
Route::get('/pages/{slug}', fn (PublicContentController $controller, string $slug) => $controller->show('page', $slug))->name('pages.show');
Route::get('/policies', [PublicContentController::class, 'policies'])->name('policies.index');
Route::get('/policies/{slug}', fn (PublicContentController $controller, string $slug) => $controller->show('policy', $slug))->name('policies.show');

Route::get('/', [MarketplaceController::class, 'home'])->name('home');
Route::get('/prompts', [MarketplaceController::class, 'index'])->name('prompts.index');
Route::get('/categories/{category:slug}', [MarketplaceController::class, 'category'])->name('categories.show');
Route::get('/prompts/{prompt:slug}', [MarketplaceController::class, 'show'])->name('prompts.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [AuthSecurityController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthSecurityController::class, 'sendReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthSecurityController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthSecurityController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.submit');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [AuthSecurityController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthSecurityController::class, 'verify'])
        ->middleware(['signed','throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthSecurityController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/account/library', [AccountController::class, 'library'])->name('account.library');
    Route::get('/account/saved', [SavedPromptController::class, 'index'])->name('account.saved');
    Route::get('/account/creator/insights', [CreatorInsightsController::class, 'index'])->name('account.creator.insights');
    Route::post('/prompts/{prompt}/save', [SavedPromptController::class, 'store'])->middleware('throttle:30,1')->name('prompts.save');
    Route::delete('/prompts/{prompt}/save', [SavedPromptController::class, 'destroy'])->middleware('throttle:30,1')->name('prompts.unsave');
    Route::get('/account/library/{prompt:slug}', [AccountController::class, 'purchasedPrompt'])->name('account.library.show');
    Route::get('/account/settings', [AccountController::class, 'settings'])->name('account.settings');
    Route::put('/account/settings', [AccountController::class, 'saveSettings'])->name('account.settings.update');
    Route::put('/account/password', [AccountController::class, 'changePassword'])->name('account.password.update');
    Route::post('/account/creator', [AccountController::class, 'becomeCreator'])->middleware(RequireVerifiedEmailWhenEnabled::class)->name('account.creator');
    Route::get('/account/prompts', [PromptController::class, 'index'])->name('account.prompts');
    Route::post('/account/prompts', [PromptController::class, 'store'])->middleware(RequireVerifiedEmailWhenEnabled::class)->name('account.prompts.store');
    Route::put('/account/prompts/{prompt}', [PromptController::class, 'update'])->name('account.prompts.update');
    Route::delete('/account/prompts/{prompt}', [PromptController::class, 'destroy'])->name('account.prompts.destroy');
    Route::post('/account/prompts/{prompt}/submit', [PromptController::class, 'submit'])->middleware(RequireVerifiedEmailWhenEnabled::class)->name('account.prompts.submit');
    Route::get('/account/prompts/{prompt}/reviews', [PromptController::class, 'reviewHistory'])->name('account.prompts.reviews');

    Route::get('/account/orders', [FinanceController::class, 'index'])->name('account.orders');
    Route::get('/dashboard/users', [UsersController::class, 'index'])->name('dashboard.users');
    Route::put('/dashboard/users/{user}/role', [UsersController::class, 'role'])->name('dashboard.users.role');
    Route::get('/dashboard/appearance', [AppearanceController::class, 'index'])->name('dashboard.appearance');
    Route::put('/dashboard/appearance', [AppearanceController::class, 'update'])->name('dashboard.appearance.update');

    Route::get('/dashboard/legal', [LegalController::class,'index'])->name('dashboard.legal');
    Route::post('/dashboard/legal', [LegalController::class,'store'])->name('dashboard.legal.store');
    Route::put('/dashboard/legal/{document}', [LegalController::class,'update'])->name('dashboard.legal.update');
    Route::post('/dashboard/legal/{document}/publish', [LegalController::class,'publish'])->name('dashboard.legal.publish');
    Route::post('/dashboard/legal/{document}/retire', [LegalController::class,'retire'])->name('dashboard.legal.retire');

    Route::get('/dashboard/security', [PlatformController::class, 'security'])->name('dashboard.security');
    Route::get('/dashboard/system', [PlatformController::class, 'system'])->name('dashboard.system');
    Route::get('/dashboard/languages', [PlatformController::class, 'language'])->name('dashboard.languages');

    Route::get('/dashboard/finance', [FinanceController::class, 'index'])->name('dashboard.finance');
    Route::get('/orders/{order}', [FinanceController::class, 'show'])->name('orders.show');

    Route::get('/account/support', [SupportController::class, 'tickets'])->name('account.support');
    Route::get('/dashboard/support', [SupportController::class, 'tickets'])->name('dashboard.support');
    Route::post('/support/tickets', [SupportController::class, 'store'])->middleware('throttle:8,60')->name('support.store');
    Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/tickets/{ticket}/replies', [SupportController::class, 'reply'])->middleware('throttle:30,1')->name('support.reply');
    Route::post('/support/tickets/{ticket}/status', [SupportController::class, 'status'])->name('support.status');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{prompt}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/checkout', [CartController::class, 'checkout'])->middleware('throttle:10,1')->middleware(RequireVerifiedEmailWhenEnabled::class)->name('checkout');

    Route::get('/account/media', [MediaController::class, 'index'])->name('account.media');
    Route::get('/dashboard/media', [MediaController::class, 'index'])->name('dashboard.media');
    Route::post('/media/upload', [MediaController::class, 'upload'])->middleware('throttle:30,1')->middleware(RequireVerifiedEmailWhenEnabled::class)->name('media.upload');
    Route::post('/media/reference', [MediaController::class, 'reference'])->name('media.reference');
    Route::put('/media/{mediaAsset}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{mediaAsset}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('/dashboard/footer', [FooterController::class, 'index'])->name('dashboard.footer');
    Route::post('/dashboard/footer', [FooterController::class, 'store'])->name('dashboard.footer.store');
    Route::put('/dashboard/footer/{column}', [FooterController::class, 'update'])->name('dashboard.footer.update');
    Route::delete('/dashboard/footer/{column}', [FooterController::class, 'destroy'])->name('dashboard.footer.destroy');
    Route::post('/dashboard/footer/{column}/links', [FooterController::class, 'storeLink'])->name('dashboard.footer.links.store');
    Route::put('/dashboard/footer/links/{link}', [FooterController::class, 'updateLink'])->name('dashboard.footer.links.update');
    Route::delete('/dashboard/footer/links/{link}', [FooterController::class, 'destroyLink'])->name('dashboard.footer.links.destroy');

    Route::get('/dashboard/content/{entry}/translations', [ContentTranslationController::class,'index'])->name('dashboard.translations');
    Route::put('/dashboard/content/{entry}/translations/{locale}', [ContentTranslationController::class,'update'])->name('dashboard.translations.update');
    Route::post('/dashboard/content/{entry}/translations/{locale}/review', [ContentTranslationController::class,'review'])->name('dashboard.translations.review');

    Route::get('/dashboard/{section}', [ContentController::class, 'index'])->whereIn('section', ['posts','pages','policies','knowledge'])->name('dashboard.content');
    Route::post('/dashboard/content/{kind}', [ContentController::class, 'store'])->whereIn('kind',['post','page','policy','article'])->name('dashboard.content.store');
    Route::put('/dashboard/content/{entry}', [ContentController::class, 'update'])->name('dashboard.content.update');
    Route::post('/dashboard/content/{entry}/publish', [ContentController::class, 'publish'])->name('dashboard.content.publish');
    Route::delete('/dashboard/content/{entry}', [ContentController::class, 'destroy'])->name('dashboard.content.destroy');
    Route::get('/dashboard/content/{entry}/preview', [ContentController::class, 'preview'])->name('dashboard.content.preview');
    Route::get('/dashboard', [OverviewController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/categories', [CategoryController::class, 'index'])->name('dashboard.categories');
    Route::post('/dashboard/categories', [CategoryController::class, 'store'])->name('dashboard.categories.store');
    Route::put('/dashboard/categories/{category}', [CategoryController::class, 'update'])->name('dashboard.categories.update');
    Route::delete('/dashboard/categories/{category}', [CategoryController::class, 'destroy'])->name('dashboard.categories.destroy');
    Route::get('/dashboard/prompts', [PromptController::class, 'index'])->name('dashboard.prompts');
    Route::post('/dashboard/prompts/{prompt}/moderate', [PromptController::class, 'moderate'])->name('dashboard.prompts.moderate');
});
