<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personel_belgeleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('personel_belgeleri', 'duzenleme_tarihi')) {
                $table->date('duzenleme_tarihi')->nullable();
            }

            if (! Schema::hasColumn('personel_belgeleri', 'gecerlilik_tarihi')) {
                $table->date('gecerlilik_tarihi')->nullable();
            }

            if (! Schema::hasColumn('personel_belgeleri', 'uyari_tarihi')) {
                $table->date('uyari_tarihi')->nullable();
            }

            if (! Schema::hasColumn('personel_belgeleri', 'durum')) {
                $table->string('durum', 40)->default('gecerli');
            }
        });

        Schema::table('personel_belgeleri', function (Blueprint $table): void {
            $table->index(['firma_id', 'durum', 'gecerlilik_tarihi'], 'pt_belge_firma_durum_gecerlilik_idx');
            $table->index(['firma_id', 'uyari_tarihi'], 'pt_belge_firma_uyari_idx');
        });
    }

    public function down(): void
    {
        Schema::table('personel_belgeleri', function (Blueprint $table): void {
            $table->dropIndex('pt_belge_firma_durum_gecerlilik_idx');
            $table->dropIndex('pt_belge_firma_uyari_idx');

            if (Schema::hasColumn('personel_belgeleri', 'duzenleme_tarihi')) {
                $table->dropColumn('duzenleme_tarihi');
            }

            if (Schema::hasColumn('personel_belgeleri', 'gecerlilik_tarihi')) {
                $table->dropColumn('gecerlilik_tarihi');
            }

            if (Schema::hasColumn('personel_belgeleri', 'uyari_tarihi')) {
                $table->dropColumn('uyari_tarihi');
            }

            if (Schema::hasColumn('personel_belgeleri', 'durum')) {
                $table->dropColumn('durum');
            }
        });
    }
};
