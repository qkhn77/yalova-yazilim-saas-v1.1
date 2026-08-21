<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['stok_olcu.goruntule', 'Stok Ölçüsü Görüntüle', 'goruntule'],
            ['stok_olcu.olustur', 'Stok Ölçüsü Oluştur', 'olustur'],
            ['stok_olcu.guncelle', 'Stok Ölçüsü Güncelle', 'guncelle'],
        ] as [$kod, $ad, $eylem]) {
            DB::table('yetkiler')->updateOrInsert(
                ['kod' => $kod],
                ['ad' => $ad, 'modul_kodu' => 'muhasebe', 'eylem' => $eylem, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $rolId = DB::table('roller')->where('kod', 'firma_yoneticisi')->value('id');
        if (! $rolId) {
            return;
        }

        $yetkiIdleri = DB::table('yetkiler')->whereNull('deleted_at')->pluck('id');
        foreach ($yetkiIdleri as $yetkiId) {
            DB::table('rol_yetkileri')->updateOrInsert(
                ['rol_id' => $rolId, 'yetki_id' => $yetkiId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Permission grants are retained on rollback to avoid removing administrator access.
    }
};
