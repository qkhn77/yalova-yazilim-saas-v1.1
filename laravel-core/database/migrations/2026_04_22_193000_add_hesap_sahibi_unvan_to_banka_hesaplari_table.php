<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banka_hesaplari')) {
            return;
        }

        if (! Schema::hasColumn('banka_hesaplari', 'hesap_sahibi_unvan')) {
            Schema::table('banka_hesaplari', function (Blueprint $table): void {
                $table->string('hesap_sahibi_unvan', 191)->nullable()->after('ad');
            });
        }

        if (! Schema::hasTable('ecommerce_odeme_yontemleri')) {
            return;
        }

        $odemeYontemleri = DB::table('ecommerce_odeme_yontemleri')
            ->where('saglayici', 'havale_eft')
            ->get(['saglayici_ayarlar']);

        foreach ($odemeYontemleri as $odemeYontemi) {
            $ayarlar = json_decode((string) ($odemeYontemi->saglayici_ayarlar ?? ''), true);
            if (! is_array($ayarlar)) {
                continue;
            }

            $bankaHesapId = (int) ($ayarlar['banka_hesap_id'] ?? 0);
            $hesapSahibi = trim((string) ($ayarlar['hesap_sahibi_unvan'] ?? ''));
            if ($bankaHesapId < 1 || $hesapSahibi === '') {
                continue;
            }

            DB::table('banka_hesaplari')
                ->where('id', $bankaHesapId)
                ->where(function ($query): void {
                    $query->whereNull('hesap_sahibi_unvan')
                        ->orWhere('hesap_sahibi_unvan', '');
                })
                ->update(['hesap_sahibi_unvan' => $hesapSahibi]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('banka_hesaplari') || ! Schema::hasColumn('banka_hesaplari', 'hesap_sahibi_unvan')) {
            return;
        }

        Schema::table('banka_hesaplari', function (Blueprint $table): void {
            $table->dropColumn('hesap_sahibi_unvan');
        });
    }
};
