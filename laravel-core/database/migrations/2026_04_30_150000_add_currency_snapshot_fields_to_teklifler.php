<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teklifler', function (Blueprint $table): void {
            if (! Schema::hasColumn('teklifler', 'kur_seti')) {
                $table->longText('kur_seti')->nullable()->after('para_birimi');
            }

            if (! Schema::hasColumn('teklifler', 'kur_seti_alindi_at')) {
                $table->timestamp('kur_seti_alindi_at')->nullable()->after('kur_seti');
            }

            if (! Schema::hasColumn('teklifler', 'kur_seti_kaynagi')) {
                $table->string('kur_seti_kaynagi', 64)->nullable()->after('kur_seti_alindi_at');
            }

            if (! Schema::hasColumn('teklifler', 'kur_seti_kur_tipi')) {
                $table->string('kur_seti_kur_tipi', 64)->nullable()->after('kur_seti_kaynagi');
            }
        });

        Schema::table('teklif_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('teklif_kalemleri', 'kaynak_para_birimi')) {
                $table->char('kaynak_para_birimi', 3)->nullable()->after('para_birimi');
            }

            if (! Schema::hasColumn('teklif_kalemleri', 'kaynak_birim_fiyat')) {
                $table->decimal('kaynak_birim_fiyat', 18, 8)->nullable()->after('kaynak_para_birimi');
            }

            if (! Schema::hasColumn('teklif_kalemleri', 'ozel_fiyat_mi')) {
                $table->boolean('ozel_fiyat_mi')->default(false)->after('kaynak_birim_fiyat');
            }

            if (! Schema::hasColumn('teklif_kalemleri', 'fiyat_uyari')) {
                $table->text('fiyat_uyari')->nullable()->after('ozel_fiyat_mi');
            }

            if (! Schema::hasColumn('teklif_kalemleri', 'kaynak_verisi')) {
                $table->longText('kaynak_verisi')->nullable()->after('fiyat_uyari');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teklif_kalemleri', function (Blueprint $table): void {
            foreach (['kaynak_verisi', 'fiyat_uyari', 'ozel_fiyat_mi', 'kaynak_birim_fiyat', 'kaynak_para_birimi'] as $column) {
                if (Schema::hasColumn('teklif_kalemleri', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('teklifler', function (Blueprint $table): void {
            foreach (['kur_seti_kur_tipi', 'kur_seti_kaynagi', 'kur_seti_alindi_at', 'kur_seti'] as $column) {
                if (Schema::hasColumn('teklifler', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
