<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_forms', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->string('borrower_name');
            $table->string('borrower_phone');
            $table->string('borrower_citizen_id_number')->nullable();
            $table->string('borrower_role', 50);
            $table->enum('method', ['BORROW', 'RENT']);
            $table->dateTime('due_date')->nullable();
            $table->unsignedInteger('rental_days')->default(0);
            $table->decimal('total_rental_amount', 15, 2)->default(0);
            $table->decimal('total_item_price_amount', 15, 2)->default(0);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['DEPOSIT_PENDING', 'BORROWING', 'RETURNED', 'CANCELED'])
                ->default('DEPOSIT_PENDING');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['method', 'status']);
            $table->index('borrower_phone');
            $table->index('due_date');
        });

        Schema::create('loan_form_items', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('loan_form_code')->index();
            $table->string('sku')->index();
            $table->string('loan_item_name');
            $table->decimal('rental_price_per_day', 15, 2)->default(0);
            $table->decimal('item_price', 15, 2)->default(0);
            $table->foreignId('inventory_id')->nullable()->constrained('inventory')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipment_props')->nullOnDelete();
            $table->string('item_type', 50)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('size', 20)->nullable();
            $table->boolean('is_returned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['loan_form_code', 'sku']);
        });

        Schema::create('return_forms', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->string('loan_form_code')->index();
            $table->string('returnee_name');
            $table->string('returnee_phone');
            $table->string('returnee_citizen_id_number')->nullable();
            $table->text('remark')->nullable();
            $table->string('returnee_role', 50)->nullable();
            $table->enum('method', ['BORROW', 'RENT'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['RETURNED', 'INSPECTED', 'COMPLETED', 'CANCELED'])
                ->default('INSPECTED');
            $table->timestamps();

            $table->index(['method', 'status']);
        });

        Schema::create('return_form_items', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('return_form_code')->index();
            $table->string('sku')->index();
            $table->string('return_item_name');
            $table->decimal('rental_price_per_day', 15, 2)->default(0);
            $table->string('condition_on_return', 50)->default('GOOD');
            $table->foreignId('inventory_id')->nullable()->constrained('inventory')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('equipment_props')->nullOnDelete();
            $table->string('item_type', 50)->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('size', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['return_form_code', 'sku']);
        });

        Schema::create('penalty_forms', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->string('loan_form_code')->nullable()->index();
            $table->string('return_form_code')->nullable()->index();
            $table->text('reason');
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['ISSUED', 'PAID', 'CANCELED'])->default('ISSUED');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('code')->unique();
            $table->string('loan_form_code')->nullable()->index();
            $table->string('return_form_code')->nullable()->index();
            $table->string('penalty_form_code')->nullable()->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('payment_amount', 15, 2)->default(0);
            $table->decimal('rental_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->enum('payment_method', PaymentMethod::values())->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('payer_citizen_id_number')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['ISSUED', 'PAID', 'CANCELED'])->default('ISSUED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('penalty_forms');
        Schema::dropIfExists('return_form_items');
        Schema::dropIfExists('return_forms');
        Schema::dropIfExists('loan_form_items');
        Schema::dropIfExists('loan_forms');
    }
};
