<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\AlacakPlanTaksiti;
use App\Models\Muhasebe\AlacakTakipNotu;
use App\Services\SistemOlayServisi;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class AlacakTakipNotuServisi
{
    public function __construct(private readonly SistemOlayServisi $sistemOlayServisi)
    {
    }

    /**
     * @param iterable<int, AlacakPlanTaksiti> $taksitler
     * @param array<string, mixed> $data
     * @return array{olusturulan:int, atlanan:int, cari_adedi:int, toplam_tutarlar:array<string,string>}
     */
    public function topluOlustur(iterable $taksitler, array $data): array
    {
        $olusturulan = 0;
        $atlanan = 0;
        $cariIdleri = [];
        $toplamTutarlar = [];
        $firmaId = 0;

        DB::transaction(function () use ($taksitler, $data, &$olusturulan, &$atlanan, &$cariIdleri, &$toplamTutarlar, &$firmaId): void {
            foreach ($taksitler as $taksit) {
                if (! $taksit instanceof AlacakPlanTaksiti) {
                    $atlanan++;
                    continue;
                }

                $taksit->loadMissing('plan');
                if (! $this->takipNotunaUygunMu($taksit)) {
                    $atlanan++;
                    continue;
                }

                $not = $this->olustur($taksit, $data);
                $olusturulan++;
                $firmaId = (int) $not->firma_id;
                $cariIdleri[(int) $not->cari_id] = true;
                $paraBirimi = strtoupper((string) ($not->para_birimi ?: 'TRY'));
                $toplamTutarlar[$paraBirimi] = bcadd(
                    (string) ($toplamTutarlar[$paraBirimi] ?? '0.00'),
                    number_format((float) ($not->beklenen_tutar ?? 0), 2, '.', ''),
                    2
                );
            }
        });

        if ($olusturulan > 0) {
            $this->sistemOlayServisi->olayKaydet(
                tip: 'muhasebe.alacak_takip_notu_toplu',
                seviye: 'info',
                mesaj: 'Toplu alacak takip notu olusturuldu.',
                context: [
                    'firma_id' => $firmaId ?: null,
                    'olusturulan' => $olusturulan,
                    'atlanan' => $atlanan,
                    'cari_adedi' => count($cariIdleri),
                    'takip_tipi' => (string) ($data['takip_tipi'] ?? 'not'),
                    'durum' => (string) ($data['durum'] ?? 'planlandi'),
                    'toplam_tutarlar' => $toplamTutarlar,
                ]
            );
        }

        return [
            'olusturulan' => $olusturulan,
            'atlanan' => $atlanan,
            'cari_adedi' => count($cariIdleri),
            'toplam_tutarlar' => $toplamTutarlar,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function olustur(AlacakPlanTaksiti $taksit, array $data): AlacakTakipNotu
    {
        $taksit->loadMissing('plan');
        $durum = (string) ($data['durum'] ?? 'planlandi');
        $beklenenTutar = number_format((float) ($data['beklenen_tutar'] ?? $taksit->kalan_tutar), 2, '.', '');
        $odemeSozuTarihi = $data['odeme_sozu_tarihi'] ?? null;
        $odemeSozuTutari = (float) ($data['odeme_sozu_tutari'] ?? 0) > 0
            ? number_format((float) $data['odeme_sozu_tutari'], 2, '.', '')
            : ($durum === 'odeme_sozu' ? $beklenenTutar : null);

        return AlacakTakipNotu::query()->create([
            'firma_id' => (int) $taksit->firma_id,
            'cari_id' => (int) $taksit->cari_id,
            'alacak_plan_id' => (int) $taksit->alacak_plan_id,
            'alacak_plan_taksiti_id' => (int) $taksit->getKey(),
            'takip_tipi' => (string) ($data['takip_tipi'] ?? 'not'),
            'durum' => $durum,
            'takip_tarihi' => $data['takip_tarihi'] ?? now(),
            'sonraki_takip_tarihi' => $data['sonraki_takip_tarihi'] ?? null,
            'odeme_sozu_tarihi' => $odemeSozuTarihi,
            'odeme_sozu_tutari' => $odemeSozuTutari,
            'odeme_sozu_durumu' => $durum === 'odeme_sozu' ? (string) ($data['odeme_sozu_durumu'] ?? 'bekliyor') : null,
            'beklenen_tutar' => $beklenenTutar,
            'para_birimi' => strtoupper((string) ($taksit->plan?->para_birimi ?: 'TRY')),
            'not' => trim((string) ($data['not'] ?? '')),
            'sonuc_notu' => trim((string) ($data['sonuc_notu'] ?? '')),
            'olusturan_id' => (int) ($data['olusturan_id'] ?? auth()->id() ?? 0) ?: null,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function guncelle(AlacakTakipNotu $takipNotu, array $data): AlacakTakipNotu
    {
        return DB::transaction(function () use ($takipNotu, $data): AlacakTakipNotu {
            $takipNotu = AlacakTakipNotu::query()
                ->whereKey($takipNotu->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $durum = (string) ($data['durum'] ?? $takipNotu->durum ?? 'planlandi');
            $kapanisTarihi = in_array($durum, ['tamamlandi', 'ulasildi'], true)
                ? ($data['kapanis_tarihi'] ?? now())
                : ($data['kapanis_tarihi'] ?? $takipNotu->kapanis_tarihi);
            $odemeSozuTutari = (float) ($data['odeme_sozu_tutari'] ?? 0) > 0
                ? number_format((float) $data['odeme_sozu_tutari'], 2, '.', '')
                : ($durum === 'odeme_sozu' ? (string) ($takipNotu->odeme_sozu_tutari ?? $takipNotu->beklenen_tutar ?? '0') : null);

            $takipNotu->update([
                'takip_tipi' => (string) ($data['takip_tipi'] ?? $takipNotu->takip_tipi),
                'durum' => $durum,
                'takip_tarihi' => $data['takip_tarihi'] ?? $takipNotu->takip_tarihi ?? now(),
                'sonraki_takip_tarihi' => $data['sonraki_takip_tarihi'] ?? null,
                'odeme_sozu_tarihi' => $durum === 'odeme_sozu' ? ($data['odeme_sozu_tarihi'] ?? $takipNotu->odeme_sozu_tarihi) : null,
                'odeme_sozu_tutari' => $odemeSozuTutari,
                'odeme_sozu_durumu' => $durum === 'odeme_sozu' ? (string) ($data['odeme_sozu_durumu'] ?? $takipNotu->odeme_sozu_durumu ?? 'bekliyor') : null,
                'beklenen_tutar' => number_format((float) ($data['beklenen_tutar'] ?? $takipNotu->beklenen_tutar ?? 0), 2, '.', ''),
                'not' => trim((string) ($data['not'] ?? $takipNotu->not ?? '')),
                'sonuc_notu' => trim((string) ($data['sonuc_notu'] ?? $takipNotu->sonuc_notu ?? '')),
                'kapanis_tarihi' => $kapanisTarihi,
            ]);

            $this->sistemOlayServisi->olayKaydet(
                tip: 'muhasebe.alacak_takip_notu_guncellendi',
                seviye: 'info',
                mesaj: 'Alacak takip notu guncellendi.',
                context: [
                    'firma_id' => (int) $takipNotu->firma_id,
                    'takip_notu_id' => (int) $takipNotu->getKey(),
                    'durum' => $durum,
                    'odeme_sozu_durumu' => $takipNotu->odeme_sozu_durumu,
                ]
            );

            return $takipNotu->fresh(['taksit', 'plan']) ?? $takipNotu;
        });
    }

    public function kapat(AlacakTakipNotu $takipNotu, ?string $sonucNotu = null): AlacakTakipNotu
    {
        return $this->guncelle($takipNotu, [
            'durum' => 'tamamlandi',
            'sonuc_notu' => $sonucNotu ?? 'Takip tamamlandi.',
            'sonraki_takip_tarihi' => null,
            'kapanis_tarihi' => now(),
        ]);
    }

    public function sonrakiTakibiAyarla(AlacakTakipNotu $takipNotu, mixed $sonrakiTakipTarihi, ?string $sonucNotu = null): AlacakTakipNotu
    {
        return $this->guncelle($takipNotu, [
            'durum' => 'takip_gerekli',
            'sonraki_takip_tarihi' => $sonrakiTakipTarihi,
            'sonuc_notu' => $sonucNotu ?? 'Sonraki takip tarihi guncellendi.',
        ]);
    }

    public function odemeSozuDurumunuGuncelle(AlacakPlanTaksiti $taksit, string $uygulananTutar, mixed $tarih = null): void
    {
        $notlar = AlacakTakipNotu::query()
            ->where('firma_id', (int) $taksit->firma_id)
            ->where('alacak_plan_taksiti_id', (int) $taksit->getKey())
            ->where('durum', 'odeme_sozu')
            ->whereIn('odeme_sozu_durumu', ['bekliyor', 'kismi'])
            ->orderBy('odeme_sozu_tarihi')
            ->get();

        foreach ($notlar as $not) {
            $sozTutari = number_format((float) ($not->odeme_sozu_tutari ?? $not->beklenen_tutar ?? 0), 2, '.', '');
            $durum = bccomp(number_format((float) $taksit->kalan_tutar, 2, '.', ''), '0.00', 2) <= 0
                || bccomp(number_format((float) $uygulananTutar, 2, '.', ''), $sozTutari, 2) >= 0
                    ? 'tutuldu'
                    : 'kismi';

            $not->update([
                'odeme_sozu_durumu' => $durum,
                'kapanis_tarihi' => $durum === 'tutuldu' ? ($tarih ?? now()) : null,
                'sonuc_notu' => $durum === 'tutuldu'
                    ? 'Odeme sozu tahsilatla kapandi.'
                    : 'Odeme sozu icin kismi tahsilat alindi.',
            ]);
        }
    }

    public function takipNotunaUygunMu(AlacakPlanTaksiti $taksit): bool
    {
        if ((float) $taksit->kalan_tutar <= 0) {
            return false;
        }

        if (in_array((string) $taksit->durum, ['odendi', 'iptal'], true)) {
            return false;
        }

        $planDurumu = (string) ($taksit->plan?->durum ?? '');

        return in_array($planDurumu, ['aktif', 'kismi_odendi', 'gecikti'], true);
    }

    /**
     * @param EloquentCollection<int, AlacakPlanTaksiti> $records
     * @return EloquentCollection<int, AlacakPlanTaksiti>
     */
    public function siraliTaksitler(EloquentCollection $records): EloquentCollection
    {
        return $records
            ->loadMissing('plan')
            ->sortBy([
                ['vade_tarihi', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }
}
