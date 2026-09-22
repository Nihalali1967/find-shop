<?php

use App\Http\Controllers\Web\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Web\Admin\ColorController as AdminColorController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Web\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Web\Admin\TaxonomyController as AdminTaxonomyController;
use App\Http\Controllers\Web\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Web\Client\CatalogController;
use App\Http\Controllers\Web\Client\ChatController as ClientChatController;
use App\Http\Controllers\Web\Client\NotificationController as ClientNotificationController;
use App\Http\Controllers\Web\Shop\AuthController as ShopAuthController;
use App\Http\Controllers\Web\Shop\ChatController as ShopChatController;
use App\Http\Controllers\Web\Shop\ClaimController;
use App\Http\Controllers\Web\Shop\DashboardController as ShopDashboardController;
use App\Http\Controllers\Web\Shop\NotificationController as ShopNotificationController;
use App\Http\Controllers\Web\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Web\Shop\ProductImageController as ShopProductImageController;
use App\Http\Controllers\Web\Shop\ProfileController as ShopProfileController;
use App\Http\Controllers\Web\Shop\RegistrationController as ShopRegistrationController;
use App\Http\Controllers\Web\Shop\StatusController as ShopStatusController;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

Scramble::registerApi('api', ['api_path' => 'api/v1']);

/*
|--------------------------------------------------------------------------
| Client portal
|--------------------------------------------------------------------------
*/
Route::get('/', [CatalogController::class, 'index'])->name('client.home');
Route::get('/products', [CatalogController::class, 'index'])->name('client.products.index');
Route::get('/products/{product}', [CatalogController::class, 'show'])->name('client.products.show');

// Guest-safe: captures a validated product intent, then asks for login.
Route::get('/products/{product}/chat', [ClientChatController::class, 'start'])->name('client.chat.start');

Route::get('/login', [ClientAuthController::class, 'showLogin'])->name('client.login');
Route::post('/login/otp', [ClientAuthController::class, 'requestOtp'])->name('client.otp.request');
Route::get('/login/verify', [ClientAuthController::class, 'showVerify'])->name('client.otp.verify');
Route::post('/login/verify', [ClientAuthController::class, 'verifyOtp'])->name('client.otp.verify.submit');
Route::post('/login/resend', [ClientAuthController::class, 'resendOtp'])->name('client.otp.resend');

Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [ClientAuthController::class, 'logout'])->name('client.logout');

    Route::get('/chat', [ClientChatController::class, 'index'])->name('client.chat.index');
    Route::get('/chat/{conversation}', [ClientChatController::class, 'show'])->name('client.chat.show');
    Route::post('/chat/{conversation}/messages', [ClientChatController::class, 'send'])->name('client.chat.send');
    Route::get('/chat/{conversation}/poll', [ClientChatController::class, 'poll'])->name('client.chat.poll');
    Route::patch('/chat/{conversation}/read', [ClientChatController::class, 'read'])->name('client.chat.read');

    Route::get('/notifications', [ClientNotificationController::class, 'index'])->name('client.notifications.index');
    Route::patch('/notifications/{notification}/read', [ClientNotificationController::class, 'read'])->name('client.notifications.read');
    Route::post('/notifications/read-all', [ClientNotificationController::class, 'readAll'])->name('client.notifications.readAll');
});

