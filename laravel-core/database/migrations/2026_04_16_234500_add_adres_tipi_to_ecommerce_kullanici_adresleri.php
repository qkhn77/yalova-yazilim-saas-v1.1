<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_kullanici_adresleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('ecommerce_kullanici_adresleri', 'adres_tipi')) {
                $table->string('adres_tipi', 20)->default('teslimat')->after('kullanici_id');
                $table->index(['firma_id', 'kullanici_id', 'adres_tipi'], 'ecom_adres_tipi_idx');
            }
        });

        if (! Schema::hasColumn('ecommerce_kullanici_adresleri', 'adres_tipi')) {
            return;
        }

        DB::table('ecommerce_kullanici_adresleri')
            ->whereNull('adres_tipi')
            ->orWhere('adres_tipi', '')
            ->update(['adres_tipi' => 'teslimat']);

        $faturaAdresleri = DB::table('ecommerce_kullanici_adresleri')
            ->where('varsayilan_fatura_mi', true)
            ->where('adres_tipi', 'teslimat')
            ->orderBy('id')
            ->get();

        foreach ($faturaAdresleri as $adres) {
            $faturaVarMi = DB::table('ecommerce_kullanici_adresleri')
                ->where('firma_id', $adres->firma_id)
                ->where('kullanici_id', $adres->kullanici_id)
                ->where('adres_tipi', 'fatura')
                ->exists();

            if ($faturaVarMi) {
                continue;
            }

            $yeniAdres = (array) $adres;
            unset($yeniAdres['id']);
            $yeniAdres['adres_tipi'] = 'fatura';
            $yeniAdres['baslik'] = 'Fatura Adresi';
            $yeniAdres['varsayilan_teslimat_mi'] = false;
            $yeniAdres['varsayilan_fatura_mi'] = true;
            $yeniAdres['created_at'] = now();
            $yeniAdres['updated_at'] = now();

            DB::table('ecommerce_kullanici_adresleri')->insert($yeniAdres);
        }

        DB::table('ecommerce_kullanici_adresleri')
            ->where('adres_tipi', 'teslimat')
            ->update(['varsayilan_fatura_mi' => false]);
    }

    public function down(): void
    {
        Schema::table('ecommerce_kullanici_adresleri', function (Blueprint $table): void {
            if (Schema::hasColumn('ecommerce_kullanici_adresleri', 'adres_tipi')) {
                $table->dropIndex('ecom_adres_tipi_idx');
                $table->dropColumn('adres_tipi');
            }
        });
    }
};
