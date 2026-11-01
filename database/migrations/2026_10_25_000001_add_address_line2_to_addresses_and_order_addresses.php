<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('address_line2', 255)->nullable()->after('address');
        });

        Schema::table('order_addresses', function (Blueprint $table) {
            $table->string('address_line2', 255)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropColumn('address_line2');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('address_line2');
        });
    }
};