/*
|--------------------------------------------------------------------------
| Shop portal
|--------------------------------------------------------------------------
*/
Route::prefix('shop')->name('shop.')->group(function () {
    Route::get('/login', [ShopAuthController::class, 'showLogin'])->name('login');
    Route::post('/login/otp', [ShopAuthController::class, 'requestOtp'])->name('otp.request');
    Route::get('/login/verify', [ShopAuthController::class, 'showVerify'])->name('otp.verify');
    Route::post('/login/verify', [ShopAuthController::class, 'verifyOtp'])->name('otp.verify.submit');
    Route::post('/logout', [ShopAuthController::class, 'logout'])->name('logout');

    // Invitation claim (guest is bounced to shop login first).
    Route::get('/claim/{token}', [ClaimController::class, 'show'])->name('claim.show');

    Route::middleware('auth:web')->group(function () {
        Route::get('/after-login', [ShopAuthController::class, 'afterLogin'])->name('after-login');
        Route::post('/claim', [ClaimController::class, 'claim'])->name('claim.submit');

        // Status screen must stay reachable even when the shop is restricted.
        Route::get('/status', [ShopStatusController::class, 'show'])->name('status');

        Route::get('/register', [ShopRegistrationController::class, 'show'])->name('register');
        Route::post('/register', [ShopRegistrationController::class, 'store'])->name('register.store');
        Route::get('/name-availability', [ShopRegistrationController::class, 'checkName'])->name('name.check');

        Route::middleware('shop.active')->group(function () {
            Route::get('/', [ShopDashboardController::class, 'index'])->name('dashboard');

            Route::get('/profile', [ShopProfileController::class, 'edit'])->name('profile');
            Route::patch('/profile', [ShopProfileController::class, 'update'])->name('profile.update');

            Route::get('/products', [ShopProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [ShopProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ShopProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ShopProductController::class, 'edit'])->name('products.edit');
            Route::patch('/products/{product}', [ShopProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ShopProductController::class, 'destroy'])->name('products.destroy');

            Route::post('/products/{product}/images', [ShopProductImageController::class, 'store'])->name('products.images.store');
            Route::patch('/products/{product}/images/order', [ShopProductImageController::class, 'reorder'])->name('products.images.order');
            Route::delete('/products/{product}/images/{image}', [ShopProductImageController::class, 'destroy'])->name('products.images.destroy');

            Route::get('/notifications', [ShopNotificationController::class, 'index'])->name('notifications.index');
            Route::patch('/notifications/{notification}/read', [ShopNotificationController::class, 'read'])->name('notifications.read');
            Route::post('/notifications/read-all', [ShopNotificationController::class, 'readAll'])->name('notifications.readAll');

            Route::get('/chat', [ShopChatController::class, 'index'])->name('chat.index');
            Route::get('/chat/{conversation}', [ShopChatController::class, 'show'])->name('chat.show');
            Route::post('/chat/{conversation}/messages', [ShopChatController::class, 'send'])->name('chat.send');
            Route::get('/chat/{conversation}/poll', [ShopChatController::class, 'poll'])->name('chat.poll');
            Route::patch('/chat/{conversation}/read', [ShopChatController::class, 'read'])->name('chat.read');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Admin portal
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.submit');

    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
        Route::get('/shops/{shop}', [AdminShopController::class, 'show'])->name('shops.show');
        Route::patch('/shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
        Route::patch('/shops/{shop}/status', [AdminShopController::class, 'status'])->name('shops.status');
        Route::delete('/shops/{shop}', [AdminShopController::class, 'destroy'])->name('shops.destroy');

        Route::get('/invitations', [AdminInvitationController::class, 'index'])->name('invitations.index');
        Route::post('/invitations', [AdminInvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/invitations/{invitation}', [AdminInvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('/taxonomy', [AdminTaxonomyController::class, 'index'])->name('taxonomy.index');
        Route::post('/categories', [AdminTaxonomyController::class, 'storeCategory'])->name('categories.store');
        Route::patch('/categories/{category}', [AdminTaxonomyController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminTaxonomyController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('/categories/{category}/subcategories', [AdminTaxonomyController::class, 'storeSubcategory'])->name('subcategories.store');
        Route::patch('/subcategories/{subcategory}', [AdminTaxonomyController::class, 'updateSubcategory'])->name('subcategories.update');
        Route::delete('/subcategories/{subcategory}', [AdminTaxonomyController::class, 'destroySubcategory'])->name('subcategories.destroy');

        Route::get('/colors', [AdminColorController::class, 'index'])->name('colors.index');
        Route::post('/colors', [AdminColorController::class, 'store'])->name('colors.store');
        Route::patch('/colors/{color}', [AdminColorController::class, 'update'])->name('colors.update');
        Route::delete('/colors/{color}', [AdminColorController::class, 'destroy'])->name('colors.destroy');
    });
});
