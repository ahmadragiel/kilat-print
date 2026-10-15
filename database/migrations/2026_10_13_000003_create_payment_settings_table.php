<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the payment gateway credentials an admin enters from the dashboard.
 *
 * The key columns are encrypted by the model cast before they reach the
 * database, so a raw database dump never exposes a usable credential. Values
 * entered here take precedence over the environment variables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 40)->default('midtrans');
            $table->text('server_key')->nullable();
            $table->text('client_key')->nullable();
            $table->text('merchant_id')->nullable();
            $table->boolean('sandbox')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};