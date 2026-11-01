<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Stable identifier for the real catalog seeder. Admin edits to the
            // slug or name must not cause a second product to be created.
            $table->string('catalog_key', 80)->nullable()->unique()->after('slug');
            $table->json('variations')->nullable()->after('specifications');
            $table->string('price_display', 20)->default('starting')->after('base_price');
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->string('catalog_key', 100)->nullable()->unique()->after('id');
            $table->string('variant_key', 80)->nullable()->after('name')->index();
            $table->boolean('is_wholesale')->default(false)->after('active')->index();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('variant', 80)->nullable()->after('size');
            $table->string('variant_label', 150)->nullable()->after('variant');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('variant', 80)->nullable()->after('size');
            $table->string('variant_label', 150)->nullable()->after('variant');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['variant', 'variant_label']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['variant', 'variant_label']);
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn(['catalog_key', 'variant_key', 'is_wholesale']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['catalog_key', 'variations', 'price_display']);
        });
    }
};
