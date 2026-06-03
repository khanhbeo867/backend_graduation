<?php

use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable()->unique();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('citizen_id_number')->unique();
            $table->enum('position', Position::values());
            $table->date('hire_date')->nullable();
            $table->enum('work_status', Work::values())->default(Work::ACTIVE->value);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('position');
            $table->index('work_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
