<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            if (! Schema::hasColumn('siparisler', 'odeme_yontemi_kodu')) {
                $table->string('odeme_yontemi_kodu', 64)->nullable()->after('durum');
            }

            if (! Schema::hasColumn('siparisler', 'odeme_yontemi_ad')) {
                $table->string('odeme_yontemi_ad', 160)->nullable()->after('odeme_yontemi_kodu');
            }

            if (! Schema::hasColumn('siparisler', 'odeme_provider')) {
                $table->string('odeme_provider', 64)->nullable()->after('odeme_yontemi_ad');
            }

            if (! Schema::hasColumn('siparisler', 'havale_banka_hesap_id')) {
                $table->unsignedBigInteger('havale_banka_hesap_id')->nullable()->after('odeme_provider');
            }

            if (! Schema::hasColumn('siparisler', 'havale_banka_adi')) {
                $table->string('havale_banka_adi', 191)->nullable()->after('havale_banka_hesap_id');
            }

            if (! Schema::hasColumn('siparisler', 'havale_hesap_sahibi')) {
                $table->string('havale_hesap_sahibi', 191)->nullable()->after('havale_banka_adi');
            }

            if (! Schema::hasColumn('siparisler', 'havale_iban')) {
                $table->string('havale_iban', 34)->nullable()->after('havale_hesap_sahibi');
            }

            if (! Schema::hasColumn('siparisler', 'havale_aciklama_notu')) {
                $table->text('havale_aciklama_notu')->nullable()->after('havale_iban');
            }

            if (! Schema::hasColumn('siparisler', 'havale_referans_kodu')) {
                $table->string('havale_referans_kodu', 64)->nullable()->after('havale_aciklama_notu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            foreach ([
                'havale_referans_kodu',
                'havale_aciklama_notu',
                'havale_iban',
                'havale_hesap_sahibi',
                'havale_banka_adi',
                'havale_banka_hesap_id',
                'odeme_provider',
                'odeme_yontemi_ad',
                'odeme_yontemi_kodu',
            ] as $column) {
                if (Schema::hasColumn('siparisler', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
