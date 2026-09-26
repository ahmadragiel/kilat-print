<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->string('path', 500);
            $table->string('extension', 20);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['Pending', 'Approved', 'Revision Required'])->default('Pending')->index();
            $table->text('notes')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'version']);
            $table->index(['order_id', 'status']);
            $table->index('order_item_id');
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->enum('status', [
                'WAITING_PRODUCTION',
                'IN_PRODUCTION',
                'FINISHING',
                'QUALITY_CHECK',
                'READY',
                'SHIPPED',
                'COMPLETED',
                'CANCELLED',
            ])->default('WAITING_PRODUCTION')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('notes')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'status']);
            $table->index(['status', 'deadline']);
        });

        Schema::create('production_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('operators')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'is_current']);
            $table->index(['operator_id', 'assigned_at']);
        });

        Schema::create('production_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'created_at']);
        });

        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('result', ['PASS', 'FAIL', 'REWORK'])->index();
            $table->text('notes')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'checked_at']);
        });

        Schema::create('production_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'uploaded_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('production_photos');
        Schema::dropIfExists('quality_checks');
        Schema::dropIfExists('production_status_histories');
        Schema::dropIfExists('production_assignments');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('design_files');
    }
};
