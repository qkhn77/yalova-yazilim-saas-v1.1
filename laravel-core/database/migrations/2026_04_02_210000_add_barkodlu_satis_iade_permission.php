<?php

use App\Models\Rol;
use App\Models\Yetki;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $yetki = Yetki::query()->updateOrCreate(
            ['kod' => 'barkodlu_satis.iade'],
            [
                'ad' => 'Barkodlu Satis Iade',
                'modul_kodu' => 'barkodlu_satis',
                'eylem' => 'guncelle',
            ]
        );

        $rolKodlari = ['firma_sahibi', 'firma_yoneticisi', 'muhasebe_personeli', 'satis_personeli'];
        foreach ($rolKodlari as $rolKodu) {
            $rol = Rol::query()->where('kod', $rolKodu)->first();
            if (! $rol) {
                continue;
            }

            $rol->yetkiler()->syncWithoutDetaching([(int) $yetki->id]);
        }
    }

    public function down(): void
    {
        $yetki = Yetki::query()->where('kod', 'barkodlu_satis.iade')->first();
        if (! $yetki) {
            return;
        }

        foreach (Rol::query()->get() as $rol) {
            $rol->yetkiler()->detach((int) $yetki->id);
        }

        $yetki->delete();
    }
};

