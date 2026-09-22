<?php

use App\Http\Controllers\Api\V1\Admin\AdminController as ApiAdminController;
use App\Http\Controllers\Api\V1\Admin\AuthController as ApiAdminAuthController;
use App\Http\Controllers\Api\V1\AuthController as ApiAuthController;
use App\Http\Controllers\Api\V1\CatalogController as ApiCatalogController;
use App\Http\Controllers\Api\V1\ConversationController as ApiConversationController;
use App\Http\Controllers\Api\V1\NotificationController as ApiNotificationController;
use App\Http\Controllers\Api\V1\Shop\ProductController as ApiShopProductController;
use App\Http\Controllers\Api\V1\Shop\ShopController as ApiShopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    /*
    | Public
    */
    Route::post('/auth/otp/request', [ApiAuthController::class, 'requestOtp'])->name('api.auth.otp.request');
    Route::post('/auth/otp/verify', [ApiAuthController::class, 'verifyOtp'])->name('api.auth.otp.verify');

    Route::get('/categories', [ApiCatalogController::class, 'categories']);
    Route::get('/categories/{category}/subcategories', [ApiCatalogController::class, 'subcategories']);
    Route::get('/colors', [ApiCatalogController::class, 'colors']);
    Route::get('/units', [ApiCatalogController::class, 'units']);

    Route::get('/products', [ApiCatalogController::class, 'products']);
    Route::get('/products/{product}', [ApiCatalogController::class, 'product']);
    Route::get('/shops/{shop}', [ApiCatalogController::class, 'shop']);

    /*
    | Authenticated users
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [ApiAuthController::class, 'logout']);
        Route::get('/me', [ApiAuthController::class, 'me']);

        // Shop onboarding and status remain reachable for restricted accounts.
        Route::get('/shop/name-availability', [ApiShopController::class, 'nameAvailability']);
        Route::post('/shop/registration', [ApiShopController::class, 'register']);
        Route::get('/shop/status', [ApiShopController::class, 'status']);

        Route::post('/client/conversations', [ApiConversationController::class, 'store']);
        Route::get('/client/conversations', [ApiConversationController::class, 'index']);

        Route::get('/conversations/{conversation}/messages', [ApiConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/messages', [ApiConversationController::class, 'send']);
        Route::patch('/conversations/{conversation}/read', [ApiConversationController::class, 'read']);

        Route::get('/notifications', [ApiNotificationController::class, 'index']);
        Route::patch('/notifications/{notification}/read', [ApiNotificationController::class, 'read']);
        Route::post('/notifications/read-all', [ApiNotificationController::class, 'readAll']);

        // Active shop only.
        Route::middleware('shop.active')->group(function () {
            Route::get('/shop/dashboard', [ApiShopController::class, 'dashboard']);
            Route::get('/shop/profile', [ApiShopController::class, 'profile']);
            Route::patch('/shop/profile', [ApiShopController::class, 'updateProfile']);

            Route::get('/shop/conversations', [ApiConversationController::class, 'index']);

            Route::get('/shop/products', [ApiShopProductController::class, 'index']);
            Route::post('/shop/products', [ApiShopProductController::class, 'store']);
            Route::get('/shop/products/{product}', [ApiShopProductController::class, 'show']);
            Route::patch('/shop/products/{product}', [ApiShopProductController::class, 'update']);
            Route::delete('/shop/products/{product}', [ApiShopProductController::class, 'destroy']);
            Route::post('/shop/products/{product}/images', [ApiShopProductController::class, 'uploadImages']);
            Route::patch('/shop/products/{product}/images/order', [ApiShopProductController::class, 'reorderImages']);
            Route::delete('/shop/products/{product}/images/{image}', [ApiShopProductController::class, 'deleteImage']);
        });
    });

    /*
    | Admin
    */
    Route::post('/admin/auth/login', [ApiAdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'api.admin'])->group(function () {
        Route::post('/admin/auth/logout', [ApiAdminAuthController::class, 'logout']);

        Route::get('/admin/dashboard', [ApiAdminController::class, 'dashboard']);

        Route::get('/admin/shops', [ApiAdminController::class, 'shops']);
        Route::get('/admin/shops/{shop}', [ApiAdminController::class, 'shop']);
        Route::patch('/admin/shops/{shop}', [ApiAdminController::class, 'updateShop']);
        Route::patch('/admin/shops/{shop}/status', [ApiAdminController::class, 'shopStatus']);
        Route::delete('/admin/shops/{shop}', [ApiAdminController::class, 'deleteShop']);
        Route::post('/admin/shop-invitations', [ApiAdminController::class, 'storeInvitation']);

        Route::get('/admin/categories', [ApiAdminController::class, 'categories']);
        Route::post('/admin/categories', [ApiAdminController::class, 'storeCategory']);
        Route::patch('/admin/categories/{category}', [ApiAdminController::class, 'updateCategory']);
        Route::delete('/admin/categories/{category}', [ApiAdminController::class, 'deleteCategory']);
        Route::post('/admin/categories/{category}/subcategories', [ApiAdminController::class, 'storeSubcategory']);
        Route::patch('/admin/subcategories/{subcategory}', [ApiAdminController::class, 'updateSubcategory']);
        Route::delete('/admin/subcategories/{subcategory}', [ApiAdminController::class, 'deleteSubcategory']);
    });
});
