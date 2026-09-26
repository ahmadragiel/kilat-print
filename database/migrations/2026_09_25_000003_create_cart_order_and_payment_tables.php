<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('size', 100)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('finishing_id')->nullable()->constrained('finishings')->nullOnDelete();
            $table->string('color', 100)->nullable();
            $table->string('production_method', 80)->nullable();
            $table->json('custom_parameters')->nullable();
            $table->decimal('price_at_addition', 14, 2);
            $table->string('design_reference', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cart_id', 'product_id']);
            $table->index(['material_id', 'finishing_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->enum('status', [
                'PENDING_PAYMENT',
                'PAYMENT_REVIEW',
                'PAYMENT_CONFIRMED',
                'DESIGN_REVIEW',
                'DESIGN_REVISION',
                'DESIGN_APPROVED',
                'WAITING_PRODUCTION',
                'IN_PRODUCTION',
                'FINISHING',
                'QUALITY_CHECK',
                'READY',
                'SHIPPED',
                'COMPLETED',
                'CANCELLED',
            ])->default('PENDING_PAYMENT')->index();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('shipping_fee', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->enum('shipping_method', ['pickup', 'delivery'])->default('pickup');
            $table->text('internal_notes')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 180);
            $table->string('product_reference', 100)->nullable();
            $table->string('product_slug', 180)->nullable();
            $table->unsignedInteger('quantity');
            $table->string('size', 100)->nullable();
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('material_name', 150)->nullable();
            $table->string('material_reference', 100)->nullable();
            $table->foreignId('finishing_id')->nullable()->constrained('finishings')->nullOnDelete();
            $table->string('finishing_name', 150)->nullable();
            $table->string('finishing_reference', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('production_method', 80)->nullable();
            $table->json('custom_parameters')->nullable();
            $table->json('configuration')->nullable();
            $table->string('design_reference', 500)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->string('recipient', 255);
            $table->string('phone', 30);
            $table->text('address');
            $table->string('district', 100);
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100)->default('Indonesia');
            $table->enum('shipping_method', ['pickup', 'delivery'])->default('pickup');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index(['new_status', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->enum('status', ['UNPAID', 'WAITING_VERIFICATION', 'PAID', 'REJECTED'])->default('UNPAID')->index();
            $table->string('method', 50)->default('BANK_TRANSFER');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('account_name', 150)->nullable();
            $table->string('proof_path', 500)->nullable();
            $table->string('proof_original_filename', 255)->nullable();
            $table->string('proof_extension', 20)->nullable();
            $table->string('proof_mime_type', 150)->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();
            $table->unsignedInteger('proof_version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
