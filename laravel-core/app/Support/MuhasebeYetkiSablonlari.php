<?php

namespace App\Support;

use App\Filament\Clusters\Muhasebe\MuhasebeTaslakSayfa;
use App\Muhasebe\Filament\AbstractKaynaklar\BirimKaynagi;
use Database\Seeders\SaasPermissionsSeeder;

/**
 * Muhasebe modülü işlem bazlı yetki kodları (yer tutucu / seeder ile eşleşecek).
 *
 * Sidebar + {@see MuhasebeTaslakSayfa} + abstract kaynaklar tarafından kullanılanlar:
 * MUHASEBE_GORUNTULE, CARI_*, STOK_* (seed: {@see SaasPermissionsSeeder}), FATURA_GORUNTULE, …
 *
 * Fatura ve finans kaynaklarında temel görüntüleme/oluşturma/güncelleme/silme yetkileri kaynak katmanında; fatura onay/iptal/iade işlemleri sayfa aksiyonlarında ayrıca kontrol edilir.
 *
 * Tanım abstract kaynaklarında TANIM_OLUSTUR / TANIM_SIL sabitleri olmadığından, Filament `canCreate` / `canDelete` geçici olarak TANIM_GUNCELLE ile eşlenir (aynı kalıp: {@see BirimKaynagi} vb.).
 */
final class MuhasebeYetkiSablonlari
{
    public const MUHASEBE_GORUNTULE = 'muhasebe.goruntule';

    public const CARI_GORUNTULE = 'cari.goruntule';

    public const CARI_OLUSTUR = 'cari.olustur';

    public const CARI_GUNCELLE = 'cari.guncelle';

    public const CARI_SIL = 'cari.sil';

    public const STOK_GORUNTULE = 'stok.goruntule';

    public const STOK_OLUSTUR = 'stok.olustur';

    public const STOK_GUNCELLE = 'stok.guncelle';

    public const STOK_SIL = 'stok.sil';

    public const STOK_OLCU_GORUNTULE = 'stok_olcu.goruntule';

    public const STOK_OLCU_OLUSTUR = 'stok_olcu.olustur';

    public const STOK_OLCU_GUNCELLE = 'stok_olcu.guncelle';

    public const DEPO_GORUNTULE = 'depo.goruntule';

    public const DEPO_OLUSTUR = 'depo.olustur';

    public const DEPO_GUNCELLE = 'depo.guncelle';

    public const STOK_SERI_GORUNTULE = 'stok_seri.goruntule';

    public const FATURA_GORUNTULE = 'fatura.goruntule';

    public const FATURA_OLUSTUR = 'fatura.olustur';

    public const FATURA_GUNCELLE = 'fatura.guncelle';

    public const FATURA_SIL = 'fatura.sil';

    public const FATURA_ONAY = 'fatura.onay';

    public const FINANS_GORUNTULE = 'finans.goruntule';

    public const FINANS_OLUSTUR = 'finans.olustur';

    public const FINANS_GUNCELLE = 'finans.guncelle';

    public const FINANS_SIL = 'finans.sil';

    /**
     * Finans hareketlerinde fiziksel silme yoktur; geriye dönük uyumluluk
     * için eski finans.sil yetkisini iptal yetkisi olarak kullanırız.
     */
    public const FINANS_IPTAL = self::FINANS_SIL;

    public const FINANS_ONAY = 'finans.onay';

    public const RAPOR_GORUNTULE = 'muhasebe_rapor.goruntule';

    public const TANIM_GORUNTULE = 'muhasebe_tanim.goruntule';

    public const TANIM_GUNCELLE = 'muhasebe_tanim.guncelle';

    public const POS_GORUNTULE = 'pos.goruntule';

    public const POS_OLUSTUR = 'pos.olustur';

    public const POS_GUNCELLE = 'pos.guncelle';

    public const POS_SIL = 'pos.sil';

    public const BARKODLU_SATIS_GORUNTULE = 'barkodlu_satis.goruntule';

    public const BARKODLU_SATIS_OLUSTUR = 'barkodlu_satis.olustur';

    public const BARKODLU_SATIS_GUNCELLE = 'barkodlu_satis.guncelle';

    public const BARKODLU_SATIS_ETIKET_YAZDIR = 'barkodlu_satis.etiket_yazdir';

    public const BARKODLU_SATIS_IPTAL = 'barkodlu_satis.iptal';

    public const BARKODLU_SATIS_IADE = 'barkodlu_satis.iade';

    public const BARKODLU_SATIS_FIYAT_GUNCELLE = 'barkodlu_satis.fiyat_guncelle';

    public const BARKODLU_SATIS_ISKONTO_UYGULA = 'barkodlu_satis.iskonto_uygula';

    public const BARKODLU_SATIS_AYAR_GORUNTULE = 'barkodlu_satis_ayar.goruntule';

    public const BARKODLU_SATIS_AYAR_GUNCELLE = 'barkodlu_satis_ayar.guncelle';
}
