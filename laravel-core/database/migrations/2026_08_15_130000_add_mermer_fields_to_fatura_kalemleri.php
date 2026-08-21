<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            foreach ([
                'blok_no' => ['string', 128],
                'ocak_tedarikci' => ['string', 191],
                'kalite_sinifi' => ['string', 64],
                'renk_desen' => ['string', 191],
                'plaka_no' => ['string', 128],
                'parca_no' => ['string', 128],
            ] as $column => [$type, $length]) {
                if (! Schema::hasColumn('fatura_kalemleri', $column)) {
                    $table->{$type}($column, $length)->nullable();
                }
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'kalinlik_cm')) {
                $table->decimal('kalinlik_cm', 12, 3)->nullable();
            }
            if (! Schema::hasColumn('fatura_kalemleri', 'metrekare')) {
                $table->decimal('metrekare', 18, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fatura_kalemleri')) {
            return;
        }

        Schema::table('fatura_kalemleri', function (Blueprint $table): void {
            $columns = ['blok_no', 'ocak_tedarikci', 'kalite_sinifi', 'renk_desen', 'kalinlik_cm', 'metrekare', 'plaka_no', 'parca_no'];
            $table->dropColumn(array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn('fatura_kalemleri', $column))));
        });
    }
};
