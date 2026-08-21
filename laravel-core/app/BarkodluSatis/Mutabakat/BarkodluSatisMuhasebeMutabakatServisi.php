<?php

namespace App\BarkodluSatis\Mutabakat;

use App\Models\Muhasebe\BarkodluSatis;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use Carbon\CarbonInterface;

class BarkodluSatisMuhasebeMutabakatServisi
{
    /**
     * @return array{
     *   kontrol_edilen:int,
     *   toplam_sorun:int,
     *   sorunlu_kayit:int,
     *   kontrol_edilen_firma_idleri:array<int,int>,
     *   kod_dagilimi:array<string,int>,
     *   sorunlar:array<int,array<string,mixed>>
     * }
     */
    public function raporla(
        ?int $firmaId,
        CarbonInterface $baslangic,
        CarbonInterface $bitis,
        int $limit = 1000,
        bool $sadeceKritik = false
    ): array {
        $kayitlar = BarkodluSatis::query()
            ->withoutGlobalScopes()
            ->with(['cari', 'iadeler'])
            ->when($firmaId !== null, fn ($q) => $q->where('firma_id', $firmaId))
            ->whereBetween('satis_tarihi', [$baslangic->toDateTimeString(), $bitis->toDateTimeString()])
            ->orderByDesc('satis_tarihi')
            ->limit(max(1, $limit))
            ->get();

        $sorunlar = [];
        foreach ($kayitlar as $satis) {
            $satisDurumu = (string) $satis->durum;
            $odemeTipi = strtolower(trim((string) $satis->odeme_tipi));
            $beklenenTahsilat = in_array($odemeTipi, ['nakit', 'kart', 'havale'], true)
                && bccomp(number_format((float) $satis->genel_toplam, 2, '.', ''), '0.00', 2) === 1;

            $aktifFinanslar = FinansHareketi::query()
                ->withoutGlobalScopes()
                ->where('firma_id', (int) $satis->firma_id)
                ->where('referans_turu', 'barkodlu_satis')
                ->where('referans_id', (int) $satis->id)
                ->where('durum', FinansHareketDurumu::Aktif->value)
                ->get(['id', 'tutar', 'para_birimi', 'referans_turu', 'referans_id']);

            $aktifAdet = $aktifFinanslar->count();
            $aktifToplam = number_format((float) $aktifFinanslar->sum(fn ($f): float => (float) $f->tutar), 2, '.', '');
            $beklenenToplam = number_format((float) $satis->genel_toplam, 2, '.', '');
            $satisParaBirimi = strtoupper((string) ($satis->para_birimi ?: 'TRY'));

            if ($satisDurumu === 'tamamlandi' && $beklenenTahsilat && $aktifAdet === 0) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'finans_eksik',
                    'critical',
                    'Tamamlanan satis icin aktif tahsilat finans kaydi bulunamadi.',
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet
                );
                continue;
            }

