<?php

namespace App\Muhasebe\Servisler;

use App\Models\Masraf\Arac;
use App\Models\Masraf\DuzenliFaturaTanimi;
use App\Models\Personel\Personel;
use App\Models\Personel\PersonelAvansi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\TeknikServis\TeknikServisMuhasebeBaglantisi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;

final class MasrafKaynakDogrulamaServisi
{
    public const PERSONEL = 'personel';

    public const PERSONEL_MAAS = 'personel_maas';

    public const PERSONEL_AVANS = 'personel_avans';

    public const ARAC = 'arac';

    public const DUZENLI_FATURA = 'duzenli_fatura';

    public const TEKNIK_SERVIS = 'teknik_servis';

    /** @return array<string, string> */
    public static function turSecenekleri(): array
    {
        return [
            '' => 'Özel kaynak seçilmedi',
            self::PERSONEL => 'Personel kaydı',
            self::ARAC => 'Araç kaydı',
            self::DUZENLI_FATURA => 'Düzenli fatura tanımı',
            self::TEKNIK_SERVIS => 'Teknik servis kaydı',
        ];
    }

    public function dogrula(int $firmaId, string $tur, int $kaynakId): void
    {
        if ($tur === '' && $kaynakId < 1) {
            return;
        }

        if ($tur === '' || $kaynakId < 1) {
            throw new IsKuraliIstisnasi('Özel kaynak seçimi eksik.');
        }

        $varMi = match ($tur) {
            self::PERSONEL => Personel::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->where('durum', Personel::DURUM_AKTIF)->exists(),
            self::PERSONEL_MAAS => PersonelMaasHareketi::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->exists(),
            self::PERSONEL_AVANS => PersonelAvansi::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->whereNull('deleted_at')->exists(),
            self::ARAC => Arac::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->where('aktif_mi', true)->exists(),
            self::DUZENLI_FATURA => DuzenliFaturaTanimi::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->where('aktif_mi', true)->exists(),
            self::TEKNIK_SERVIS => TeknikServisKaydi::query()->where('firma_id', $firmaId)->whereKey($kaynakId)->exists(),
            default => throw new IsKuraliIstisnasi('Geçersiz özel masraf kaynak türü.'),
        };

        if (! $varMi) {
            throw new IsKuraliIstisnasi('Seçilen özel kaynak aktif firmaya ait değil veya kullanıma kapalı.');
        }

        if ($tur === self::TEKNIK_SERVIS) {
            $giderFaturasiVarMi = TeknikServisMuhasebeBaglantisi::query()
                ->where('firma_id', $firmaId)
                ->where('teknik_servis_kaydi_id', $kaynakId)
                ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Gider->value)
                ->whereNotNull('gider_faturasi_id')
                ->whereHas('giderFaturasi', fn ($query) => $query
                    ->where('durum', '<>', 'iptal')
                    ->whereNull('deleted_at'))
                ->exists();

            if ($giderFaturasiVarMi) {
                throw new IsKuraliIstisnasi('Bu teknik servis kaydı aktif gider faturasıyla zaten muhasebeleştirilmiş. Aynı gider için ikinci masraf kaydı açılamaz.');
            }
        }
    }
}
