<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketEslesmesi;
use App\Models\Muhasebe\CariHareketi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Cari hareketleri için FIFO açık kalem eşlemesi.
 *
 * Aynı cari için {@see cariFifoKilidiyle} ile `cariler` satırı üzerinde lockForUpdate
 * kullanılır (InnoDB); paralel tahsilat/fatura eşlemesi güvenli sıralanır.
 */
class CariHareketFifoEslestirmeServisi
{
    private const PARA_BASAMAK = 8;
    private const SIFIR_TUTAR = '0.00000000';

    public function toplamEslesenBorcTarafindan(int $hareketId): string
    {
        $v = CariHareketEslesmesi::query()
            ->where('borc_hareket_id', $hareketId)
            ->sum('eslesen_tutar');

        return $this->formatMoney((string) $v);
    }

    public function toplamEslesenAlacakTarafindan(int $hareketId): string
    {
        $v = CariHareketEslesmesi::query()
            ->where('alacak_hareket_id', $hareketId)
            ->sum('eslesen_tutar');

        return $this->formatMoney((string) $v);
    }

    /**
     * Çoklu hareket için eşleşme toplamları (2 sorgu; N hareket için 2N sorgu yerine).
     *
     * @param  array<int, int>  $hareketIdleri
     * @return array{borc_taraf: array<int, string>, alacak_taraf: array<int, string>}
     */
    public function toplamEslesenToplamlariHareketBasina(array $hareketIdleri): array
    {
        $bos = ['borc_taraf' => [], 'alacak_taraf' => []];
        $ids = array_values(array_unique(array_filter(array_map('intval', $hareketIdleri))));
        if ($ids === []) {
            return $bos;
        }

        $borcTaraf = [];
        $alacakTaraf = [];

        foreach (array_chunk($ids, 1000) as $parca) {
            $borcSatirlar = CariHareketEslesmesi::query()
                ->whereIn('borc_hareket_id', $parca)
                ->selectRaw('borc_hareket_id, COALESCE(SUM(eslesen_tutar), 0) as toplam')
                ->groupBy('borc_hareket_id')
                ->get();

            foreach ($borcSatirlar as $s) {
                $hid = (int) $s->borc_hareket_id;
                $borcTaraf[$hid] = $this->formatMoney((string) $s->toplam);
            }

            $alacakSatirlar = CariHareketEslesmesi::query()
                ->whereIn('alacak_hareket_id', $parca)
                ->selectRaw('alacak_hareket_id, COALESCE(SUM(eslesen_tutar), 0) as toplam')
                ->groupBy('alacak_hareket_id')
                ->get();

            foreach ($alacakSatirlar as $s) {
                $hid = (int) $s->alacak_hareket_id;
                $alacakTaraf[$hid] = $this->formatMoney((string) $s->toplam);
            }
        }

        return [
            'borc_taraf' => $borcTaraf,
            'alacak_taraf' => $alacakTaraf,
        ];
    }

