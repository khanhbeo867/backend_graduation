<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\LoanFormController;
use App\Http\Controllers\Api\ReturnFormController;
use App\Http\Controllers\Api\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::get('statistics', [StatisticsController::class, 'index']);

    Route::prefix('loan-forms')->controller(LoanFormController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/items', 'addItems');
        Route::post('/{id}/confirm-deposit', 'confirmDeposit');
        Route::post('/{id}/checkout', 'checkout');
        Route::post('/{id}/cancel', 'cancel');
    });

    Route::prefix('loan-form-items')->controller(LoanFormController::class)->group(function (): void {
        Route::patch('/{id}', 'updateItem');
        Route::delete('/{id}', 'destroyItem');
    });

    Route::prefix('return-forms')->controller(ReturnFormController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::patch('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}/items', 'addItems');
        Route::post('/{id}/inspect', 'inspect');
        Route::post('/{id}/complete', 'complete');
    });

    Route::prefix('return-form-items')->controller(ReturnFormController::class)->group(function (): void {
        Route::patch('/{id}', 'updateItem');
        Route::delete('/{id}', 'destroyItem');
    });

    Route::prefix('penalty-forms')->controller(BillingController::class)->group(function (): void {
        Route::get('/', 'penaltyIndex');
        Route::post('/', 'penaltyStore');
        Route::get('/{id}', 'penaltyShow');
        Route::patch('/{id}', 'penaltyUpdate');
        Route::delete('/{id}', 'penaltyDestroy');
        Route::post('/{id}/issue', 'penaltyIssue');
        Route::post('/{id}/pay', 'penaltyPay');
    });

    Route::prefix('invoices')->controller(BillingController::class)->group(function (): void {
        Route::get('/', 'invoiceIndex');
        Route::post('/', 'invoiceStore');
        Route::get('/{id}', 'invoiceShow');
        Route::patch('/{id}', 'invoiceUpdate');
        Route::delete('/{id}', 'invoiceDestroy');
        Route::post('/{id}/issue', 'invoiceIssue');
        Route::post('/{id}/pay', 'invoicePay');
    });
});
