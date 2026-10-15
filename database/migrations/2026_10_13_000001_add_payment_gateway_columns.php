<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the provider bookkeeping needed for QRIS and bank transfer.
 *
 * Existing rows keep their proof columns and their history: nothing is dropped
 * so previously uploaded proofs stay readable for auditing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->after('method');
            $table->string('provider_reference', 120)->nullable()->after('provider');
            $table->string('provider_status', 60)->nullable()->after('provider_reference');
            $table->string('channel', 40)->nullable()->after('provider_status');
            $table->text('qr_image')->nullable()->after('channel');
            $table->text('qr_payload')->nullable()->after('qr_image');
            $table->string('virtual_account_bank', 80)->nullable()->after('qr_payload');
            $table->string('virtual_account_number', 120)->nullable()->after('virtual_account_bank');
            $table->timestamp('expires_at')->nullable()->after('virtual_account_number');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->string('failure_reason', 255)->nullable()->after('paid_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['provider', 'provider_reference'], 'payments_provider_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_provider_reference_index');
            $table->dropColumn([
                'provider',
                'provider_reference',
                'provider_status',
                'channel',
                'qr_image',
                'qr_payload',
                'virtual_account_bank',
                'virtual_account_number',
                'expires_at',
                'paid_at',
                'failure_reason',
            ]);
        });
    }
};
