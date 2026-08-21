<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Yetki;
use Illuminate\Database\Seeder;

class SaasRolePermissionMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'firma_sahibi' => [
                'kullanici.goruntule', 'kullanici.olustur', 'kullanici.guncelle', 'kullanici.sil', 'kullanici.yonet',
                'firma_yetki_yonetimi.yonet',
                'firma.goruntule', 'firma.guncelle', 'firma.onay',
                'modul.goruntule', 'modul.yonet',
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle', 'sekreter.sil',
                'muhasebe.goruntule', 'muhasebe.olustur', 'muhasebe.guncelle', 'muhasebe.sil',
                'masraf_takip.goruntule', 'masraf_takip.olustur', 'masraf_takip.guncelle', 'masraf_takip.sil',
                'pos.goruntule', 'pos.olustur', 'pos.guncelle', 'pos.sil',
                'cari.goruntule', 'cari.olustur', 'cari.guncelle', 'cari.sil',
                'stok.goruntule', 'stok.olustur', 'stok.guncelle', 'stok.sil',
                'stok_seri.goruntule',
                'fatura.goruntule', 'fatura.olustur', 'fatura.guncelle', 'fatura.sil', 'fatura.onay',
                'finans.goruntule', 'finans.olustur', 'finans.guncelle', 'finans.sil', 'finans.onay',
                'muhasebe_rapor.goruntule',
                'muhasebe_tanim.goruntule', 'muhasebe_tanim.guncelle',
                'teknik_servis.goruntule', 'teknik_servis.olustur', 'teknik_servis.guncelle', 'teknik_servis.sil',
                'teknik_servis_tanim.goruntule', 'teknik_servis_tanim.guncelle',
                'teknik_servis_rapor.goruntule',
                'teknik_servis_ayar.goruntule', 'teknik_servis_ayar.guncelle',
                'barkodlu_satis.goruntule', 'barkodlu_satis.olustur', 'barkodlu_satis.guncelle', 'barkodlu_satis.etiket_yazdir', 'barkodlu_satis.iptal', 'barkodlu_satis.iade', 'barkodlu_satis.fiyat_guncelle', 'barkodlu_satis.iskonto_uygula',
                'barkodlu_satis_ayar.goruntule', 'barkodlu_satis_ayar.guncelle',
                'depo.goruntule', 'depo.olustur', 'depo.guncelle',
                'restoran.goruntule', 'restoran.olustur', 'restoran.guncelle',
                'proje_yonetimi.goruntule', 'proje_yonetimi.olustur', 'proje_yonetimi.guncelle', 'proje_yonetimi.sil',
                'personel_takip.goruntule', 'personel_takip.olustur', 'personel_takip.guncelle',
                'personel.goruntule', 'personel.olustur', 'personel.guncelle', 'personel.sil',
                'personel_tanim.goruntule', 'personel_tanim.guncelle',
                'personel_vardiya.goruntule', 'personel_vardiya.duzenle',
                'personel_giris_cikis.goruntule', 'personel_giris_cikis.duzenle', 'personel_giris_cikis.onayla',
                'personel_izin.goruntule', 'personel_izin.olustur', 'personel_izin.duzenle', 'personel_izin.onayla',
                'personel_avans.goruntule', 'personel_avans.olustur', 'personel_avans.onayla',
                'personel_maas.goruntule', 'personel_maas.hesapla', 'personel_maas.odeme_yap',
                'personel_rapor.goruntule',
                'teklif_yonetimi.goruntule', 'teklif_yonetimi.olustur', 'teklif_yonetimi.guncelle', 'teklif_yonetimi.sil',
                'e_ticaret.goruntule', 'e_ticaret.olustur', 'e_ticaret.guncelle',
                'bt_varlik_yonetimi.goruntule', 'bt_varlik_yonetimi.olustur', 'bt_varlik_yonetimi.guncelle', 'bt_varlik_yonetimi.sil',
                'web.goruntule', 'web.olustur', 'web.guncelle', 'web.sil',
                'urun.goruntule', 'urun.olustur', 'urun.guncelle', 'urun.sil',
                'urun_kategori.goruntule', 'urun_kategori.olustur', 'urun_kategori.guncelle', 'urun_kategori.sil',
            ],
            'firma_yoneticisi' => [
                'kullanici.goruntule', 'kullanici.olustur', 'kullanici.guncelle',
                'firma_yetki_yonetimi.yonet',
                'firma.goruntule', 'firma.guncelle',
                'modul.goruntule',
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle',
                'muhasebe.goruntule', 'muhasebe.olustur', 'muhasebe.guncelle',
                'masraf_takip.goruntule', 'masraf_takip.olustur', 'masraf_takip.guncelle',
                'pos.goruntule', 'pos.olustur', 'pos.guncelle', 'pos.sil',
                'fatura.goruntule', 'fatura.olustur', 'fatura.guncelle', 'fatura.onay',
                'finans.goruntule', 'finans.olustur', 'finans.guncelle', 'finans.onay',
                'barkodlu_satis.goruntule', 'barkodlu_satis.olustur', 'barkodlu_satis.guncelle', 'barkodlu_satis.etiket_yazdir', 'barkodlu_satis.iade', 'barkodlu_satis.fiyat_guncelle', 'barkodlu_satis.iskonto_uygula',
                'muhasebe_rapor.goruntule',
                'muhasebe_tanim.goruntule', 'muhasebe_tanim.guncelle',
                'teknik_servis.goruntule', 'teknik_servis.olustur', 'teknik_servis.guncelle', 'teknik_servis.sil',
                'teknik_servis_tanim.goruntule', 'teknik_servis_tanim.guncelle',
                'teknik_servis_rapor.goruntule',
                'teknik_servis_ayar.goruntule', 'teknik_servis_ayar.guncelle',
                'barkodlu_satis.goruntule', 'barkodlu_satis.olustur', 'barkodlu_satis.guncelle', 'barkodlu_satis.etiket_yazdir', 'barkodlu_satis.iptal', 'barkodlu_satis.iade', 'barkodlu_satis.iskonto_uygula',
                'barkodlu_satis_ayar.goruntule', 'barkodlu_satis_ayar.guncelle',
                'depo.goruntule', 'depo.olustur', 'depo.guncelle',
                'restoran.goruntule', 'restoran.olustur', 'restoran.guncelle',
                'proje_yonetimi.goruntule', 'proje_yonetimi.olustur', 'proje_yonetimi.guncelle',
                'personel_takip.goruntule', 'personel_takip.olustur', 'personel_takip.guncelle',
                'personel.goruntule', 'personel.olustur', 'personel.guncelle',
                'personel_tanim.goruntule', 'personel_tanim.guncelle',
                'personel_vardiya.goruntule', 'personel_vardiya.duzenle',
                'personel_giris_cikis.goruntule', 'personel_giris_cikis.duzenle', 'personel_giris_cikis.onayla',
                'personel_izin.goruntule', 'personel_izin.olustur', 'personel_izin.duzenle', 'personel_izin.onayla',
                'personel_avans.goruntule', 'personel_avans.olustur', 'personel_avans.onayla',
                'personel_maas.goruntule', 'personel_maas.hesapla', 'personel_maas.odeme_yap',
                'personel_rapor.goruntule',
                'teklif_yonetimi.goruntule', 'teklif_yonetimi.olustur', 'teklif_yonetimi.guncelle',
                'e_ticaret.goruntule', 'e_ticaret.olustur', 'e_ticaret.guncelle',
                'bt_varlik_yonetimi.goruntule', 'bt_varlik_yonetimi.olustur', 'bt_varlik_yonetimi.guncelle',
                'web.goruntule', 'web.olustur', 'web.guncelle',
                'urun.goruntule', 'urun.olustur', 'urun.guncelle',
                'urun_kategori.goruntule', 'urun_kategori.olustur', 'urun_kategori.guncelle',
            ],
            'muhasebe_personeli' => [
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle',
                'muhasebe.goruntule', 'muhasebe.olustur', 'muhasebe.guncelle',
                'masraf_takip.goruntule', 'masraf_takip.olustur', 'masraf_takip.guncelle',
                'pos.goruntule', 'pos.olustur', 'pos.guncelle', 'pos.sil',
                'cari.goruntule', 'cari.olustur', 'cari.guncelle', 'cari.sil',
                'stok.goruntule', 'stok.olustur', 'stok.guncelle', 'stok.sil',
                'stok_seri.goruntule',
                'fatura.goruntule', 'fatura.olustur', 'fatura.guncelle', 'fatura.onay',
                'finans.goruntule', 'finans.olustur', 'finans.guncelle',
                'muhasebe_rapor.goruntule',
                'muhasebe_tanim.goruntule', 'muhasebe_tanim.guncelle',
                'barkodlu_satis_ayar.goruntule',
                'depo.goruntule',
                'teklif_yonetimi.goruntule',
                'firma.goruntule',
            ],
            'teknik_servis_personeli' => [
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle',
                'teknik_servis.goruntule', 'teknik_servis.olustur', 'teknik_servis.guncelle', 'teknik_servis.sil',
                'teknik_servis_tanim.goruntule', 'teknik_servis_tanim.guncelle',
                'teknik_servis_rapor.goruntule',
                'teknik_servis_ayar.goruntule', 'teknik_servis_ayar.guncelle',
                'teklif_yonetimi.goruntule', 'teklif_yonetimi.olustur', 'teklif_yonetimi.guncelle',
                'depo.goruntule',
                'urun.goruntule',
                'urun_kategori.goruntule',
                'firma.goruntule',
            ],
            'satis_personeli' => [
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle',
                'barkodlu_satis.goruntule', 'barkodlu_satis.olustur', 'barkodlu_satis.guncelle', 'barkodlu_satis.etiket_yazdir', 'barkodlu_satis.iptal', 'barkodlu_satis.iade',
                'teknik_servis.goruntule', 'teknik_servis.olustur', 'teknik_servis.guncelle',
                'teknik_servis_tanim.goruntule',
                'teklif_yonetimi.goruntule', 'teklif_yonetimi.olustur', 'teklif_yonetimi.guncelle',
                'urun.goruntule', 'urun.olustur', 'urun.guncelle',
                'urun_kategori.goruntule',
                'e_ticaret.goruntule',
                'firma.goruntule',
            ],
            'depo_personeli' => [
                'sekreter.goruntule', 'sekreter.olustur', 'sekreter.guncelle',
                'depo.goruntule', 'depo.olustur', 'depo.guncelle',
                'stok.goruntule', 'stok.olustur', 'stok.guncelle',
                'stok_seri.goruntule',
                'fatura.goruntule',
                'finans.goruntule',
                'urun.goruntule', 'urun.guncelle',
                'urun_kategori.goruntule',
                'barkodlu_satis.goruntule', 'barkodlu_satis.etiket_yazdir',
                'firma.goruntule',
            ],
            'goruntuleyici' => [
                'sekreter.goruntule',
                'firma.goruntule',
                'muhasebe.goruntule',
                'masraf_takip.goruntule',
                'pos.goruntule',
                'cari.goruntule',
                'stok.goruntule', 'stok_seri.goruntule',
                'fatura.goruntule',
                'finans.goruntule',
                'muhasebe_rapor.goruntule',
                'muhasebe_tanim.goruntule',
                'teknik_servis.goruntule',
                'teknik_servis_tanim.goruntule',
                'teknik_servis_rapor.goruntule',
                'teknik_servis_ayar.goruntule',
                'barkodlu_satis.goruntule',
                'barkodlu_satis_ayar.goruntule',
                'depo.goruntule',
                'restoran.goruntule',
                'proje_yonetimi.goruntule',
                'personel_takip.goruntule',
                'personel.goruntule',
                'personel_tanim.goruntule',
                'personel_vardiya.goruntule',
                'personel_giris_cikis.goruntule',
                'personel_izin.goruntule',
                'personel_avans.goruntule',
                'personel_maas.goruntule',
                'personel_rapor.goruntule',
                'teklif_yonetimi.goruntule',
                'e_ticaret.goruntule',
                'bt_varlik_yonetimi.goruntule',
                'web.goruntule',
                'urun.goruntule',
                'urun_kategori.goruntule',
            ],
        ];

        $eTicaretYonetimYetkileri = [
            'e_ticaret_siparis.goruntule', 'e_ticaret_siparis.guncelle',
            'e_ticaret_mesaj.goruntule', 'e_ticaret_mesaj.guncelle',
            'e_ticaret_mesaj.musteri_goruntule', 'e_ticaret_mesaj.urun_goruntule',
            'e_ticaret_kampanya.goruntule', 'e_ticaret_kampanya.guncelle',
            'e_ticaret_pazaryeri.goruntule', 'e_ticaret_pazaryeri.guncelle',
            'e_ticaret_varyasyon.goruntule', 'e_ticaret_varyasyon.guncelle',
            'e_ticaret_kargo.goruntule', 'e_ticaret_kargo.guncelle',
            'e_ticaret_odeme.goruntule', 'e_ticaret_odeme.guncelle',
            'e_ticaret_bildirim.goruntule', 'e_ticaret_bildirim.guncelle',
        ];

        $matrix['firma_sahibi'] = array_values(array_unique(array_merge($matrix['firma_sahibi'] ?? [], $eTicaretYonetimYetkileri)));
        $matrix['firma_yoneticisi'] = array_values(array_unique(array_merge($matrix['firma_yoneticisi'] ?? [], $eTicaretYonetimYetkileri)));
        $matrix['goruntuleyici'] = array_values(array_unique(array_merge(
            $matrix['goruntuleyici'] ?? [],
            [
                'e_ticaret_siparis.goruntule',
                'e_ticaret_mesaj.goruntule',
                'e_ticaret_mesaj.musteri_goruntule',
                'e_ticaret_mesaj.urun_goruntule',
                'e_ticaret_kampanya.goruntule',
                'e_ticaret_pazaryeri.goruntule',
                'e_ticaret_varyasyon.goruntule',
                'e_ticaret_kargo.goruntule',
                'e_ticaret_odeme.goruntule',
                'e_ticaret_bildirim.goruntule',
            ]
        )));

        $restoranYonetimYetkileri = [
            'restoran.goruntule', 'restoran.olustur', 'restoran.guncelle',
            'restoran_masa.goruntule', 'restoran_masa.duzenle',
            'restoran_adisyon.goruntule', 'restoran_adisyon.olustur', 'restoran_adisyon.guncelle',
            'restoran_adisyon.iptal', 'restoran_adisyon.tahsilat', 'restoran_adisyon.fatura',
            'restoran_mutfak.goruntule', 'restoran_mutfak.guncelle',
            'restoran_qr_menu.goruntule', 'restoran_qr_menu.guncelle',
            'restoran_paket_servis.goruntule', 'restoran_paket_servis.guncelle',
            'restoran_rapor.goruntule', 'restoran_gun_sonu.goruntule', 'restoran_ayar.guncelle',
        ];
        $restoranOperasyonYetkileri = [
            'restoran.goruntule',
            'restoran_masa.goruntule',
            'restoran_adisyon.goruntule', 'restoran_adisyon.olustur', 'restoran_adisyon.guncelle',
            'restoran_mutfak.goruntule', 'restoran_mutfak.guncelle',
            'restoran_paket_servis.goruntule', 'restoran_paket_servis.guncelle',
        ];
        $restoranGoruntulemeYetkileri = [
            'restoran.goruntule',
            'restoran_masa.goruntule',
            'restoran_adisyon.goruntule',
            'restoran_mutfak.goruntule',
            'restoran_qr_menu.goruntule',
            'restoran_paket_servis.goruntule',
            'restoran_rapor.goruntule',
        ];

        $matrix['firma_sahibi'] = array_values(array_unique(array_merge($matrix['firma_sahibi'] ?? [], $restoranYonetimYetkileri)));
        $matrix['firma_yoneticisi'] = array_values(array_unique(array_merge($matrix['firma_yoneticisi'] ?? [], $restoranYonetimYetkileri)));
        $matrix['satis_personeli'] = array_values(array_unique(array_merge($matrix['satis_personeli'] ?? [], $restoranOperasyonYetkileri)));
        $matrix['muhasebe_personeli'] = array_values(array_unique(array_merge($matrix['muhasebe_personeli'] ?? [], [
            'restoran.goruntule',
            'restoran_adisyon.goruntule',
            'restoran_adisyon.tahsilat',
            'restoran_adisyon.fatura',
            'restoran_rapor.goruntule',
            'restoran_gun_sonu.goruntule',
        ])));
        $matrix['goruntuleyici'] = array_values(array_unique(array_merge($matrix['goruntuleyici'] ?? [], $restoranGoruntulemeYetkileri)));

        $personelYonetimYetkileri = [
            'personel.goruntule', 'personel.olustur', 'personel.guncelle', 'personel.sil',
            'personel_tanim.goruntule', 'personel_tanim.guncelle',
            'personel_vardiya.goruntule', 'personel_vardiya.duzenle',
            'personel_giris_cikis.goruntule', 'personel_giris_cikis.duzenle', 'personel_giris_cikis.onayla',
            'personel_izin.goruntule', 'personel_izin.olustur', 'personel_izin.duzenle', 'personel_izin.onayla',
            'personel_avans.goruntule', 'personel_avans.olustur', 'personel_avans.onayla',
            'personel_maas.goruntule', 'personel_maas.hesapla', 'personel_maas.odeme_yap',
            'personel_rapor.goruntule',
        ];
        $personelGoruntulemeYetkileri = [
            'personel.goruntule',
            'personel_tanim.goruntule',
            'personel_vardiya.goruntule',
            'personel_giris_cikis.goruntule',
            'personel_izin.goruntule',
            'personel_rapor.goruntule',
        ];

        $matrix['firma_sahibi'] = array_values(array_unique(array_merge($matrix['firma_sahibi'] ?? [], $personelYonetimYetkileri)));
        $matrix['firma_yoneticisi'] = array_values(array_unique(array_merge($matrix['firma_yoneticisi'] ?? [], $personelYonetimYetkileri)));
        $matrix['muhasebe_personeli'] = array_values(array_unique(array_merge($matrix['muhasebe_personeli'] ?? [], [
            'personel.goruntule',
            'personel_avans.goruntule', 'personel_avans.olustur', 'personel_avans.onayla',
            'personel_maas.goruntule', 'personel_maas.hesapla', 'personel_maas.odeme_yap',
            'personel_rapor.goruntule',
        ])));
        $matrix['teknik_servis_personeli'] = array_values(array_unique(array_merge($matrix['teknik_servis_personeli'] ?? [], [
            'personel.goruntule',
            'personel_vardiya.goruntule',
            'personel_giris_cikis.goruntule',
        ])));
        $matrix['satis_personeli'] = array_values(array_unique(array_merge($matrix['satis_personeli'] ?? [], [
            'personel.goruntule',
            'personel_vardiya.goruntule',
            'personel_giris_cikis.goruntule',
        ])));
        $matrix['depo_personeli'] = array_values(array_unique(array_merge($matrix['depo_personeli'] ?? [], [
            'personel.goruntule',
            'personel_vardiya.goruntule',
            'personel_giris_cikis.goruntule',
        ])));
        $matrix['goruntuleyici'] = array_values(array_unique(array_merge($matrix['goruntuleyici'] ?? [], $personelGoruntulemeYetkileri)));

        // Ölçü izinleri stok izinlerinin ayrıntılı alt kümesidir; mevcut rollerin
        // stok yetkileri korunarak idempotent biçimde genişletilir.
        foreach ($matrix as $rolKodu => $yetkiKodlari) {
            $olcuYetkileri = [];
            if (in_array('stok.goruntule', $yetkiKodlari, true)) {
                $olcuYetkileri[] = 'stok_olcu.goruntule';
            }
            if (in_array('stok.olustur', $yetkiKodlari, true)) {
                $olcuYetkileri[] = 'stok_olcu.olustur';
            }
            if (in_array('stok.guncelle', $yetkiKodlari, true)) {
                $olcuYetkileri[] = 'stok_olcu.guncelle';
            }
            $matrix[$rolKodu] = array_values(array_unique(array_merge($yetkiKodlari, $olcuYetkileri)));
        }

        foreach ($matrix as $rolKodu => $yetkiKodlari) {
            $rol = Rol::query()->where('kod', $rolKodu)->first();
            if (! $rol) {
                continue;
            }

            $yetkiIdleri = $rolKodu === 'firma_sahibi'
                ? Yetki::query()->pluck('id')->all()
                : Yetki::query()
                    ->whereIn('kod', array_values(array_unique($yetkiKodlari)))
                    ->pluck('id')
                    ->all();

            if (! empty($yetkiIdleri)) {
                $rol->yetkiler()->syncWithoutDetaching($yetkiIdleri);
            }
        }
    }
}
