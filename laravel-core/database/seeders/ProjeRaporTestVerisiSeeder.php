<?php

namespace Database\Seeders;

use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelMaasDonemi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Servisler\MasrafFaturaKayitServisi;
use App\Services\TenantContextService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProjeRaporTestVerisiSeeder extends Seeder
{
    /**
     * Rapor ve masraf ekranları için local/test ortamına idempotent demo verisi ekler.
     * Her masraf kategorisi için ayda 5, maaş döneminde ayda 10 personel kaydı oluşturur.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Bu test verisi seeder\'ı yalnızca local veya testing ortamında çalışır.');

            return;
        }

        $firma = Firma::query()->where('firma_kodu', 'demo-firma')->first()
            ?? Firma::query()->orderBy('id')->first();

        if (! $firma) {
            $this->command?->error('Test verisi için önce en az bir firma oluşturulmalı.');

            return;
        }

        DB::transaction(function () use ($firma): void {
            $kullaniciId = DB::table('firma_kullanicilari')
                ->where('firma_id', $firma->id)
                ->orderBy('id')
                ->value('kullanici_id');

            $projeler = IsletmeProjesi::withoutGlobalScopes()
                ->where('firma_id', $firma->id)
                ->whereIn('durum', [IsletmeProjesi::DURUM_AKTIF, IsletmeProjesi::DURUM_TASLAK])
                ->orderBy('id')
                ->get();

            if ($projeler->isEmpty()) {
                foreach ([
                    ['kod' => 'DEMO-RAPOR-001', 'ad' => 'Demo Şantiye Projesi', 'butce_tutari' => 750000],
                    ['kod' => 'DEMO-RAPOR-002', 'ad' => 'Demo Kamera ve Güvenlik Projesi', 'butce_tutari' => 420000],
                    ['kod' => 'DEMO-RAPOR-003', 'ad' => 'Demo Altyapı Projesi', 'butce_tutari' => 280000],
                ] as $proje) {
                    $projeler->push(IsletmeProjesi::withoutGlobalScopes()->updateOrCreate(
                        ['firma_id' => $firma->id, 'kod' => $proje['kod']],
                        [
                            'ad' => $proje['ad'],
                            'durum' => IsletmeProjesi::DURUM_AKTIF,
                            'baslangic_tarihi' => Carbon::today()->subMonths(6)->startOfMonth()->toDateString(),
                            'butce_tutari' => $proje['butce_tutari'],
                            'para_birimi' => 'TRY',
                            'aciklama' => 'Rapor ekranı test verisi.',
                        ],
                    ));
                }
            }

            MasrafKategorisi::varsayilanlariHazirla((int) $firma->id);
            $kategoriKodlari = ['personel', 'elektrik', 'su', 'arac', 'kira', 'bakim_onarim'];
            $kategoriler = MasrafKategorisi::withoutGlobalScopes()
                ->where('firma_id', $firma->id)
                ->whereIn('kod', $kategoriKodlari)
                ->where('secilir_mi', true)
                ->get()
                ->keyBy('kod');

            $aylar = collect([1, 2, 3])->map(fn (int $ay): Carbon => Carbon::today()->startOfMonth()->subMonths($ay));
            $masrafTarihleri = [3, 9, 15, 21, 27];
            $masrafSayaci = 0;

            foreach ($aylar as $ayIndex => $ay) {
                foreach ($kategoriKodlari as $kategoriIndex => $kategoriKodu) {
                    $kategori = $kategoriler->get($kategoriKodu);

                    if (! $kategori) {
                        continue;
                    }

                    foreach ($masrafTarihleri as $kayitIndex => $gun) {
                        $tarih = $ay->copy()->day(min($gun, $ay->daysInMonth));
                        $tutar = 650 + ($kategoriIndex * 475) + ($kayitIndex * 125) + ($ayIndex * 90);
                        $proje = $projeler[$masrafSayaci % $projeler->count()];
                        $anahtar = sprintf('demo-rapor-masraf-%s-%s-%02d', $tarih->format('Ym'), $kategoriKodu, $kayitIndex + 1);

                        Masraf::withoutGlobalScopes()->updateOrCreate(
                            ['firma_id' => $firma->id, 'idempotency_key' => $anahtar],
                            [
                                'masraf_kategorisi_id' => $kategori->id,
                                'isletme_proje_id' => $proje->id,
                                'tarih' => $tarih->toDateString(),
                                'tutar' => $tutar,
                                'para_birimi' => 'TRY',
                                'aciklama' => 'Demo '.$kategori->ad.' masrafı '.($kayitIndex + 1),
                                'notlar' => 'Rapor ve masraf ekranı test kaydı.',
                                'durum' => Masraf::DURUM_AKTIF,
                                'olusturan_kullanici_id' => $kullaniciId,
                            ],
                        );
                        $masrafSayaci++;
                    }
                }
            }

            $personelListesi = [
                ['Ali Yılmaz', 28500], ['Ayşe Demir', 31000], ['Mehmet Kaya', 33500], ['Zeynep Çelik', 36000],
                ['Burak Şahin', 39000], ['Elif Aydın', 42000], ['Can Öztürk', 44500], ['Derya Arslan', 47000],
                ['Emre Koç', 52000], ['Selin Kılıç', 57500],
            ];
            $personeller = collect();

            foreach ($personelListesi as $index => [$adSoyad, $maas]) {
                $personeller->push(Personel::withoutGlobalScopes()->updateOrCreate(
                    ['firma_id' => $firma->id, 'personel_no' => 'DEMO-MAAS-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                    [
                        'ad_soyad' => $adSoyad,
                        'calisma_tipi' => 'tam_zamanli',
                        'maas_tipi' => 'aylik',
                        'maas_tutari' => $maas,
                        'para_birimi' => 'TRY',
                        'ise_giris_tarihi' => Carbon::today()->subYear()->toDateString(),
                        'durum' => Personel::DURUM_AKTIF,
                        'notlar' => 'Rapor ve maaş ekranı test personeli.',
                    ],
                ));
            }

            foreach ($aylar as $ay) {
                $donem = PersonelMaasDonemi::withoutGlobalScopes()
                    ->where('firma_id', $firma->id)
                    ->whereNull('sube_id')
                    ->where('donem_yil', $ay->year)
                    ->where('donem_ay', $ay->month)
                    ->first() ?? new PersonelMaasDonemi;

                $donem->fill([
                    'firma_id' => $firma->id,
                    'sube_id' => null,
                    'ad' => $ay->format('Y-m').' Demo Maaş Dönemi',
                    'donem_yil' => $ay->year,
                    'donem_ay' => $ay->month,
                    'baslangic_tarihi' => $ay->copy()->startOfMonth()->toDateString(),
                    'bitis_tarihi' => $ay->copy()->endOfMonth()->toDateString(),
                    'durum' => 'onaylandi',
                    'para_birimi' => 'TRY',
                    'aciklama' => 'Rapor ve personel maaşı test dönemi.',
                    'olusturan_id' => $kullaniciId,
                    'onaylayan_id' => $kullaniciId,
                    'onay_at' => $ay->copy()->endOfMonth()->setTime(17, 0),
                ]);
                $donem->save();

                $toplamBrut = 0.0;
                $toplamKesinti = 0.0;
                $toplamNet = 0.0;

                foreach ($personeller as $personel) {
                    $brut = (float) $personel->maas_tutari;
                    $kesinti = round($brut * 0.03, 2);
                    $net = round($brut - $kesinti, 2);
                    $odenen = round($net * 0.8, 2);

                    PersonelMaasHareketi::withoutGlobalScopes()->updateOrCreate(
                        ['firma_id' => $firma->id, 'maas_donemi_id' => $donem->id, 'personel_id' => $personel->id],
                        [
                            'brut_tutar' => $brut,
                            'fazla_mesai_tutari' => 0,
                            'prim_tutari' => 0,
                            'ek_odeme_tutari' => 0,
                            'avans_kesintisi' => 0,
                            'devamsizlik_kesintisi' => 0,
                            'diger_kesinti' => $kesinti,
                            'net_tutar' => $net,
                            'odenen_tutar' => $odenen,
                            'kalan_tutar' => round($net - $odenen, 2),
                            'durum' => 'onaylandi',
                        ],
                    );

                    $toplamBrut += $brut;
                    $toplamKesinti += $kesinti;
                    $toplamNet += $net;
                }

                $donem->forceFill([
                    'toplam_brut' => round($toplamBrut, 2),
                    'toplam_kesinti' => round($toplamKesinti, 2),
                    'toplam_net' => round($toplamNet, 2),
                ])->saveQuietly();
            }

            $entegrasyonKategorisi = $kategoriler->get('elektrik') ?: $kategoriler->first();
            $entegrasyonProjesi = $projeler->first();
            if ($entegrasyonKategorisi && $entegrasyonProjesi) {
                $cari = Cari::withoutGlobalScopes()->updateOrCreate(
                    ['firma_id' => $firma->id, 'kod' => 'DEMO-PROJE-TED'],
                    [
                        'ad' => 'Demo Proje Tedarikçisi',
                        'tur' => CariTuru::Tedarikci->value,
                        'durum' => CariDurumu::Aktif->value,
                        'para_birimi' => 'TRY',
                    ],
                );

                if ($kullaniciId) {
                    Auth::loginUsingId((int) $kullaniciId);
                    app(TenantContextService::class)->firmaAyarla($firma);
                }

                app(MasrafFaturaKayitServisi::class)->kaydet(
                    (int) $firma->id,
                    [
                        'masraf_kategorisi_id' => (int) $entegrasyonKategorisi->id,
                        'isletme_proje_id' => (int) $entegrasyonProjesi->id,
                        'tarih' => Carbon::today()->subDays(12)->toDateString(),
                        'tutar' => '18500.00',
                        'para_birimi' => 'TRY',
                        'aciklama' => 'Demo proje bağlantılı gider faturası',
                        'notlar' => 'Fatura–masraf tekil hareket test kaydı.',
                    ],
                    'yeni',
                    [
                        'fatura_cari_id' => (int) $cari->id,
                        'fatura_vade_tarihi' => Carbon::today()->addDays(15)->toDateString(),
                        'fatura_aciklama' => 'Demo proje bağlantılı gider faturası',
                    ],
                    $kullaniciId ? (int) $kullaniciId : null,
                    'demo-proje-fatura-masraf-001',
                );
            }
        });

        $this->command?->info('3 aylık rapor test verisi hazırlandı: ayda 30 masraf, 10 maaş hareketi ve proje bağlantılı fatura–masraf örneği.');
    }
}
