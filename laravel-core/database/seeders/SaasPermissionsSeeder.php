<?php

namespace Database\Seeders;

use App\Models\Yetki;
use Illuminate\Database\Seeder;

class SaasPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $kapsamlar = [
            'muhasebe' => ['Muhasebe', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'masraf_takip' => ['Masraf Takibi', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'teknik_servis' => ['Teknik Servis', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'barkodlu_satis' => ['Barkodlu Satis', ['goruntule', 'olustur', 'guncelle']],
            'depo' => ['Depo', ['goruntule', 'olustur', 'guncelle']],
            'restoran' => ['Restoran', ['goruntule', 'olustur', 'guncelle']],
            'proje_yonetimi' => ['Proje Yönetimi', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'personel_takip' => ['Personel Takip', ['goruntule', 'olustur', 'guncelle']],
            'teklif_yonetimi' => ['Teklif Yönetimi', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'e_ticaret' => ['E-ticaret', ['goruntule', 'olustur', 'guncelle']],
            'bt_varlik_yonetimi' => ['BT Varlık Yönetimi', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'web' => ['Web', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'urun' => ['Ürün', ['goruntule', 'olustur', 'guncelle', 'sil']],
            'urun_kategori' => ['Ürün Kategori', ['goruntule', 'olustur', 'guncelle', 'sil']],
            // Sistem / iç alan izinleri (modül aboneliğinden bağımsız, yetkiye bağlı).
            'kullanici' => ['Kullanıcı', ['goruntule', 'olustur', 'guncelle', 'sil', 'yonet']],
            'firma' => ['Firma', ['goruntule', 'guncelle', 'onay']],
            'modul' => ['Modül', ['goruntule', 'yonet']],
            'sekreter' => ['Ajanda ve Görevler', ['goruntule', 'olustur', 'guncelle', 'sil']],
        ];

        foreach ($kapsamlar as $kodOnEk => [$adOnEk, $eylemler]) {
            foreach ($eylemler as $eylem) {
                $kod = "{$kodOnEk}.{$eylem}";
                $ad = "{$adOnEk} ".$this->eylemEtiketi($eylem);

                Yetki::query()->updateOrCreate(
                    ['kod' => $kod],
                    [
                        'ad' => $ad,
                        'modul_kodu' => $kodOnEk,
                        'eylem' => $eylem,
                    ]
                );
            }
        }

        // Urun yonetimi "web" modulu altinda calisir.
        Yetki::query()
            ->where(function ($query): void {
                $query->where('kod', 'like', 'urun.%')
                    ->orWhere('kod', 'like', 'urun_kategori.%');
            })
            ->update(['modul_kodu' => 'web']);

        // POS hesabı: kod `pos.*` — abonelik kontrolü `muhasebe` modülü ile hizalı (modul_kodu = muhasebe).
        $posYetkileri = [
            ['pos.goruntule', 'POS Görüntüle', 'goruntule'],
            ['pos.olustur', 'POS Oluştur', 'olustur'],
            ['pos.guncelle', 'POS Güncelle', 'guncelle'],
            ['pos.sil', 'POS Sil', 'sil'],
        ];
        foreach ($posYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $barkodluSatisYetkileri = [
            ['barkodlu_satis.goruntule', 'Barkodlu Satis Goruntule', 'goruntule'],
            ['barkodlu_satis.olustur', 'Barkodlu Satis Olustur', 'olustur'],
            ['barkodlu_satis.guncelle', 'Barkodlu Satis Guncelle', 'guncelle'],
            ['barkodlu_satis.etiket_yazdir', 'Barkodlu Satis Etiket Yazdir', 'guncelle'],
            ['barkodlu_satis.iptal', 'Barkodlu Satis Iptal', 'guncelle'],
            ['barkodlu_satis.iade', 'Barkodlu Satis Iade', 'guncelle'],
            ['barkodlu_satis.fiyat_guncelle', 'Barkodlu Satis Fiyat Guncelle', 'guncelle'],
            ['barkodlu_satis.iskonto_uygula', 'Barkodlu Satis Iskonto Uygula', 'guncelle'],
            ['barkodlu_satis_ayar.goruntule', 'Barkodlu Satis Ayar Goruntule', 'goruntule'],
            ['barkodlu_satis_ayar.guncelle', 'Barkodlu Satis Ayar Guncelle', 'guncelle'],
        ];
        foreach ($barkodluSatisYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'barkodlu_satis',
                    'eylem' => $eylem,
                ]
            );
        }

        $personelYetkileri = [
            ['personel.goruntule', 'Personel Görüntüle', 'goruntule'],
            ['personel.olustur', 'Personel Oluştur', 'olustur'],
            ['personel.guncelle', 'Personel Güncelle', 'guncelle'],
            ['personel.sil', 'Personel Sil', 'sil'],
            ['personel_tanim.goruntule', 'Personel Tanım Görüntüle', 'goruntule'],
            ['personel_tanim.guncelle', 'Personel Tanım Güncelle', 'guncelle'],
            ['personel_vardiya.goruntule', 'Personel Vardiya Görüntüle', 'goruntule'],
            ['personel_vardiya.duzenle', 'Personel Vardiya Düzenle', 'guncelle'],
            ['personel_giris_cikis.goruntule', 'Personel Giriş Çıkış Görüntüle', 'goruntule'],
            ['personel_giris_cikis.duzenle', 'Personel Giriş Çıkış Düzenle', 'guncelle'],
            ['personel_giris_cikis.onayla', 'Personel Giriş Çıkış Onayla', 'onay'],
            ['personel_izin.goruntule', 'Personel İzin Görüntüle', 'goruntule'],
            ['personel_izin.olustur', 'Personel İzin Oluştur', 'olustur'],
            ['personel_izin.duzenle', 'Personel İzin Düzenle', 'guncelle'],
            ['personel_izin.onayla', 'Personel İzin Onayla', 'onay'],
            ['personel_avans.goruntule', 'Personel Avans Görüntüle', 'goruntule'],
            ['personel_avans.olustur', 'Personel Avans Oluştur', 'olustur'],
            ['personel_avans.onayla', 'Personel Avans Onayla', 'onay'],
            ['personel_maas.goruntule', 'Personel Maaş Görüntüle', 'goruntule'],
            ['personel_maas.hesapla', 'Personel Maaş Hesapla', 'guncelle'],
            ['personel_maas.odeme_yap', 'Personel Maaş Ödeme Yap', 'onay'],
            ['personel_rapor.goruntule', 'Personel Rapor Görüntüle', 'goruntule'],
        ];
        foreach ($personelYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'personel_takip',
                    'eylem' => $eylem,
                ]
            );
        }

        $restoranYetkileri = [
            ['restoran_masa.goruntule', 'Restoran Masa Görüntüle', 'goruntule'],
            ['restoran_masa.duzenle', 'Restoran Masa Düzenle', 'guncelle'],
            ['restoran_adisyon.goruntule', 'Restoran Adisyon Görüntüle', 'goruntule'],
            ['restoran_adisyon.olustur', 'Restoran Adisyon Oluştur', 'olustur'],
            ['restoran_adisyon.guncelle', 'Restoran Adisyon Güncelle', 'guncelle'],
            ['restoran_adisyon.iptal', 'Restoran Adisyon İptal', 'guncelle'],
            ['restoran_adisyon.tahsilat', 'Restoran Adisyon Tahsilat', 'onay'],
            ['restoran_adisyon.fatura', 'Restoran Adisyon Fatura', 'onay'],
            ['restoran_mutfak.goruntule', 'Restoran Mutfak Görüntüle', 'goruntule'],
            ['restoran_mutfak.guncelle', 'Restoran Mutfak Güncelle', 'guncelle'],
            ['restoran_qr_menu.goruntule', 'Restoran QR Menü Görüntüle', 'goruntule'],
            ['restoran_qr_menu.guncelle', 'Restoran QR Menü Güncelle', 'guncelle'],
            ['restoran_paket_servis.goruntule', 'Restoran Paket Servis Görüntüle', 'goruntule'],
            ['restoran_paket_servis.guncelle', 'Restoran Paket Servis Güncelle', 'guncelle'],
            ['restoran_rapor.goruntule', 'Restoran Rapor Görüntüle', 'goruntule'],
            ['restoran_gun_sonu.goruntule', 'Restoran Gün Sonu Görüntüle', 'goruntule'],
            ['restoran_ayar.guncelle', 'Restoran Ayar Güncelle', 'guncelle'],
        ];
        foreach ($restoranYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'restoran',
                    'eylem' => $eylem,
                ]
            );
        }

        // Cari kartı: kod `cari.*` — POS ile aynı şekilde `modul_kodu = muhasebe`.
        $cariYetkileri = [
            ['cari.goruntule', 'Cari Görüntüle', 'goruntule'],
            ['cari.olustur', 'Cari Oluştur', 'olustur'],
            ['cari.guncelle', 'Cari Güncelle', 'guncelle'],
            ['cari.sil', 'Cari Sil', 'sil'],
        ];
        foreach ($cariYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        // Stok kartı: kod `stok.*` — `modul_kodu = muhasebe`.
        $stokYetkileri = [
            ['stok.goruntule', 'Stok Görüntüle', 'goruntule'],
            ['stok.olustur', 'Stok Oluştur', 'olustur'],
            ['stok.guncelle', 'Stok Güncelle', 'guncelle'],
            ['stok.sil', 'Stok Sil', 'sil'],
            ['stok_olcu.goruntule', 'Stok Ölçüsü Görüntüle', 'goruntule'],
            ['stok_olcu.olustur', 'Stok Ölçüsü Oluştur', 'olustur'],
            ['stok_olcu.guncelle', 'Stok Ölçüsü Güncelle', 'guncelle'],
        ];
        foreach ($stokYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $faturaYetkileri = [
            ['fatura.goruntule', 'Fatura Görüntüle', 'goruntule'],
            ['fatura.olustur', 'Fatura Oluştur', 'olustur'],
            ['fatura.guncelle', 'Fatura Güncelle', 'guncelle'],
            ['fatura.sil', 'Fatura Sil', 'sil'],
            ['fatura.onay', 'Fatura Onay', 'onay'],
        ];
        foreach ($faturaYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $finansYetkileri = [
            ['finans.goruntule', 'Finans Görüntüle', 'goruntule'],
            ['finans.olustur', 'Finans Oluştur', 'olustur'],
            ['finans.guncelle', 'Finans Güncelle', 'guncelle'],
            ['finans.sil', 'Finans Sil', 'sil'],
            ['finans.onay', 'Finans Onay', 'onay'],
        ];
        foreach ($finansYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $raporYetkileri = [
            ['muhasebe_rapor.goruntule', 'Muhasebe Rapor Görüntüle', 'goruntule'],
        ];
        foreach ($raporYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $tanimYetkileri = [
            ['muhasebe_tanim.goruntule', 'Muhasebe Tanım Görüntüle', 'goruntule'],
            ['muhasebe_tanim.guncelle', 'Muhasebe Tanım Güncelle', 'guncelle'],
        ];
        foreach ($tanimYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'muhasebe',
                    'eylem' => $eylem,
                ]
            );
        }

        $teknikServisTanimYetkileri = [
            ['teknik_servis_tanim.goruntule', 'Teknik Servis Tanım Görüntüle', 'goruntule'],
            ['teknik_servis_tanim.guncelle', 'Teknik Servis Tanım Güncelle', 'guncelle'],
        ];
        foreach ($teknikServisTanimYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'teknik_servis',
                    'eylem' => $eylem,
                ]
            );
        }

        $teknikServisRaporYetkileri = [
            ['teknik_servis_rapor.goruntule', 'Teknik Servis Rapor Görüntüle', 'goruntule'],
        ];
        foreach ($teknikServisRaporYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'teknik_servis',
                    'eylem' => $eylem,
                ]
            );
        }

        $teknikServisAyarYetkileri = [
            ['teknik_servis_ayar.goruntule', 'Teknik Servis Ayar Görüntüle', 'goruntule'],
            ['teknik_servis_ayar.guncelle', 'Teknik Servis Ayar Güncelle', 'guncelle'],
        ];
        foreach ($teknikServisAyarYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'teknik_servis',
                    'eylem' => $eylem,
                ]
            );
        }

        foreach ([
            ['stok_seri.goruntule', 'Seri No Barkodlarını görüntüle', 'goruntule'],
        ] as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                ['ad' => $ad, 'modul_kodu' => 'muhasebe', 'eylem' => $eylem]
            );
        }

        $eTicaretYetkileri = [
            ['e_ticaret_siparis.goruntule', 'E-Ticaret Sipariş Görüntüle', 'goruntule'],
            ['e_ticaret_siparis.guncelle', 'E-Ticaret Sipariş Güncelle', 'guncelle'],
            ['e_ticaret_mesaj.goruntule', 'E-Ticaret Mesaj Görüntüle', 'goruntule'],
            ['e_ticaret_mesaj.guncelle', 'E-Ticaret Mesaj Güncelle', 'guncelle'],
            ['e_ticaret_mesaj.musteri_goruntule', 'E-Ticaret Müşteri Mesajı Görüntüle', 'goruntule'],
            ['e_ticaret_mesaj.urun_goruntule', 'E-Ticaret Ürün Mesajı Görüntüle', 'goruntule'],
            ['e_ticaret_kampanya.goruntule', 'E-Ticaret Kampanya Görüntüle', 'goruntule'],
            ['e_ticaret_kampanya.guncelle', 'E-Ticaret Kampanya Güncelle', 'guncelle'],
            ['e_ticaret_pazaryeri.goruntule', 'E-Ticaret Pazaryeri Görüntüle', 'goruntule'],
            ['e_ticaret_pazaryeri.guncelle', 'E-Ticaret Pazaryeri Güncelle', 'guncelle'],
            ['e_ticaret_varyasyon.goruntule', 'E-Ticaret Varyasyon Görüntüle', 'goruntule'],
            ['e_ticaret_varyasyon.guncelle', 'E-Ticaret Varyasyon Güncelle', 'guncelle'],
            ['e_ticaret_kargo.goruntule', 'E-Ticaret Kargo Görüntüle', 'goruntule'],
            ['e_ticaret_kargo.guncelle', 'E-Ticaret Kargo Güncelle', 'guncelle'],
            ['e_ticaret_odeme.goruntule', 'E-Ticaret Ödeme Görüntüle', 'goruntule'],
            ['e_ticaret_odeme.guncelle', 'E-Ticaret Ödeme Güncelle', 'guncelle'],
            ['e_ticaret_bildirim.goruntule', 'E-Ticaret Bildirim Görüntüle', 'goruntule'],
            ['e_ticaret_bildirim.guncelle', 'E-Ticaret Bildirim Güncelle', 'guncelle'],
        ];
        foreach ($eTicaretYetkileri as [$kod, $ad, $eylem]) {
            Yetki::query()->updateOrCreate(
                ['kod' => $kod],
                [
                    'ad' => $ad,
                    'modul_kodu' => 'e_ticaret',
                    'eylem' => $eylem,
                ]
            );
        }

        Yetki::query()->updateOrCreate(
            ['kod' => 'firma_yetki_yonetimi.yonet'],
            [
                'ad' => 'Firma Yetki Yönetimi',
                'modul_kodu' => 'kullanici',
                'eylem' => 'yonet',
            ]
        );

        $this->normalizePermissionLabels();
        $this->syncCriticalLabels();
    }

    private function eylemEtiketi(string $eylem): string
    {
        return [
            'goruntule' => "G\u{00F6}r\u{00FC}nt\u{00FC}le",
            'olustur' => "Olu\u{015F}tur",
            'guncelle' => "G\u{00FC}ncelle",
            'sil' => 'Sil',
            'yonet' => "Y\u{00F6}net",
            'onay' => 'Onay',
        ][$eylem] ?? ucfirst($eylem);
    }

    private function normalizePermissionLabels(): void
    {
        Yetki::query()
            ->select(['id', 'kod', 'ad'])
            ->chunkById(100, function ($records): void {
                foreach ($records as $record) {
                    $normalized = $this->normalizeTurkish((string) $record->ad);

                    if ($normalized === $record->ad) {
                        continue;
                    }

                    $record->ad = $normalized;
                    $record->save();
                }
            });
    }

    private function syncCriticalLabels(): void
    {
        foreach ([
            'kullanici.goruntule' => "Kullan\u{0131}c\u{0131} G\u{00F6}r\u{00FC}nt\u{00FC}le",
            'kullanici.olustur' => "Kullan\u{0131}c\u{0131} Olu\u{015F}tur",
            'kullanici.guncelle' => "Kullan\u{0131}c\u{0131} G\u{00FC}ncelle",
            'kullanici.sil' => "Kullan\u{0131}c\u{0131} Sil",
            'kullanici.yonet' => "Kullan\u{0131}c\u{0131} Y\u{00F6}net",
            'firma_yetki_yonetimi.yonet' => "Firma Yetki Y\u{00F6}netimi",
        ] as $kod => $ad) {
            Yetki::query()->where('kod', $kod)->update(['ad' => $ad]);
        }
    }

    private function normalizeTurkish(string $value): string
    {
        return strtr($value, [
            'GÃ¶rÃ¼ntÃ¼le' => 'Görüntüle',
            'OluÅŸtur' => 'Oluştur',
            'GÃ¼ncelle' => 'Güncelle',
            'YÃ¶netimi' => 'Yönetimi',
            'YÃ¶net' => 'Yönet',
            'MÃ¼ÅŸteri' => 'Müşteri',
            'SipariÅŸ' => 'Sipariş',
            'ÃœrÃ¼n' => 'Ürün',
            'Ã–deme' => 'Ödeme',
            'Ä°' => 'İ',
            'Ä±' => 'ı',
            'ÅŸ' => 'ş',
            'Åž' => 'Ş',
            'Ã§' => 'ç',
            'Ã‡' => 'Ç',
            'Ã¶' => 'ö',
            'Ã–' => 'Ö',
            'Ã¼' => 'ü',
            'Ãœ' => 'Ü',
            'ÄŸ' => 'ğ',
            'Äž' => 'Ğ',
            'Kullanici' => 'Kullanıcı',
            'Goruntule' => 'Görüntüle',
            'Olustur' => 'Oluştur',
            'Guncelle' => 'Güncelle',
            'Yonetimi' => 'Yönetimi',
            'Yonet' => 'Yönet',
            'Varlik' => 'Varlık',
            'Modul' => 'Modül',
            'Urun' => 'Ürün',
            'Odeme' => 'Ödeme',
            'Musteri' => 'Müşteri',
            'Siparis' => 'Sipariş',
            'Iptal' => 'İptal',
        ]);
    }
}


