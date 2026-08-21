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
        ['kod' => 'barkodlu_satis.goruntule', 'ad' => 'Barkodlu Satis Goruntule', 'eylem' => 'goruntule'],
        ['kod' => 'barkodlu_satis.olustur', 'ad' => 'Barkodlu Satis Olustur', 'eylem' => 'olustur'],
        ['kod' => 'barkodlu_satis.guncelle', 'ad' => 'Barkodlu Satis Guncelle', 'eylem' => 'guncelle'],
        ['kod' => 'barkodlu_satis.etiket_yazdir', 'ad' => 'Barkodlu Satis Etiket Yazdir', 'eylem' => 'guncelle'],
        ['kod' => 'barkodlu_satis.iptal', 'ad' => 'Barkodlu Satis Iptal', 'eylem' => 'guncelle'],
    ];

    public function up(): void
    {
        $yetkiIdleri = [];
        foreach ($this->yetkiler as $satir) {
            $yetki = Yetki::query()->updateOrCreate(
                ['kod' => $satir['kod']],
                [
                    'ad' => $satir['ad'],
                    'modul_kodu' => 'barkodlu_satis',
                    'eylem' => $satir['eylem'],
                ]
            );
            $yetkiIdleri[] = (int) $yetki->id;
        }

        $rolYetkiHaritasi = [
            'firma_sahibi' => [
                'barkodlu_satis.goruntule',
                'barkodlu_satis.olustur',
                'barkodlu_satis.guncelle',
                'barkodlu_satis.etiket_yazdir',
                'barkodlu_satis.iptal',
            ],
            'firma_yoneticisi' => [
                'barkodlu_satis.goruntule',
                'barkodlu_satis.olustur',
                'barkodlu_satis.guncelle',
                'barkodlu_satis.etiket_yazdir',
                'barkodlu_satis.iptal',
            ],
            'muhasebe_personeli' => [
                'barkodlu_satis.goruntule',
                'barkodlu_satis.olustur',
                'barkodlu_satis.guncelle',
                'barkodlu_satis.etiket_yazdir',
            ],
            'satis_personeli' => [
                'barkodlu_satis.goruntule',
                'barkodlu_satis.olustur',
                'barkodlu_satis.guncelle',
                'barkodlu_satis.etiket_yazdir',
                'barkodlu_satis.iptal',
            ],
            'depo_personeli' => [
                'barkodlu_satis.goruntule',
                'barkodlu_satis.etiket_yazdir',
            ],
            'goruntuleyici' => [
                'barkodlu_satis.goruntule',
            ],
        ];

        foreach ($rolYetkiHaritasi as $rolKodu => $kodlar) {
            $rol = Rol::query()->where('kod', $rolKodu)->first();
            if (! $rol) {
                continue;
            }

            $ids = Yetki::query()
                ->whereIn('kod', $kodlar)
                ->pluck('id')
                ->all();

            if (! empty($ids)) {
                $rol->yetkiler()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        $yetkiKodlari = array_map(fn (array $satir): string => $satir['kod'], $this->yetkiler);

        $yetkiIdleri = Yetki::query()
            ->whereIn('kod', $yetkiKodlari)
            ->pluck('id')
            ->all();

        if (! empty($yetkiIdleri)) {
            foreach (Rol::query()->get() as $rol) {
                $rol->yetkiler()->detach($yetkiIdleri);
            }
        }

        Yetki::query()->whereIn('kod', $yetkiKodlari)->delete();
    }
};

