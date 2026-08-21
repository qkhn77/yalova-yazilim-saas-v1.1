<?php

use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teklif_baski_sablonlari')) {
            return;
        }

        $hazirSablon = app(TeklifBaskiSablonuServisi::class)->hazirSablonlar()[0] ?? null;

        if (! is_array($hazirSablon)) {
            return;
        }

        TeklifBaskiSablonu::query()
            ->where('kod', 'yalova-bilgisayar-teklif-formu-a4')
            ->update([
                'ad' => $hazirSablon['ad'],
                'sayfa_tipi' => $hazirSablon['sayfa_tipi'],
                'sablon_html' => $hazirSablon['sablon_html'],
                'sablon_css' => $hazirSablon['sablon_css'],
                'aktif' => true,
                'updated_at' => now(),
            ]);

    }

    public function down(): void
    {
        // Şablon içeriği uygulamanın kanonik varsayılanıdır; geri alma işlemi veri kaybı
        // oluşturabileceğinden bilerek tersine çevrilmez.
    }
};
