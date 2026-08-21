<?php

use App\Models\Rol;
use App\Models\Yetki;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<int, array{kod:string, ad:string, eylem:string}>
     */
    private array $yetkiler = [
        ['kod' => 'barkodlu_satis.fiyat_guncelle', 'ad' => 'Barkodlu Satis Fiyat Guncelle', 'eylem' => 'guncelle'],
        ['kod' => 'barkodlu_satis.iskonto_uygula', 'ad' => 'Barkodlu Satis Iskonto Uygula', 'eylem' => 'guncelle'],
    ];

    public function up(): void
    {
        foreach ($this->yetkiler as $satir) {
            Yetki::query()->updateOrCreate(
                ['kod' => $satir['kod']],
                [
                    'ad' => $satir['ad'],
                    'modul_kodu' => 'barkodlu_satis',
                    'eylem' => $satir['eylem'],
                ]
            );
        }

        $rolYetkiHaritasi = [
            'firma_sahibi' => ['barkodlu_satis.fiyat_guncelle', 'barkodlu_satis.iskonto_uygula'],
            'firma_yoneticisi' => ['barkodlu_satis.fiyat_guncelle', 'barkodlu_satis.iskonto_uygula'],
            'muhasebe_personeli' => ['barkodlu_satis.fiyat_guncelle', 'barkodlu_satis.iskonto_uygula'],
            'satis_personeli' => ['barkodlu_satis.iskonto_uygula'],
        ];

        foreach ($rolYetkiHaritasi as $rolKodu => $kodlar) {
            $rol = Rol::query()->where('kod', $rolKodu)->first();
            if (! $rol) {
                continue;
            }

            $ids = Yetki::query()->whereIn('kod', $kodlar)->pluck('id')->all();
            if (! empty($ids)) {
                $rol->yetkiler()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        $yetkiKodlari = array_map(fn (array $satir): string => $satir['kod'], $this->yetkiler);
        $yetkiIdleri = Yetki::query()->whereIn('kod', $yetkiKodlari)->pluck('id')->all();

        if (! empty($yetkiIdleri)) {
            foreach (Rol::query()->get() as $rol) {
                $rol->yetkiler()->detach($yetkiIdleri);
            }
        }

        Yetki::query()->whereIn('kod', $yetkiKodlari)->delete();
    }
};

