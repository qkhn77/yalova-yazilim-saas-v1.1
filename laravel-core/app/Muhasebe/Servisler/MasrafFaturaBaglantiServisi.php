<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafFaturaDagitimi;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Support\Facades\DB;

final class MasrafFaturaBaglantiServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
    ) {}

    public function bagla(int $firmaId, int $masrafId, int $faturaId, string|int|float $tutar): MasrafFaturaDagitimi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);
        $dagitimTutari = $this->tutarNormalizeEt($tutar);

        return DB::transaction(function () use ($firmaId, $masrafId, $faturaId, $dagitimTutari): MasrafFaturaDagitimi {
            $masraf = Masraf::query()
                ->where('firma_id', $firmaId)
                ->whereKey($masrafId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($masraf->durum !== Masraf::DURUM_AKTIF) {
                throw new IsKuraliIstisnasi('İptal edilmiş masrafa fatura bağlanamaz.');
            }

            $fatura = Fatura::query()
                ->where('firma_id', $firmaId)
                ->whereKey($faturaId)
                ->lockForUpdate()
                ->firstOrFail();

            $faturaTuru = $fatura->tur instanceof FaturaTuru
                ? $fatura->tur->kanonik()
                : FaturaTuru::from((string) $fatura->tur)->kanonik();

            if ($faturaTuru !== FaturaTuru::Gider) {
                throw new IsKuraliIstisnasi('Masraf yalnızca gider faturasıyla eşleştirilebilir.');
            }

            $faturaDurumu = $fatura->durum instanceof FaturaDurumu
                ? $fatura->durum
                : FaturaDurumu::tryFrom((string) $fatura->durum);
            if ($faturaDurumu === FaturaDurumu::Iptal) {
                throw new IsKuraliIstisnasi('İptal edilmiş faturaya masraf bağlanamaz.');
            }

            $faturaParaBirimi = strtoupper((string) ($fatura->para_birimi ?: 'TRY'));
            if ($faturaParaBirimi !== strtoupper((string) ($masraf->para_birimi ?: 'TRY'))) {
                throw new IsKuraliIstisnasi('Masraf ve fatura para birimi aynı olmalıdır.');
            }

            $masrafProjeId = (int) ($masraf->isletme_proje_id ?? 0);
            $faturaProjeId = (int) ($fatura->isletme_proje_id ?? 0);
            foreach ([$masrafProjeId, $faturaProjeId] as $projeId) {
                if ($projeId > 0 && ! IsletmeProjesi::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($projeId)
                    ->exists()) {
                    throw new IsKuraliIstisnasi('Masraf ve fatura projesi aktif firmaya ait olmalıdır.');
                }
            }
            if ($masrafProjeId > 0 && $faturaProjeId > 0 && $masrafProjeId !== $faturaProjeId) {
                throw new IsKuraliIstisnasi('Masraf ve fatura aynı projeye bağlı olmalıdır.');
            }
            if ($masrafProjeId > 0 && $faturaProjeId < 1) {
                $fatura->update(['isletme_proje_id' => $masrafProjeId]);
                $fatura->cariHareketleri()->update(['isletme_proje_id' => $masrafProjeId]);
            } elseif ($faturaProjeId > 0 && $masrafProjeId < 1) {
                $masraf->update(['isletme_proje_id' => $faturaProjeId]);
            }

            $mevcut = MasrafFaturaDagitimi::query()
                ->where('firma_id', $firmaId)
                ->where('masraf_id', $masrafId)
                ->where('fatura_id', $faturaId)
                ->lockForUpdate()
                ->first();

            if ($mevcut) {
                if (bccomp((string) $mevcut->tutar, $dagitimTutari, 2) !== 0) {
                    throw new IsKuraliIstisnasi('Bu masraf-fatura bağlantısı farklı bir tutarla zaten mevcut.');
                }

                return $mevcut;
            }

            $tavan = $this->faturaTavanTutari($fatura);
            $mevcutDagitim = (string) MasrafFaturaDagitimi::query()
                ->where('firma_id', $firmaId)
                ->where('fatura_id', $faturaId)
                ->sum('tutar');
            $yeniToplam = bcadd($mevcutDagitim, $dagitimTutari, 2);

            if (bccomp($yeniToplam, $tavan, 2) === 1) {
                throw new IsKuraliIstisnasi('Faturaya dağıtılan masraf toplamı fatura tutarını aşamaz.');
            }

            return MasrafFaturaDagitimi::query()->create([
                'firma_id' => $firmaId,
                'masraf_id' => $masrafId,
                'fatura_id' => $faturaId,
                'tutar' => $dagitimTutari,
                'para_birimi' => $faturaParaBirimi,
            ]);
        }, 3);
    }

    public function faturayaBagliMasraflariIptalEt(Fatura $fatura): void
    {
        $neden = sprintf(
            'Bağlı gider faturası iptal edildi. Fatura ID: %d%s',
            (int) $fatura->getKey(),
            $fatura->fatura_no ? ' | Fatura No: '.$fatura->fatura_no : '',
        );

        $dagitimlar = MasrafFaturaDagitimi::query()
            ->where('firma_id', (int) $fatura->firma_id)
            ->where('fatura_id', (int) $fatura->getKey())
            ->with('masraf')
            ->get();

        foreach ($dagitimlar->pluck('masraf')->filter()->unique('id') as $masraf) {
            if ($masraf->durum !== Masraf::DURUM_AKTIF) {
                continue;
            }

            $masraf->update([
                'durum' => Masraf::DURUM_IPTAL,
                'iptal_eden_kullanici_id' => auth()->id() ? (int) auth()->id() : null,
                'iptal_nedeni' => $neden,
                'iptal_edildi_at' => now(),
            ]);
        }
    }

    private function faturaTavanTutari(Fatura $fatura): string
    {
        $odenecek = $this->tutarNormalizeEt($fatura->odenecek_tutar ?? 0, true);
        if (bccomp($odenecek, '0', 2) > 0) {
            return $odenecek;
        }

        return $this->tutarNormalizeEt($fatura->genel_toplam ?? 0, true);
    }

    private function tutarNormalizeEt(mixed $tutar, bool $sifiraIzinVer = false): string
    {
        $deger = str_replace([' ', ','], ['', '.'], trim((string) $tutar));
        if (! preg_match('/^\d{1,14}(?:\.\d{1,8})?$/', $deger)) {
            throw new IsKuraliIstisnasi('Tutar en fazla iki ondalık basamak içeren pozitif bir sayı olmalıdır.');
        }

        if (! $sifiraIzinVer && bccomp($deger, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Dağıtım tutarı sıfırdan büyük olmalıdır.');
        }

        return bcadd($deger, '0', 2);
    }
}
