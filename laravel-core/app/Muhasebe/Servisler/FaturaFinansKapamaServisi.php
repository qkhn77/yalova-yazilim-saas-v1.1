<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use App\Services\SistemOlayServisi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FaturaFinansKapamaServisi
{
    private const PARA_BASAMAK = 8;

    public function __construct(
        private readonly FaturaKapamaDogrulamaServisi $faturaKapamaDogrulamaServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
        private readonly KurFarkiHareketServisi $kurFarkiHareketServisi,
    ) {}

    public function finansHareketiniFaturayaUygula(FinansHareketi $finans): void
    {
        if ($finans->referans_turu !== 'fatura' || $finans->referans_id === null) {
            return;
        }
        if ($finans->durum !== FinansHareketDurumu::Aktif) {
            return;
        }

        $mevcut = FaturaFinansKapama::query()
            ->where('finans_hareket_id', $finans->id)
            ->where('fatura_id', (int) $finans->referans_id)
            ->exists();
        if ($mevcut) {
            $this->faturaOdemeDurumunuYenile((int) $finans->referans_id);

            return;
        }

        $fatura = Fatura::query()->findOrFail((int) $finans->referans_id);
        $acik = (string) ($fatura->acik_tutar ?? $fatura->odenecek_tutar ?? $fatura->genel_toplam ?? '0');
        if (bccomp($acik, '0', self::PARA_BASAMAK) <= 0) {
            $finans->update(['kullanilan_tutar' => '0.00000000', 'avans_tutar' => (string) $finans->tutar]);

            return;
        }
        $uygulanacak = bccomp((string) $finans->tutar, $acik, self::PARA_BASAMAK) === 1 ? $acik : (string) $finans->tutar;
        $this->finansiFaturalaraDagit($finans, [
            ['fatura_id' => (int) $finans->referans_id, 'tutar' => $uygulanacak],
        ]);
    }

    /**
     * @param  array<int,array{fatura_id:int,tutar:string|float|int}>  $dagitimlar
     */
    public function finansiFaturalaraDagit(FinansHareketi|int $finans, array $dagitimlar): void
    {
        DB::transaction(function () use ($finans, $dagitimlar): void {
            $finans = $finans instanceof FinansHareketi
                ? FinansHareketi::query()->withoutGlobalScopes()->lockForUpdate()->whereKey($finans->id)->firstOrFail()
                : FinansHareketi::query()->withoutGlobalScopes()->lockForUpdate()->whereKey($finans)->firstOrFail();

            if ($finans->durum !== FinansHareketDurumu::Aktif) {
                throw new IsKuraliIstisnasi('Pasif finans hareketi dağıtılamaz.');
            }
            if ($dagitimlar === []) {
                throw new IsKuraliIstisnasi('En az bir fatura dağıtımı girilmelidir.');
            }

            $faturaIdler = array_map(fn ($d) => (int) $d['fatura_id'], $dagitimlar);
            if (count($faturaIdler) !== count(array_unique($faturaIdler))) {
                throw new IsKuraliIstisnasi('Aynı finans içinde aynı faturaya duplicate dağıtım yapılamaz.');
            }

            $mevcutKullanilan = (string) (FaturaFinansKapama::query()
                ->where('finans_hareket_id', $finans->id)
                ->sum('uygulanan_tutar'));
            $toplamDagitim = '0.00000000';
            foreach ($dagitimlar as $d) {
                $tutar = (string) $d['tutar'];
                if (! is_numeric($tutar) || bccomp($tutar, '0', self::PARA_BASAMAK) <= 0) {
                    throw new IsKuraliIstisnasi('Negatif/sıfır kapama tutarı kullanılamaz.');
                }
                $toplamDagitim = bcadd($toplamDagitim, $tutar, self::PARA_BASAMAK);
            }
            $kalan = bcsub((string) $finans->tutar, $mevcutKullanilan, self::PARA_BASAMAK);
            if (bccomp($kalan, '0', self::PARA_BASAMAK) < 0) {
                throw new IsKuraliIstisnasi('Finans kapama bakiyesi negatif görünüyor. İnceleme gerekli.');
            }
            if (bccomp($toplamDagitim, $kalan, self::PARA_BASAMAK) === 1) {
                throw new IsKuraliIstisnasi('Dağıtım toplamı finans tutarını aşamaz.');
            }

            foreach ($dagitimlar as $d) {
                $fatura = Fatura::query()->lockForUpdate()->whereKey((int) $d['fatura_id'])->firstOrFail();
                if ((int) $fatura->firma_id !== (int) $finans->firma_id) {
                    throw new IsKuraliIstisnasi('Farklı firma finans/fatura dağıtımı yapılamaz.');
                }
                if ((string) $fatura->para_birimi !== (string) $finans->para_birimi) {
                    throw new IsKuraliIstisnasi('Fatura ve finans para birimi farklı.');
                }
                if (! $fatura->tur->kayitUretirMi()) {
                    throw new IsKuraliIstisnasi('Proforma/bekleyen/iptal faturalar kapamaya girmez.');
                }
                $acikTutar = (string) ($fatura->acik_tutar ?? $fatura->odenecek_tutar ?? $fatura->genel_toplam ?? '0');
                if (bccomp((string) $d['tutar'], $acikTutar, self::PARA_BASAMAK) === 1) {
                    throw new IsKuraliIstisnasi('Dağıtım tutarı fatura açık tutarını aşamaz.');
                }

                $cariYonu = $fatura->tur->cariYonu();
                if (($cariYonu === 'alacak' && $finans->tur !== FinansHareketTuru::Tahsilat)
                    || ($cariYonu === 'borc' && $finans->tur !== FinansHareketTuru::Odeme)) {
                    throw new IsKuraliIstisnasi('Fatura türü ile finans hareket türü uyumlu değil.');
                }
                if (FaturaFinansKapama::query()->where('finans_hareket_id', $finans->id)->where('fatura_id', $fatura->id)->exists()) {
                    throw new IsKuraliIstisnasi('Aynı finans-fatura için duplicate kapama engellendi.');
                }

                $kapama = FaturaFinansKapama::query()->create([
                    'firma_id' => $fatura->firma_id,
                    'fatura_id' => $fatura->id,
                    'finans_hareket_id' => $finans->id,
                    'uygulanan_tutar' => (string) $d['tutar'],
                    'para_birimi' => (string) $finans->para_birimi,
                    'baz_uygulanan_tutar' => $this->bazUygulananTutarHesapla($finans, (string) $d['tutar']),
                    'baz_fatura_tutari' => $this->bazFaturaTutariHesapla($fatura, (string) $d['tutar']),
                    'kur_farki_tutari' => $this->kurFarkiTutariHesapla($fatura, $finans, (string) $d['tutar']),
                    'baz_para_birimi' => (string) ($finans->baz_para_birimi ?: $finans->para_birimi),
                    'kur' => (string) ($finans->kur ?: '1.00000000'),
                ]);
                $this->kurFarkiHareketServisi->kapamadanOlustur($kapama);

                $this->faturaOdemeDurumunuYenile($fatura->id);
                $this->faturaKapamaDogrulamaServisi->faturaKapamaDurumuDogrula($fatura->id);
            }

            $kullanilan = (string) (FaturaFinansKapama::query()->where('finans_hareket_id', $finans->id)->sum('uygulanan_tutar'));
            $avans = bcsub((string) $finans->tutar, $kullanilan, self::PARA_BASAMAK);
            if (bccomp($avans, '0', self::PARA_BASAMAK) < 0) {
                throw new IsKuraliIstisnasi('Kapama sonrası negatif avans oluştu; dağıtım tutarsız.');
            }
            $finans->update(['kullanilan_tutar' => $kullanilan, 'avans_tutar' => $avans]);
        });
    }

    /**
     * @return array<int,array{fatura_id:int,tutar:string}>
     */
    public function onerilenDagitimOlustur(FinansHareketi|int $finans, ?string $strateji = null): array
    {
        $finans = $finans instanceof FinansHareketi
            ? FinansHareketi::query()->withoutGlobalScopes()->findOrFail($finans->id)
            : FinansHareketi::query()->withoutGlobalScopes()->findOrFail($finans);

        $strateji ??= (string) config('muhasebe.otomasyon.dagitim_stratejisi', 'fifo');

        $kalan = $this->finansKalanTutari($finans);
        if (bccomp($kalan, '0', self::PARA_BASAMAK) <= 0) {
            return [];
        }

        $tersten = $strateji === 'tarih';
        $tarihYon = $tersten ? 'desc' : 'asc';
        $idYon = $tersten ? 'desc' : 'asc';

        $zatenBagliFaturaIdleri = FaturaFinansKapama::query()
            ->where('finans_hareket_id', $finans->id)
            ->pluck('fatura_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $faturaSorgu = Fatura::query()
            ->where('firma_id', $finans->firma_id)
            ->where('cari_id', $finans->cari_id)
            ->where('para_birimi', $finans->para_birimi)
            ->whereNotIn('odeme_durumu', ['odendi', 'iptal', 'iade'])
            ->where('durum', FaturaDurumu::Onayli->value)
            ->orderBy('tarih', $tarihYon)
            ->orderBy('id', $idYon);

        $oneriler = [];
        foreach ($faturaSorgu->get(['id', 'acik_tutar', 'tur']) as $fatura) {
            if (in_array((int) $fatura->id, $zatenBagliFaturaIdleri, true)) {
                continue;
            }
            if (! $fatura->tur->kayitUretirMi()) {
                continue;
            }
            if (! $this->faturaFinansTurUyumu($fatura, $finans)) {
                continue;
            }
            $acik = (string) ($fatura->acik_tutar ?? '0');
            if (bccomp($acik, '0', self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $uygulanacak = bccomp($kalan, $acik, self::PARA_BASAMAK) >= 0 ? $acik : $kalan;
            if (bccomp($uygulanacak, '0', self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $oneriler[] = ['fatura_id' => (int) $fatura->id, 'tutar' => $uygulanacak];
            $kalan = bcsub($kalan, $uygulanacak, self::PARA_BASAMAK);
            if (bccomp($kalan, '0', self::PARA_BASAMAK) <= 0) {
                break;
            }
        }

        return $oneriler;
    }

    public function finansHareketSonrasiOtomatikDagitim(FinansHareketi $finans): void
    {
        if (! (bool) config('muhasebe.otomasyon.finans_otomatik_dagitim', true)) {
            return;
        }
        if ($finans->cari_id === null || (int) $finans->cari_id <= 0) {
            return;
        }
        if (! in_array($finans->tur, [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme], true)) {
            return;
        }
        if ($finans->durum !== FinansHareketDurumu::Aktif) {
            return;
        }

        $finans->refresh();
        $kalan = $this->finansKalanTutari($finans);
        if (bccomp($kalan, '0', self::PARA_BASAMAK) <= 0) {
            return;
        }

        $strateji = (string) config('muhasebe.otomasyon.dagitim_stratejisi', 'fifo');
        $oneriler = $this->onerilenDagitimOlustur($finans, $strateji);
        if ($oneriler === []) {
            return;
        }

        try {
            $this->finansiFaturalaraDagit($finans, $oneriler);
            $this->logOtomasyon('otomatik_dagitim_olusturuldu', [
                'firma_id' => (int) $finans->firma_id,
                'cari_id' => (int) $finans->cari_id,
                'finans_hareket_id' => (int) $finans->id,
                'fatura_idler' => array_column($oneriler, 'fatura_id'),
                'dagitim_sayisi' => count($oneriler),
            ]);
        } catch (Throwable $e) {
            // Tahsilat/ödeme kaydı korunur; kalan tutar avans olarak kalır.
            $this->logOtomasyon('otomatik_dagitim_hatasi', [
                'firma_id' => (int) $finans->firma_id,
                'cari_id' => (int) $finans->cari_id,
                'finans_hareket_id' => (int) $finans->id,
                'hata' => $e->getMessage(),
            ], 'warning');
            $this->sistemOlayServisi->olayKaydet('finans.otomatik_dagitim_hatasi', 'error', 'Otomatik dagitim hata ile sonlandi.', [
                'firma_id' => (int) $finans->firma_id,
                'cari_id' => (int) $finans->cari_id,
                'finans_hareket_id' => (int) $finans->id,
            ]);
        }
    }

    /**
     * Tahsilat/ödeme veya sipariş sonrası: carinin aynı para birimindeki açık onaylı faturalarına,
     * birikmiş avans satırlarından (FIFO finans sırası) sırayla mahsup dener.
     */
    public function siparisVeyaFinansSonrasiAvanslariDagit(int $firmaId, int $cariId, string $paraBirimi): void
    {
        if (! (bool) config('muhasebe.otomasyon.avans_otomatik_mahsup', true)) {
            return;
        }

        $faturalar = Fatura::query()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('para_birimi', $paraBirimi)
            ->where('durum', FaturaDurumu::Onayli->value)
            ->whereNotIn('odeme_durumu', ['odendi', 'iptal', 'iade'])
            ->whereRaw('CAST(acik_tutar AS DECIMAL(24,8)) > 0')
            ->orderBy('tarih')
            ->orderBy('id')
            ->get();

        foreach ($faturalar as $fatura) {
            if (! $fatura->tur->kayitUretirMi()) {
                continue;
            }
            $this->faturayaUygunAvansMahsupEt($fatura);
        }
    }

    public function faturayaUygunAvansMahsupEt(Fatura $fatura): void
    {
        if (! (bool) config('muhasebe.otomasyon.avans_otomatik_mahsup', true)) {
            return;
        }
        if ($fatura->cari_id === null) {
            return;
        }
        if (! $fatura->tur->kayitUretirMi()) {
            return;
        }
        if ($fatura->durum !== FaturaDurumu::Onayli) {
            return;
        }

        DB::transaction(function () use ($fatura): void {
            /** @var Fatura $kilitli */
            $kilitli = Fatura::query()->lockForUpdate()->whereKey($fatura->id)->firstOrFail();
            if ($kilitli->durum !== FaturaDurumu::Onayli || $kilitli->cari_id === null) {
                return;
            }
            if (! $kilitli->tur->kayitUretirMi()) {
                return;
            }

            $this->faturaOdemeDurumunuYenile((int) $kilitli->id);
            $kilitli->refresh();

            $acik = (string) ($kilitli->acik_tutar ?? '0');
            if (bccomp($acik, '0', self::PARA_BASAMAK) <= 0) {
                return;
            }

            $yon = $kilitli->tur->cariYonu();
            $finansTuru = match ($yon) {
                'alacak' => FinansHareketTuru::Tahsilat,
                'borc' => FinansHareketTuru::Odeme,
                default => null,
            };
            if ($finansTuru === null) {
                return;
            }

            $para = (string) $kilitli->para_birimi;
            $liste = $this->cariAvanslariniGetir(
                (int) $kilitli->firma_id,
                (int) $kilitli->cari_id,
                $para,
                $finansTuru,
            );

            foreach ($liste as $finans) {
                $kilitli->refresh();
                $acik = (string) ($kilitli->acik_tutar ?? '0');
                if (bccomp($acik, '0', self::PARA_BASAMAK) <= 0) {
                    break;
                }
                if ((int) $finans->firma_id !== (int) $kilitli->firma_id) {
                    continue;
                }
                $kalanFinans = $this->finansKalanTutari($finans);
                if (bccomp($kalanFinans, '0', self::PARA_BASAMAK) <= 0) {
                    continue;
                }
                if (! $this->faturaFinansTurUyumu($kilitli, $finans)) {
                    continue;
                }

                $parca = bccomp($kalanFinans, $acik, self::PARA_BASAMAK) >= 0 ? $acik : $kalanFinans;
                if (bccomp($parca, '0', self::PARA_BASAMAK) <= 0) {
                    continue;
                }

                try {
                    $this->finansiFaturalaraDagit($finans, [
                        ['fatura_id' => (int) $kilitli->id, 'tutar' => $parca],
                    ]);
                    $this->logOtomasyon('avans_mahsup_olusturuldu', [
                        'firma_id' => (int) $kilitli->firma_id,
                        'cari_id' => (int) $kilitli->cari_id,
                        'fatura_id' => (int) $kilitli->id,
                        'finans_hareket_id' => (int) $finans->id,
                        'tutar' => $parca,
                    ]);
                } catch (Throwable $e) {
                    $this->logOtomasyon('avans_mahsup_atlandi', [
                        'firma_id' => (int) $kilitli->firma_id,
                        'cari_id' => (int) $kilitli->cari_id,
                        'fatura_id' => (int) $kilitli->id,
                        'finans_hareket_id' => (int) $finans->id,
                        'sebep' => $e->getMessage(),
                    ], 'warning');
                    $this->sistemOlayServisi->olayKaydet('finans.avans_mahsup_hatasi', 'error', 'Avans mahsup islemi atlandi/hata aldi.', [
                        'firma_id' => (int) $kilitli->firma_id,
                        'cari_id' => (int) $kilitli->cari_id,
                        'fatura_id' => (int) $kilitli->id,
                        'finans_hareket_id' => (int) $finans->id,
                    ]);
                    break;
                }
            }
        });
    }

    /**
     * Cari için kullanılabilir avans içeren tahsilat/ödeme finans satırları (FIFO: tarih, id).
     *
     * @return Collection<int, FinansHareketi>
     */
    public function cariAvanslariniGetir(int $firmaId, int $cariId, string $paraBirimi, ?FinansHareketTuru $turFiltre = null): Collection
    {
        app(MuhasebeFirmaErisimDenetleyicisi::class)->okumaIcinFirmaKontrolEt($firmaId);

        $sorgu = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('para_birimi', $paraBirimi)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme])
            ->orderBy('tarih')
            ->orderBy('id');

        if ($turFiltre instanceof FinansHareketTuru) {
            $sorgu->where('tur', $turFiltre);
        }

        return $sorgu->get()->filter(function (FinansHareketi $f): bool {
            return bccomp($this->finansKalanTutari($f), '0', self::PARA_BASAMAK) > 0;
        })->values();
    }

    /**
     * Aktif tahsilat/ödeme finanslarından faturalara uygulanmış tutar toplamı.
     */
    public function cariKapamayaGidenKullanilanToplam(int $firmaId, int $cariId, string $paraBirimi): string
    {
        app(MuhasebeFirmaErisimDenetleyicisi::class)->okumaIcinFirmaKontrolEt($firmaId);

        $toplam = '0.00000000';
        $satirlar = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('para_birimi', $paraBirimi)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme])
            ->get(['kullanilan_tutar']);

        foreach ($satirlar as $f) {
            $toplam = bcadd($toplam, (string) ($f->kullanilan_tutar ?? '0'), self::PARA_BASAMAK);
        }

        return $toplam;
    }

    public function faturaOtomasyonOzetiMetni(Fatura $fatura): string
    {
        $kapamalar = $fatura->relationLoaded('finansKapatmalari')
            ? $fatura->finansKapatmalari
            : $fatura->finansKapatmalari()->with('finansHareketi:id,tur,avans_tutar,kullanilan_tutar')->get();

        if ($kapamalar->isEmpty()) {
            return 'Bu faturaya henüz finans kapaması düşmemiş. Otomatik dağıtım ve avans mahsup, config üzerinden etkinleştirilebilir (muhasebe.otomasyon).';
        }

        $finansIdler = $kapamalar->pluck('finans_hareket_id')->unique()->values()->all();
        $coklu = count($finansIdler) > 1;
        $kurFarki = (string) $kapamalar->sum(fn (FaturaFinansKapama $kapama): string => (string) ($kapama->kur_farki_tutari ?? '0'));

        $satirlar = [];
        foreach ($kapamalar as $k) {
            $fh = $k->finansHareketi;
            $satirlar[] = sprintf(
                'Finans #%s · %s · uygulanan %s %s',
                (string) $k->finans_hareket_id,
                $fh ? (string) $fh->tur->value : '—',
                (string) $k->uygulanan_tutar,
                (string) ($k->para_birimi ?: 'TRY')
            );
        }

        $not = (bool) config('muhasebe.otomasyon.finans_otomatik_dagitim', true)
            || (bool) config('muhasebe.otomasyon.avans_otomatik_mahsup', true)
            ? 'Otomasyon şu an yapılandırmada açık olabilir; kapama satırları manuel veya otomatik oluşmuş olabilir.'
            : 'Otomasyon kapalı; kapamalar manuel akışla oluşturulmuştur.';

        $kurNotu = bccomp($kurFarki, '0', self::PARA_BASAMAK) !== 0
            ? "\nKur farkı snapshot toplamı: {$kurFarki} ".(string) ($fatura->baz_para_birimi ?: 'TRY')
            : '';

        return ($coklu ? 'Bu fatura birden fazla finans hareketinden kapandı.' : 'Tek finans hareketinden kapama var.')."\n"
            .implode("\n", $satirlar)."\n".$not.$kurNotu;
    }

    private function faturaFinansTurUyumu(Fatura $fatura, FinansHareketi $finans): bool
    {
        $yon = $fatura->tur->cariYonu();

        return match ($finans->tur) {
            FinansHareketTuru::Tahsilat => $yon === 'alacak',
            FinansHareketTuru::Odeme => $yon === 'borc',
            default => false,
        };
    }

    private function bazFaturaTutariHesapla(Fatura $fatura, string $uygulananTutar): string
    {
        $kur = (string) ($fatura->doviz_kuru ?: '1');

        return bcmul($uygulananTutar, $kur, self::PARA_BASAMAK);
    }

    private function kurFarkiTutariHesapla(Fatura $fatura, FinansHareketi $finans, string $uygulananTutar): string
    {
        return bcsub(
            $this->bazUygulananTutarHesapla($finans, $uygulananTutar),
            $this->bazFaturaTutariHesapla($fatura, $uygulananTutar),
            self::PARA_BASAMAK
        );
    }

    /**
     * @param  array<string, mixed>  $baglam
     */
    private function logOtomasyon(string $olay, array $baglam, string $seviye = 'info'): void
    {
        $kanal = (string) config('muhasebe.otomasyon.log_channel', 'muhasebe');
        Log::channel($kanal)->{$seviye}('muhasebe.otomasyon.'.$olay, $baglam);
    }

    public function finansKalanTutari(FinansHareketi|int $finans): string
    {
        $finans = $finans instanceof FinansHareketi
            ? FinansHareketi::query()->withoutGlobalScopes()->findOrFail($finans->id)
            : FinansHareketi::query()->withoutGlobalScopes()->findOrFail($finans);
        $kullanilan = (string) (FaturaFinansKapama::query()->where('finans_hareket_id', $finans->id)->sum('uygulanan_tutar'));

        return bcsub((string) $finans->tutar, $kullanilan, self::PARA_BASAMAK);
    }

    /**
     * @return array{toplam_avans:string,satir_sayisi:int}
     */
    public function cariKullanilabilirAvansOzeti(int $firmaId, int $cariId, ?string $paraBirimi = null): array
    {
        app(MuhasebeFirmaErisimDenetleyicisi::class)->okumaIcinFirmaKontrolEt($firmaId);

        $satirlar = FinansHareketi::query()->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where('cari_id', $cariId)
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme])
            ->when($paraBirimi, fn ($q) => $q->where('para_birimi', $paraBirimi))
            ->get(['id', 'tutar']);

        $toplamAvans = '0.00000000';
        $satir = 0;
        foreach ($satirlar as $finans) {
            $kalan = $this->finansKalanTutari($finans);
            if (bccomp($kalan, '0', self::PARA_BASAMAK) <= 0) {
                continue;
            }
            $toplamAvans = bcadd($toplamAvans, $kalan, self::PARA_BASAMAK);
            $satir++;
        }

        return ['toplam_avans' => $toplamAvans, 'satir_sayisi' => $satir];
    }

    public function finansTersleninceFaturaDurumunuYenile(FinansHareketi $finans): void
    {
        $this->kurFarkiHareketServisi->finansKurFarklariniIptalEt((int) $finans->id);

        $faturaIdler = FaturaFinansKapama::query()
            ->where('finans_hareket_id', $finans->id)
            ->pluck('fatura_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($finans->referans_turu === 'fatura' && $finans->referans_id !== null) {
            $faturaIdler[] = (int) $finans->referans_id;
        }

        foreach (array_unique($faturaIdler) as $fid) {
            if ($fid > 0) {
                $this->faturaOdemeDurumunuYenile($fid);
            }
        }
    }

    /**
     * Düzeltme akışında eski finansın aktif fatura kapamalarını yeni finansa
     * taşır. Eski kapama satırları silinmez; iptal edilen finansla birlikte
     * audit geçmişinde tutulur.
     */
    public function faturaKapamalariniYeniFinansaTasi(FinansHareketi|int $eskiFinans, FinansHareketi|int $yeniFinans): void
    {
        DB::transaction(function () use ($eskiFinans, $yeniFinans): void {
            $eski = $eskiFinans instanceof FinansHareketi
                ? FinansHareketi::query()->lockForUpdate()->findOrFail($eskiFinans->getKey())
                : FinansHareketi::query()->lockForUpdate()->findOrFail($eskiFinans);
            $yeni = $yeniFinans instanceof FinansHareketi
                ? FinansHareketi::query()->lockForUpdate()->findOrFail($yeniFinans->getKey())
                : FinansHareketi::query()->lockForUpdate()->findOrFail($yeniFinans);

            if ((int) $eski->firma_id !== (int) $yeni->firma_id) {
                throw new IsKuraliIstisnasi('Fatura kapama düzeltmesi farklı firmalar arasında taşınamaz.');
            }
            if ($eski->durum !== FinansHareketDurumu::Iptal) {
                throw new IsKuraliIstisnasi('Eski finans hareketi iptal edilmeden fatura kapaması taşınamaz.');
            }
            if ($yeni->durum !== FinansHareketDurumu::Aktif) {
                throw new IsKuraliIstisnasi('Yeni finans hareketi aktif olmalıdır.');
            }

            $kapamalar = FaturaFinansKapama::query()
                ->where('finans_hareket_id', $eski->getKey())
                ->get();

            foreach ($kapamalar as $kapama) {
                FaturaFinansKapama::query()->firstOrCreate(
                    [
                        'firma_id' => $kapama->firma_id,
                        'fatura_id' => $kapama->fatura_id,
                        'finans_hareket_id' => $yeni->getKey(),
                    ],
                    [
                        'uygulanan_tutar' => $kapama->uygulanan_tutar,
                        'baz_uygulanan_tutar' => $kapama->baz_uygulanan_tutar,
                        'baz_fatura_tutari' => $kapama->baz_fatura_tutari,
                        'kur_farki_tutari' => $kapama->kur_farki_tutari,
                        'para_birimi' => $kapama->para_birimi,
                        'baz_para_birimi' => $kapama->baz_para_birimi,
                        'kur' => $kapama->kur,
                    ]
                );
                $this->faturaOdemeDurumunuYenile((int) $kapama->fatura_id);
            }
        });
    }

    public function faturaOdemeDurumunuYenile(int $faturaId): void
    {
        $faturaBulundu = DB::transaction(function () use ($faturaId): bool {
            $fatura = Fatura::query()->lockForUpdate()->whereKey($faturaId)->first();
            if (! $fatura) {
                return false;
            }

            $odenecek = (string) ($fatura->odenecek_tutar ?? $fatura->genel_toplam ?? 0);

            $odenen = (string) (FaturaFinansKapama::query()
                ->where('fatura_id', $fatura->id)
                ->whereHas('finansHareketi', fn ($q) => $q->where('durum', FinansHareketDurumu::Aktif))
                ->sum('uygulanan_tutar'));

            $acik = bcsub($odenecek, $odenen, self::PARA_BASAMAK);
            $fazlaOdemeIzinli = (bool) config('muhasebe.fatura.fazla_odeme_izinli', true);
            if (! $fazlaOdemeIzinli && bccomp($acik, '0', self::PARA_BASAMAK) < 0) {
                throw new IsKuraliIstisnasi('Fatura için fazla ödeme desteklenmiyor.');
            }

            $odemeDurumu = match (true) {
                bccomp($odenen, '0', self::PARA_BASAMAK) === 0 => 'odenmedi',
                bccomp($acik, '0', self::PARA_BASAMAK) > 0 => 'kismi_odendi',
                bccomp($acik, '0', self::PARA_BASAMAK) === 0 => 'odendi',
                default => 'fazla_odendi',
            };

            $fatura->update([
                'odendi_tutari' => $odenen,
                'acik_tutar' => bccomp($acik, '0', self::PARA_BASAMAK) < 0 ? '0' : $acik,
                'odeme_durumu' => $odemeDurumu,
            ]);

            return true;
        });
        if (! $faturaBulundu) {
            Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->warning('fatura.kapama.yenileme_atlandi', [
                'fatura_id' => $faturaId,
                'neden' => 'fatura_bulunamadi',
            ]);

            return;
        }

        $this->faturaKapamaDogrulamaServisi->faturaKapamaDurumuDogrula($faturaId);
        Log::channel((string) config('muhasebe.fatura.log_channel', 'muhasebe'))->info('fatura.kapama.yenilendi', [
            'fatura_id' => $faturaId,
        ]);
    }

    private function bazUygulananTutarHesapla(FinansHareketi $finans, string $uygulanan): string
    {
        $baz = (string) ($finans->baz_tutar ?: $finans->tutar ?: '0');
        $tutar = (string) ($finans->tutar ?: '0');
        if (bccomp($tutar, '0', self::PARA_BASAMAK) <= 0) {
            return number_format((float) $uygulanan, self::PARA_BASAMAK, '.', '');
        }

        $oran = bcdiv($baz, $tutar, self::PARA_BASAMAK);
        $sonuc = bcmul((string) $uygulanan, $oran, self::PARA_BASAMAK);

        return number_format((float) $sonuc, self::PARA_BASAMAK, '.', '');
    }
}
