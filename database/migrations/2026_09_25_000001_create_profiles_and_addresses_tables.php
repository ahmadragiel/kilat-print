<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('customer_code', 50)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('company', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('employee_code', 50)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('specialization', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('label', 80)->nullable();
            $table->string('recipient', 255);
            $table->string('phone', 30);
            $table->text('address');
            $table->string('district', 100);
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100)->default('Indonesia');
            $table->boolean('is_primary')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('operators');
        Schema::dropIfExists('customers');
    }
};
