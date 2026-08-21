<?php

namespace App\Support;

/**
 * Teknik Servis modülü yetki kodları (SaasPermissionsSeeder ile eşleşir).
 *
 * Ana işlem: servis kayıtları ({@see TeknikServisKayitKaynakErisimi}).
 * Tanımlar: durum / cihaz / marka / aksesuar / arıza ({@see TeknikServisTanimKaynakErisimi}).
 */
final class TeknikServisYetkiSablonlari
{
    public const GORUNTULE = 'teknik_servis.goruntule';

    public const OLUSTUR = 'teknik_servis.olustur';

    public const GUNCELLE = 'teknik_servis.guncelle';

    public const SIL = 'teknik_servis.sil';

    public const TANIM_GORUNTULE = 'teknik_servis_tanim.goruntule';

    public const TANIM_GUNCELLE = 'teknik_servis_tanim.guncelle';

    public const RAPOR_GORUNTULE = 'teknik_servis_rapor.goruntule';

    public const AYAR_GORUNTULE = 'teknik_servis_ayar.goruntule';

    public const AYAR_GUNCELLE = 'teknik_servis_ayar.guncelle';

    /**
     * Özet / operasyon sayfaları: yalnızca servis kaydı (ana TS) yetkileri.
     * Tanım, rapor ve ayar ekranları ayrı trait / kaynak erişimleri ile açılır;
     * böylece yalnızca tanim.* / rapor / ayar yetkisi olan kullanıcılar operasyon URL’sine giremez.
     *
     * @return list<string>
     */
    public static function panelOzetiVeOperasyonYetkileri(): array
    {
        return [
            self::GORUNTULE,
            self::OLUSTUR,
            self::GUNCELLE,
            self::SIL,
        ];
    }
}
