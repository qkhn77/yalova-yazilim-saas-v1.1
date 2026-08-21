<?php

use App\Models\Rol;
use App\Models\Yetki;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $yetki = Yetki::query()->updateOrCreate(
            ['kod' => 'firma_yetki_yonetimi.yonet'],
            [
                'ad' => 'Firma Yetki Yönetimi',
                'modul_kodu' => 'kullanici',
                'eylem' => 'yonet',
            ]
        );

        $roller = Rol::query()
            ->where(function ($query): void {
                $query->whereIn('kod', ['firma_sahibi', 'firma_yoneticisi'])
                    ->orWhere('kod', 'like', 'firma_yoneticisi_%');
            })
            ->get();

        foreach ($roller as $rol) {
            $rol->yetkiler()->syncWithoutDetaching([$yetki->id]);
        }
    }

    public function down(): void
    {
        $yetki = Yetki::query()->where('kod', 'firma_yetki_yonetimi.yonet')->first();
        if (! $yetki) {
            return;
        }

        foreach (Rol::query()->get() as $rol) {
            $rol->yetkiler()->detach($yetki->id);
        }

        $yetki->delete();
    }
};
