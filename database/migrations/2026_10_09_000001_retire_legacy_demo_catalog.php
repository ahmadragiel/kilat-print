<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan katalog demo lama (banner, spanduk, brosur, flyer, poster, stiker,
 * kartu nama, undangan, nota, kop surat) yang tidak memiliki dasar pada laporan
 * Kerja Praktek Kilat Print.
 *
 * Data transaksi lama aman karena yang diubah hanya `status` dan `deleted_at`
 * (soft delete): baris order_items, production_orders, dan designs tetap utuh.
 * Database demo tetap bisa dibangun ulang dengan `php artisan migrate:fresh --seed`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $legacyProductSlugs = [
        'banner',
        'spanduk',
        'brosur',
        'flyer',
        'poster',
        'stiker',
        'kartu-nama',
        'undangan',
        'nota',
        'kop-surat',
    ];

    /** @var list<string> */
    private array $legacyCategorySlugs = [
        'banner',
        'brochure-flyer',
        'poster-sticker',
        'stationery',
        'invitation-event',
    ];

    /** @var list<string> */
    private array $legacyMaterialSlugs = [
        'flexi-280gr',
        'flexi-340gr',
        'art-paper-150gr',
        'cotton-premium',
    ];

    /** @var list<string> */
    private array $legacyFinishingSlugs = [
        'mata-ayam',
    ];

    public function up(): void
    {
        $this->retire('products', 'slug', $this->legacyProductSlugs);
        $this->retire('categories', 'slug', $this->legacyCategorySlugs);
        $this->retire('materials', 'slug', $this->legacyMaterialSlugs);
        $this->retire('finishings', 'slug', $this->legacyFinishingSlugs);
    }

    public function down(): void
    {
        $this->restore('products', 'slug', $this->legacyProductSlugs);
        $this->restore('categories', 'slug', $this->legacyCategorySlugs);
        $this->restore('materials', 'slug', $this->legacyMaterialSlugs);
        $this->restore('finishings', 'slug', $this->legacyFinishingSlugs);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function retire(string $table, string $column, array $slugs): void
    {
        DB::table($table)
            ->whereIn($column, $slugs)
            ->where(function ($query): void {
                $query->whereNull('deleted_at')->orWhere('deleted_at', '<>', '');
            })
            ->update(['status' => 'inactive', 'deleted_at' => now()]);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function restore(string $table, string $column, array $slugs): void
    {
        DB::table($table)
            ->whereIn($column, $slugs)
            ->update(['status' => 'active', 'deleted_at' => null]);
    }
};