<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_mesaj_sablonlari') || ! Schema::hasTable('firmalar')) {
            return;
        }

        $sablon = implode("\n\n", [
            'Merhaba Sayin {cari_ad},',
            '{fis_no} numarali servis kaydiniza ait cihaziniza uygulanan islemler tamamlanmistir ve cihaziniz teslime hazirdir.',
            "Servis Ozeti:\n• Cari: {cari_ad}\n• Telefon: {cari_tel}\n• Cihaz: {cihaz}\n• Marka/Model: {marka_model}\n• Ariza Bilgisi: {ariza_bilgisi}\n• Musteriye Gorunen Not: {musteriye_gorunen_not}\n• Stok Kartlari: {stok_kartlari}",
            'Cihazinizi uygun oldugunuz ilk zamanda servis noktamizdan teslim alabilirsiniz. Teslim oncesi bilgi vermeniz islemlerinizi hizlandiracaktir.',
            "Saygilarimizla,\nYalova Bilgisayar Teknik Servis\n0 (226) 352 07 24",
        ]);

        $firmaIdler = DB::table('firmalar')->pluck('id');
        foreach ($firmaIdler as $firmaId) {
            DB::table('teknik_servis_mesaj_sablonlari')->updateOrInsert(
                [
                    'firma_id' => (int) $firmaId,
                    'kanal' => 'whatsapp',
                    'kod' => 'teslim_bekleyen_mesaji',
                ],
                [
                    'ad' => 'Teslim Bekleyen Mesaji',
                    'mesaj' => $sablon,
                    'aktif' => true,
                    'siralama' => 20,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_mesaj_sablonlari')) {
            return;
        }

        DB::table('teknik_servis_mesaj_sablonlari')
            ->where('kanal', 'whatsapp')
            ->where('kod', 'teslim_bekleyen_mesaji')
            ->delete();
    }
};
