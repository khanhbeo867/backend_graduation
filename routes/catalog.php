<?php

use App\Http\Controllers\Api\CostumeController;
use App\Http\Controllers\Api\EquipmentPropController;
use App\Http\Controllers\Api\ImageGalleryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ItemCategoryController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [ItemCategoryController::class, 'index']);
Route::get('categories/{id}', [ItemCategoryController::class, 'show']);
Route::get('costumes', [CostumeController::class, 'index']);
Route::get('costumes/{id}', [CostumeController::class, 'show']);
Route::get('equipment-props', [EquipmentPropController::class, 'index']);
Route::get('equipment-props/{id}', [EquipmentPropController::class, 'show']);

Route::middleware('auth:api')->group(function (): void {
    Route::post('categories', [ItemCategoryController::class, 'store']);
    Route::patch('categories/{id}', [ItemCategoryController::class, 'update']);
    Route::delete('categories/{id}', [ItemCategoryController::class, 'destroy']);

    Route::prefix('costumes')->controller(CostumeController::class)->group(function (): void {
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('equipment-props')->controller(EquipmentPropController::class)->group(function (): void {
        Route::post('/', 'store');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('images-gallery')->controller(ImageGalleryController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/upload', 'upload');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('warehouses')->controller(WarehouseController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

    Route::prefix('inventory')->controller(InventoryController::class)->group(function (): void {
        Route::get('/costumes', 'costumes');
        Route::get('/props', 'props');
        Route::post('/import', 'import');
        Route::patch('/status/{sku}', 'updateStatus');
        Route::delete('/delete/{sku}', 'destroyBySku');
        Route::get('/conditions', 'conditions');
    });
});
