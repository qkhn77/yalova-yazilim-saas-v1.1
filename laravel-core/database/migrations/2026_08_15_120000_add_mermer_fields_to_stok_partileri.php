<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            return;
        }

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_parcalari', 'blok_no')) {
                $table->string('blok_no', 128)->nullable()->after('parca_kodu');
            }
            if (! Schema::hasColumn('stok_parcalari', 'ocak_tedarikci')) {
                $table->string('ocak_tedarikci', 191)->nullable()->after('blok_no');
            }
            if (! Schema::hasColumn('stok_parcalari', 'kalite_sinifi')) {
                $table->string('kalite_sinifi', 64)->nullable()->after('ocak_tedarikci');
            }
            if (! Schema::hasColumn('stok_parcalari', 'renk_desen')) {
                $table->string('renk_desen', 191)->nullable()->after('kalite_sinifi');
            }
            if (! Schema::hasColumn('stok_parcalari', 'kalinlik_cm')) {
                $table->decimal('kalinlik_cm', 12, 3)->nullable()->after('renk_desen');
            }
            if (! Schema::hasColumn('stok_parcalari', 'metrekare')) {
                $table->decimal('metrekare', 18, 4)->nullable()->after('kalinlik_cm');
            }
            if (! Schema::hasColumn('stok_parcalari', 'plaka_no')) {
                $table->string('plaka_no', 128)->nullable()->after('metrekare');
            }
            if (! Schema::hasColumn('stok_parcalari', 'parca_no')) {
                $table->string('parca_no', 128)->nullable()->after('plaka_no');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            return;
        }

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('stok_parcalari', 'blok_no') ? 'blok_no' : null,
                Schema::hasColumn('stok_parcalari', 'ocak_tedarikci') ? 'ocak_tedarikci' : null,
                Schema::hasColumn('stok_parcalari', 'kalite_sinifi') ? 'kalite_sinifi' : null,
                Schema::hasColumn('stok_parcalari', 'renk_desen') ? 'renk_desen' : null,
                Schema::hasColumn('stok_parcalari', 'kalinlik_cm') ? 'kalinlik_cm' : null,
                Schema::hasColumn('stok_parcalari', 'metrekare') ? 'metrekare' : null,
                Schema::hasColumn('stok_parcalari', 'plaka_no') ? 'plaka_no' : null,
                Schema::hasColumn('stok_parcalari', 'parca_no') ? 'parca_no' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
