<?php

namespace App\Support\PersonelTakip;

final class PersonelTakipYetkiSablonlari
{
    public const GORUNTULE = 'personel.goruntule';

    public const OLUSTUR = 'personel.olustur';

    public const GUNCELLE = 'personel.guncelle';

    public const SIL = 'personel.sil';

    public const TANIM_GORUNTULE = 'personel_tanim.goruntule';

    public const TANIM_GUNCELLE = 'personel_tanim.guncelle';

    public const VARDIYA_GORUNTULE = 'personel_vardiya.goruntule';

    public const VARDIYA_DUZENLE = 'personel_vardiya.duzenle';

    public const GIRIS_CIKIS_GORUNTULE = 'personel_giris_cikis.goruntule';

    public const GIRIS_CIKIS_DUZENLE = 'personel_giris_cikis.duzenle';

    public const GIRIS_CIKIS_ONAYLA = 'personel_giris_cikis.onayla';

    public const IZIN_GORUNTULE = 'personel_izin.goruntule';

    public const IZIN_OLUSTUR = 'personel_izin.olustur';

    public const IZIN_DUZENLE = 'personel_izin.duzenle';

    public const IZIN_ONAYLA = 'personel_izin.onayla';

    public const AVANS_GORUNTULE = 'personel_avans.goruntule';

    public const AVANS_OLUSTUR = 'personel_avans.olustur';

    public const AVANS_ONAYLA = 'personel_avans.onayla';

    public const MAAS_GORUNTULE = 'personel_maas.goruntule';

    public const MAAS_HESAPLA = 'personel_maas.hesapla';

    public const MAAS_ODEME_YAP = 'personel_maas.odeme_yap';

    public const RAPOR_GORUNTULE = 'personel_rapor.goruntule';

    /**
     * @return list<string>
     */
    public static function tumErisimYetkileri(): array
    {
        return [
            self::GORUNTULE,
            self::OLUSTUR,
            self::GUNCELLE,
            self::SIL,
            self::TANIM_GORUNTULE,
            self::TANIM_GUNCELLE,
            self::VARDIYA_GORUNTULE,
            self::VARDIYA_DUZENLE,
            self::GIRIS_CIKIS_GORUNTULE,
            self::GIRIS_CIKIS_DUZENLE,
            self::GIRIS_CIKIS_ONAYLA,
            self::IZIN_GORUNTULE,
            self::IZIN_OLUSTUR,
            self::IZIN_DUZENLE,
            self::IZIN_ONAYLA,
            self::AVANS_GORUNTULE,
            self::AVANS_OLUSTUR,
            self::AVANS_ONAYLA,
            self::MAAS_GORUNTULE,
            self::MAAS_HESAPLA,
            self::MAAS_ODEME_YAP,
            self::RAPOR_GORUNTULE,
        ];
    }
}
