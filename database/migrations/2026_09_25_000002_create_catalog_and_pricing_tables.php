<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('base_price', 14, 2)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->unsignedInteger('minimum_order')->default(1);
            $table->unsignedInteger('production_days')->default(1);
            $table->unsignedBigInteger('popularity_count')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->enum('pricing_type', ['per_item', 'per_sqm', 'per_meter', 'fixed', 'additional_fee'])->default('per_item');
            $table->decimal('price', 14, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finishings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->enum('pricing_type', ['per_item', 'per_sqm', 'per_meter', 'fixed', 'additional_fee'])->default('per_item');
            $table->decimal('price', 14, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('price_override', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'material_id']);
            $table->index('material_id');
        });

        Schema::create('product_finishings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('finishing_id')->constrained('finishings')->cascadeOnDelete();
            $table->decimal('price_override', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'finishing_id']);
            $table->index('finishing_id');
        });

        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name', 150);
            $table->enum('pricing_type', ['per_item', 'per_sqm', 'per_meter', 'fixed', 'additional_fee']);
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('min_quantity')->default(1);
            $table->boolean('active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'active', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('product_finishings');
        Schema::dropIfExists('product_materials');
        Schema::dropIfExists('finishings');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
