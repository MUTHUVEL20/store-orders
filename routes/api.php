<?php

use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\LowStockProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Registered under the /api prefix by bootstrap/app.php. The brief specifies no authentication.
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/low-stock', LowStockProductController::class)->name('products.low-stock');
Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
Route::get('customers/orders', [CustomerOrderController::class, 'index'])->name('customers.orders');