            if ($satisDurumu === 'tamamlandi' && $beklenenTahsilat && $aktifAdet > 0 && bccomp($aktifToplam, $beklenenToplam, 2) !== 0) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'tutar_uyusmazligi',
                    'critical',
                    'Aktif finans toplam tutari satis genel toplamiyla uyusmuyor.',
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet
                );
            }

            if ($satisDurumu === 'tamamlandi' && $aktifAdet > 0) {
                $paraBirimiUyusmayan = $aktifFinanslar
                    ->contains(fn ($f): bool => strtoupper((string) ($f->para_birimi ?: 'TRY')) !== $satisParaBirimi);
                if ($paraBirimiUyusmayan) {
                    $sorunlar[] = $this->sorunSatiri(
                        $satis,
                        'para_birimi_uyusmazligi',
                        'warning',
                        'Satis para birimi ile finans para birimi uyusmuyor.',
                        $beklenenToplam,
                        $aktifToplam,
                        $aktifAdet
                    );
                }
            }

            if ($satisDurumu === 'iptal' && $aktifAdet > 0) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'iptalde_aktif_finans_var',
                    'critical',
                    'Iptal edilmis satisa bagli aktif finans kaydi tespit edildi.',
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet
                );
            }

            if ($satisDurumu === 'tamamlandi' && $aktifAdet > 1) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'birden_fazla_aktif_finans',
                    'warning',
                    'Ayni satis kaydi icin birden fazla aktif finans hareketi var.',
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet
                );
            }

            foreach ($this->iadeFinansSorunlariniBul($satis) as $iadeSorunu) {
                $sorunlar[] = $iadeSorunu;
            }
        }

        if ($sadeceKritik) {
            $sorunlar = array_values(array_filter(
                $sorunlar,
                static fn (array $sorun): bool => (string) ($sorun['seviye'] ?? '') === 'critical'
            ));
        }

        $kodDagilimi = [];
        $sorunluKayitlar = [];
        foreach ($sorunlar as $sorun) {
            $kod = (string) ($sorun['kod'] ?? 'tanimsiz');
            $kodDagilimi[$kod] = ($kodDagilimi[$kod] ?? 0) + 1;
            $sorunluKayitlar[(int) ($sorun['satis_id'] ?? 0)] = true;
        }

        $kontrolEdilenFirmaIdleri = $kayitlar
            ->pluck('firma_id')
            ->filter(fn ($id): bool => is_numeric((string) $id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'kontrol_edilen' => $kayitlar->count(),
            'toplam_sorun' => count($sorunlar),
            'sorunlu_kayit' => count($sorunluKayitlar),
            'kontrol_edilen_firma_idleri' => $kontrolEdilenFirmaIdleri,
            'kod_dagilimi' => $kodDagilimi,
            'sorunlar' => $sorunlar,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function iadeFinansSorunlariniBul(BarkodluSatis $satis): array
    {
        $iadeler = $satis->iadeler;
        if ($iadeler->isEmpty()) {
            return [];
        }

        $iadeIdleri = $iadeler->pluck('id')->map(fn ($id): int => (int) $id)->filter(fn (int $id): bool => $id > 0)->values();
        if ($iadeIdleri->isEmpty()) {
            return [];
        }

        $aktifIadeFinanslari = FinansHareketi::query()
            ->withoutGlobalScopes()
            ->where('firma_id', (int) $satis->firma_id)
            ->where('referans_turu', 'barkodlu_satis_iade')
            ->whereIn('referans_id', $iadeIdleri->all())
            ->where('durum', FinansHareketDurumu::Aktif->value)
            ->get(['id', 'tutar', 'para_birimi', 'referans_id']);

        $satisParaBirimi = strtoupper((string) ($satis->para_birimi ?: 'TRY'));
        $sorunlar = [];

        foreach ($iadeler as $iade) {
            $beklenenToplam = number_format((float) $iade->toplam_iade_tutari, 2, '.', '');
            if (bccomp($beklenenToplam, '0.00', 2) !== 1) {
                continue;
            }

            $iadeNo = (string) ($iade->iade_no ?? '');
            $iadeAciklama = $iadeNo !== '' ? ' Iade no: '.$iadeNo : '';
            $iadeFinanslari = $aktifIadeFinanslari->where('referans_id', (int) $iade->id);
            $aktifAdet = $iadeFinanslari->count();
            $aktifToplam = number_format((float) $iadeFinanslari->sum(fn ($f): float => (float) $f->tutar), 2, '.', '');

            if ($aktifAdet === 0) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'iade_finans_eksik',
                    'critical',
                    'Iade kaydina ait aktif finans hareketi bulunamadi.'.$iadeAciklama,
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet,
                    [
                        'referans_turu' => 'barkodlu_satis_iade',
                        'referans_id' => (int) $iade->id,
                        'iade_id' => (int) $iade->id,
                        'iade_no' => $iadeNo !== '' ? $iadeNo : null,
                    ]
                );

                continue;
            }

            if (bccomp($aktifToplam, $beklenenToplam, 2) !== 0) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'iade_tutar_uyusmazligi',
                    'critical',
                    'Iade toplam tutari ile aktif finans toplami uyusmuyor.'.$iadeAciklama,
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet,
                    [
                        'referans_turu' => 'barkodlu_satis_iade',
                        'referans_id' => (int) $iade->id,
                        'iade_id' => (int) $iade->id,
                        'iade_no' => $iadeNo !== '' ? $iadeNo : null,
                    ]
                );
            }

            $paraBirimiUyusmayan = $iadeFinanslari
                ->contains(fn ($f): bool => strtoupper((string) ($f->para_birimi ?: 'TRY')) !== $satisParaBirimi);
            if ($paraBirimiUyusmayan) {
                $sorunlar[] = $this->sorunSatiri(
                    $satis,
                    'iade_para_birimi_uyusmazligi',
                    'warning',
                    'Iade finans hareketinin para birimi satis para birimiyle uyusmuyor.'.$iadeAciklama,
                    $beklenenToplam,
                    $aktifToplam,
                    $aktifAdet,
                    [
                        'referans_turu' => 'barkodlu_satis_iade',
                        'referans_id' => (int) $iade->id,
                        'iade_id' => (int) $iade->id,
                        'iade_no' => $iadeNo !== '' ? $iadeNo : null,
                    ]
                );
            }
        }

        return $sorunlar;
    }

    /**
     * @return array<string,mixed>
     */
    private function sorunSatiri(
        BarkodluSatis $satis,
        string $kod,
        string $seviye,
        string $detay,
        string $beklenenToplam,
        string $aktifToplam,
        int $aktifAdet,
        array $ek = []
    ): array {
        $satir = [
            'kod' => $kod,
            'seviye' => $seviye,
            'detay' => $detay,
            'firma_id' => (int) $satis->firma_id,
            'satis_id' => (int) $satis->id,
            'satis_no' => (string) $satis->satis_no,
            'referans_turu' => 'barkodlu_satis',
            'referans_id' => (int) $satis->id,
            'iade_id' => null,
            'iade_no' => null,
            'satis_tarihi' => optional($satis->satis_tarihi)->format('Y-m-d H:i:s'),
            'cari' => (string) ($satis->cari?->ad ?? '-'),
            'odeme_tipi' => (string) $satis->odeme_tipi,
            'durum' => (string) $satis->durum,
            'beklenen_tutar' => $beklenenToplam,
            'aktif_finans_toplami' => $aktifToplam,
            'aktif_finans_adedi' => $aktifAdet,
        ];

        foreach ($ek as $anahtar => $deger) {
            if (! is_string($anahtar) || $anahtar === '') {
                continue;
            }
            $satir[$anahtar] = $deger;
        }

        return $satir;
    }
}
