<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'sku')) {
                $table->string('sku', 128)->nullable()->after('kod');
            }
            if (! Schema::hasColumn('stok_kartlari', 'upc')) {
                $table->string('upc', 32)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('stok_kartlari', 'ean')) {
                $table->string('ean', 32)->nullable()->after('upc');
            }
            if (! Schema::hasColumn('stok_kartlari', 'gtin')) {
                $table->string('gtin', 32)->nullable()->after('ean');
            }
            if (! Schema::hasColumn('stok_kartlari', 'mpn')) {
                $table->string('mpn', 128)->nullable()->after('gtin');
            }
            if (! Schema::hasColumn('stok_kartlari', 'amazon_asin')) {
                $table->string('amazon_asin', 20)->nullable()->after('mpn');
            }
            if (! Schema::hasColumn('stok_kartlari', 'fba_kodu')) {
                $table->string('fba_kodu', 128)->nullable()->after('amazon_asin');
            }

            $table->index(['firma_id', 'sku']);
            $table->index(['firma_id', 'amazon_asin']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            $table->dropIndex(['firma_id', 'sku']);
            $table->dropIndex(['firma_id', 'amazon_asin']);
            foreach (['fba_kodu', 'amazon_asin', 'mpn', 'gtin', 'ean', 'upc', 'sku'] as $column) {
                if (Schema::hasColumn('stok_kartlari', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
