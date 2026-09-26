<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('front_mockup', 500)->nullable()->after('thumbnail');
            $table->string('back_mockup', 500)->nullable()->after('front_mockup');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignUuid('custom_design_draft_id')
                ->nullable()
                ->after('custom_parameters')
                ->constrained('custom_design_drafts')
                ->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignUuid('custom_design_draft_id')
                ->nullable()
                ->after('configuration')
                ->constrained('custom_design_drafts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_design_draft_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_design_draft_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['front_mockup', 'back_mockup']);
        });
    }
};
