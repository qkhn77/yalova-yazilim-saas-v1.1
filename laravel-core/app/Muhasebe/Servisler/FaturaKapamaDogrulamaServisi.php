<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Facades\Log;

class FaturaKapamaDogrulamaServisi
{
    private const PARA_BASAMAK = 8;
    private const SIFIR_TUTAR = '0.00000000';

    /**
     * @return array{fatura_id:int,hata:?string,odenecek_tutar:string,odendi_tutari:string,beklenen_odendi_tutari:string,acik_tutar:string,beklenen_acik_tutar:string}
     */
    public function faturaKapamaDurumuRaporla(int $faturaId): array
    {
        $fatura = Fatura::query()->findOrFail($faturaId);
        $odenen = (string) (FaturaFinansKapama::query()
            ->where('fatura_id', $fatura->id)
            ->whereHas('finansHareketi', fn ($q) => $q->where('durum', FinansHareketDurumu::Aktif))
            ->sum('uygulanan_tutar'));

        $odenecek = (string) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? 0);
        $acikBeklenen = bcsub($odenecek, $odenen, self::PARA_BASAMAK);
        $acikBeklenenClamp = bccomp($acikBeklenen, self::SIFIR_TUTAR, self::PARA_BASAMAK) < 0 ? self::SIFIR_TUTAR : $acikBeklenen;

        $hata = null;
        if (bccomp((string) $fatura->odendi_tutari, $odenen, self::PARA_BASAMAK) !== 0) {
            $hata = 'odendi_tutari uyuşmuyor';
        } elseif (bccomp((string) $fatura->acik_tutar, $acikBeklenenClamp, self::PARA_BASAMAK) !== 0) {
            $hata = 'acik_tutar uyuşmuyor';
        } elseif (bccomp((string) $fatura->acik_tutar, self::SIFIR_TUTAR, self::PARA_BASAMAK) < 0) {
            $hata = 'acik_tutar negatif';
        }

        $toplam = FaturaFinansKapama::query()->where('fatura_id', $fatura->id)->count();
        $distinct = FaturaFinansKapama::query()->where('fatura_id', $fatura->id)->distinct('finans_hareket_id')->count('finans_hareket_id');
        if ($hata === null && $toplam !== $distinct) {
            $hata = 'duplicate kapama tespit edildi';
        }

        return [
            'fatura_id' => (int) $fatura->id,
            'hata' => $hata,
            'odenecek_tutar' => $odenecek,
            'odendi_tutari' => (string) $fatura->odendi_tutari,
            'beklenen_odendi_tutari' => $odenen,
            'acik_tutar' => (string) $fatura->acik_tutar,
            'beklenen_acik_tutar' => $acikBeklenenClamp,
        ];
    }

    public function faturaKapamaDurumuDogrula(int $faturaId): void
    {
        $rapor = $this->faturaKapamaDurumuRaporla($faturaId);
        $hata = $rapor['hata'];

        if ($hata === null) {
            return;
        }

        Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->error('fatura.kapama.tutarsizlik', [
            'fatura_id' => $rapor['fatura_id'],
            'hata' => $hata,
            'odenecek_tutar' => $rapor['odenecek_tutar'],
            'odendi_tutari' => $rapor['odendi_tutari'],
            'beklenen_odendi_tutari' => $rapor['beklenen_odendi_tutari'],
            'acik_tutar' => $rapor['acik_tutar'],
            'beklenen_acik_tutar' => $rapor['beklenen_acik_tutar'],
        ]);

        if ((bool) config('muhasebe.fatura.kapama_tutarsizlik_hard_fail', false)) {
            throw new IsKuraliIstisnasi('Fatura kapama tutarsızlığı tespit edildi.');
        }
    }
}
