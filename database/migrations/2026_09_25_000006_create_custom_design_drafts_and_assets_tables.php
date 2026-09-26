<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_design_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->json('specification')->nullable();
            $table->json('design')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'completed'])->default('draft')->index();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('product_id');
            $table->index('updated_at');
        });

        Schema::create('custom_design_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // foreignUuid() is required here: plain uuid()->constrained() is a no-op.
            $table->foreignUuid('draft_id')->constrained('custom_design_drafts')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['draft_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_design_assets');
        Schema::dropIfExists('custom_design_drafts');
    }
};