    /**
     * Borç sütununda kalan eşlenmemiş tutar (tahsilat / gelen fatura borcu).
     */
    public function acikBorcKapasitesi(CariHareketi $h): string
    {
        $borc = $this->formatMoney((string) $h->borc);
        $es = $this->toplamEslesenBorcTarafindan((int) $h->getKey());
        $kalan = bcsub($borc, $es, self::PARA_BASAMAK);

        return bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) < 0 ? self::SIFIR_TUTAR : $kalan;
    }

    /**
     * Alacak sütununda kalan eşlenmemiş tutar (giden fatura alacağı / ödeme).
     */
    public function acikAlacakKapasitesi(CariHareketi $h): string
    {
        $alacak = $this->formatMoney((string) $h->alacak);
        $es = $this->toplamEslesenAlacakTarafindan((int) $h->getKey());
        $kalan = bcsub($alacak, $es, self::PARA_BASAMAK);

        return bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) < 0 ? self::SIFIR_TUTAR : $kalan;
    }

    public function yeniHareketSonrasiOtomatikEsle(CariHareketi $h): void
    {
        if ($h->durum !== CariHareketDurumu::Aktif) {
            return;
        }

        try {
            $this->cariFifoKilidiyle((int) $h->firma_id, (int) $h->cari_id, function () use ($h): void {
                $hareket = $h->fresh();
                if (! $hareket || $hareket->durum !== CariHareketDurumu::Aktif) {
                    return;
                }

                match ($hareket->belge_turu) {
                    CariHareketBelgeTuru::Tahsilat => $this->esleTahsilatFifo($hareket),
                    CariHareketBelgeTuru::Odeme => $this->esleOdemeFifo($hareket),
                    CariHareketBelgeTuru::Fatura => $this->esleYeniFaturaFifo($hareket),
                    CariHareketBelgeTuru::Satis => $this->esleYeniFaturaFifo($hareket),
                    default => null,
                };
            });
        } catch (Throwable $e) {
            $this->fifoLog('error', 'cari.fifo.yeni_hareket_hata', [
                'firma_id' => $h->firma_id,
                'cari_id' => $h->cari_id,
                'hareket_id' => $h->getKey(),
                'belge_turu' => $h->belge_turu?->value,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * İptal edilen (veya silinecek) hareketle ilgili tüm eşleşmeleri kaldırır.
     */
    public function iptalEdilenHareketEslesmeleriniSil(CariHareketi $hareket): void
    {
        $this->cariFifoKilidiyle((int) $hareket->firma_id, (int) $hareket->cari_id, function () use ($hareket): void {
            $silinen = CariHareketEslesmesi::query()
                ->where('firma_id', $hareket->firma_id)
                ->where(function ($q) use ($hareket): void {
                    $q->where('borc_hareket_id', $hareket->getKey())
                        ->orWhere('alacak_hareket_id', $hareket->getKey());
                })
                ->delete();

            if ($silinen > 0) {
                $this->fifoLog($this->fifoRutinSeviyesi(), 'cari.fifo.eslesme_silindi_iptal', [
                    'firma_id' => $hareket->firma_id,
                    'cari_id' => $hareket->cari_id,
                    'hareket_id' => $hareket->getKey(),
                    'silinen_satir' => $silinen,
                ]);
            }
        });
    }

    /**
     * Aynı cari için FIFO kritik bölgesi: cari satırı kilitlenir (MySQL/InnoDB row lock).
     * Zaten bir transaction içindeysek yeni transaction açılmaz (savepoint çoğalmasın diye).
     */
    private function cariFifoKilidiyle(int $firmaId, int $cariId, Closure $islem): void
    {
        $calistir = function () use ($firmaId, $cariId, $islem): void {
            $cari = Cari::query()
                ->whereKey($cariId)
                ->where('firma_id', $firmaId)
                ->lockForUpdate()
                ->first();

            if (! $cari) {
                $this->fifoLog('warning', 'cari.fifo.cari_kilitlenemedi', [
                    'firma_id' => $firmaId,
                    'cari_id' => $cariId,
                ]);

                return;
            }

            $islem();
        };

        if (DB::transactionLevel() > 0) {
            $calistir();
        } else {
            DB::transaction($calistir);
        }
    }

    private function esleTahsilatFifo(CariHareketi $tahsilat): void
    {
        if (bccomp($this->formatMoney((string) $tahsilat->borc), self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $kalan = $this->acikBorcKapasitesi($tahsilat);
        if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $adaylar = $this->acikAlacakFaturaSatirlari($tahsilat);

        foreach ($adaylar as $faturaSatir) {
            if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                break;
            }
            $faturaSatir->refresh();
            $acikFatura = $this->acikAlacakKapasitesi($faturaSatir);
            if (bccomp($acikFatura, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $m = $this->minBc($kalan, $acikFatura);
            if (bccomp($m, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $this->eslesmeKaydet($tahsilat, $faturaSatir, $m);
            $kalan = bcsub($kalan, $m, self::PARA_BASAMAK);
        }
    }

    private function esleOdemeFifo(CariHareketi $odeme): void
    {
        if (bccomp($this->formatMoney((string) $odeme->alacak), self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $kalan = $this->acikAlacakKapasitesi($odeme);
        if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $adaylar = $this->acikBorcFaturaSatirlari($odeme);

        foreach ($adaylar as $faturaSatir) {
            if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                break;
            }
            $faturaSatir->refresh();
            $acikFatura = $this->acikBorcKapasitesi($faturaSatir);
            if (bccomp($acikFatura, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $m = $this->minBc($kalan, $acikFatura);
            if (bccomp($m, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $this->eslesmeKaydet($faturaSatir, $odeme, $m);
            $kalan = bcsub($kalan, $m, self::PARA_BASAMAK);
        }
    }

    private function esleYeniFaturaFifo(CariHareketi $fatura): void
    {
        if (bccomp($this->formatMoney((string) $fatura->alacak), self::SIFIR_TUTAR, self::PARA_BASAMAK) > 0) {
            $this->esleFaturaAlacaginaKalanTahsilatlar($fatura);
        }

        if (bccomp($this->formatMoney((string) $fatura->borc), self::SIFIR_TUTAR, self::PARA_BASAMAK) > 0) {
            $this->esleFaturaBorcunaKalanOdemeler($fatura);
        }
    }

    private function esleFaturaAlacaginaKalanTahsilatlar(CariHareketi $fatura): void
    {
        $kalan = $this->acikAlacakKapasitesi($fatura);
        if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $adaylar = CariHareketi::query()
            ->where('firma_id', $fatura->firma_id)
            ->where('cari_id', $fatura->cari_id)
            ->where('para_birimi', $fatura->para_birimi)
            ->where('durum', CariHareketDurumu::Aktif)
            ->where('belge_turu', CariHareketBelgeTuru::Tahsilat)
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get();

        foreach ($adaylar as $tahsilat) {
            if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                break;
            }
            $tahsilat->refresh();
            $acikTahsilat = $this->acikBorcKapasitesi($tahsilat);
            if (bccomp($acikTahsilat, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $m = $this->minBc($kalan, $acikTahsilat);
            if (bccomp($m, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $this->eslesmeKaydet($tahsilat, $fatura, $m);
            $kalan = bcsub($kalan, $m, self::PARA_BASAMAK);
        }
    }

    private function esleFaturaBorcunaKalanOdemeler(CariHareketi $fatura): void
    {
        $kalan = $this->acikBorcKapasitesi($fatura);
        if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
            return;
        }

        $adaylar = CariHareketi::query()
            ->where('firma_id', $fatura->firma_id)
            ->where('cari_id', $fatura->cari_id)
            ->where('para_birimi', $fatura->para_birimi)
            ->where('durum', CariHareketDurumu::Aktif)
            ->where('belge_turu', CariHareketBelgeTuru::Odeme)
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get();

        foreach ($adaylar as $odeme) {
            if (bccomp($kalan, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                break;
            }
            $odeme->refresh();
            $acikOdeme = $this->acikAlacakKapasitesi($odeme);
            if (bccomp($acikOdeme, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $m = $this->minBc($kalan, $acikOdeme);
            if (bccomp($m, self::SIFIR_TUTAR, self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $this->eslesmeKaydet($fatura, $odeme, $m);
            $kalan = bcsub($kalan, $m, self::PARA_BASAMAK);
        }
    }

    /**
     * @return Collection<int, CariHareketi>
     */
    private function acikAlacakFaturaSatirlari(CariHareketi $tahsilat)
    {
        return CariHareketi::query()
            ->where('firma_id', $tahsilat->firma_id)
            ->where('cari_id', $tahsilat->cari_id)
            ->where('para_birimi', $tahsilat->para_birimi)
            ->where('durum', CariHareketDurumu::Aktif)
            ->whereIn('belge_turu', [CariHareketBelgeTuru::Fatura, CariHareketBelgeTuru::Satis])
            ->where('alacak', '>', 0)
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get()
            ->filter(fn (CariHareketi $s) => bccomp($this->acikAlacakKapasitesi($s), self::SIFIR_TUTAR, self::PARA_BASAMAK) > 0)
            ->values();
    }

    /**
     * @return Collection<int, CariHareketi>
     */
    private function acikBorcFaturaSatirlari(CariHareketi $odeme)
    {
        return CariHareketi::query()
            ->where('firma_id', $odeme->firma_id)
            ->where('cari_id', $odeme->cari_id)
            ->where('para_birimi', $odeme->para_birimi)
            ->where('durum', CariHareketDurumu::Aktif)
            ->where('belge_turu', CariHareketBelgeTuru::Fatura)
            ->where('borc', '>', 0)
            ->orderBy('islem_tarihi')
            ->orderBy('id')
            ->get()
            ->filter(fn (CariHareketi $s) => bccomp($this->acikBorcKapasitesi($s), self::SIFIR_TUTAR, self::PARA_BASAMAK) > 0)
            ->values();
    }

    private function eslesmeKaydet(CariHareketi $borcTaraf, CariHareketi $alacakTaraf, string $tutar): void
    {
        if ((int) $borcTaraf->firma_id !== (int) $alacakTaraf->firma_id) {
            Log::warning('cari.fifo.eslesme_reddedildi_firma', [
                'borc_hareket_id' => $borcTaraf->getKey(),
                'alacak_hareket_id' => $alacakTaraf->getKey(),
                'tutar' => $tutar,
            ]);

            return;
        }
        if ((int) $borcTaraf->cari_id !== (int) $alacakTaraf->cari_id) {
            $this->fifoLog('warning', 'cari.fifo.eslesme_reddedildi_cari', [
                'borc_hareket_id' => $borcTaraf->getKey(),
                'alacak_hareket_id' => $alacakTaraf->getKey(),
            ]);

            return;
        }
        if ($borcTaraf->para_birimi !== $alacakTaraf->para_birimi) {
            $this->fifoLog('warning', 'cari.fifo.eslesme_reddedildi_para_birimi', [
                'borc_hareket_id' => $borcTaraf->getKey(),
                'alacak_hareket_id' => $alacakTaraf->getKey(),
            ]);

            return;
        }

        $kayit = CariHareketEslesmesi::query()->create([
            'firma_id' => (int) $borcTaraf->firma_id,
            'borc_hareket_id' => (int) $borcTaraf->getKey(),
            'alacak_hareket_id' => (int) $alacakTaraf->getKey(),
            'eslesen_tutar' => $tutar,
            'created_at' => now(),
        ]);

        Log::info('cari.fifo.eslesme_olusturuldu', [
            'firma_id' => $kayit->firma_id,
            'cari_id' => $borcTaraf->cari_id,
            'eslesme_id' => $kayit->getKey(),
            'borc_hareket_id' => $kayit->borc_hareket_id,
            'alacak_hareket_id' => $kayit->alacak_hareket_id,
            'eslesen_tutar' => $tutar,
        ]);
    }

    private function minBc(string $a, string $b): string
    {
        return bccomp($a, $b, self::PARA_BASAMAK) <= 0 ? $a : $b;
    }

    private function formatMoney(string $v): string
    {
        return number_format((float) $v, self::PARA_BASAMAK, '.', '');
    }

    private function fifoLogger(): LoggerInterface
    {
        $ch = config('muhasebe.fifo.log_channel');

        if (is_string($ch) && $ch !== '') {
            return Log::channel($ch);
        }

        return Log::driver();
    }

    private function fifoRutinSeviyesi(): string
    {
        return config('muhasebe.fifo.rutin_info_seviyesi', false) ? 'info' : 'debug';
    }

    /**
     * @param  array<string, mixed>  $baglam
     */
    private function fifoLog(string $seviye, string $mesaj, array $baglam = []): void
    {
        $this->fifoLogger()->log($seviye, $mesaj, $baglam);
    }
}
