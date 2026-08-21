<?php

namespace App\Muhasebe\Servisler;

use App\Models\Firma;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokHareketiParcasi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\Muhasebe\StokParcaIslemLogu;
use App\Models\Muhasebe\StokParcasi;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Toplu stok bakiyesini ana parti ve fiziksel stok parçalarına ayırır.
 *
 * Bu servis yalnızca veri dönüşümünü yapar; kullanıcı onayı ve yetki kontrolü
 * çağıran sayfa/aksiyon katmanında yapılmalıdır.
 */
class StokParcaDonusumServisi
{
    /** Kartın dönüşüme esas ana miktarını verir. Ölçülü kartlarda kaynak ölçü bakiyeleri kullanılır. */
    public function toplamMiktar(StokKarti $stok): string
    {
        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru
            ? $stok->olculu_takip_turu
            : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu);
        if (! $tur?->olculuMu()) {
            return $this->decimal($stok->stok_miktari);
        }

        return $this->decimal(StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('stok_id', $stok->getKey())
            ->where('ana_miktar', '>', 0)
            ->sum('ana_miktar'));
    }

    /** Dönüşüm formunda bir ölçü bakiyesinin en az bir fiziksel parçayla temsil edilmesini sağlar. */
    public function minimumParcaSayisi(StokKarti $stok): int
    {
        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru
            ? $stok->olculu_takip_turu
            : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu);

        if (! $tur?->olculuMu()) {
            return 1;
        }

        return max(1, StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->getKey())
            ->whereNull('stok_parcasi_id')
            ->where('ana_miktar', '>', 0)
            ->count());
    }

    /** Aktif bir ana partinin dönüşümünde gerekli en düşük parça sayısını verir. */
    public function partiMinimumParcaSayisi(StokParcasi $parti): int
    {
        $this->donusturulebilirAnaPartiyiDogrula($parti);
        $stok = StokKarti::withoutGlobalScopes()->findOrFail($parti->stok_id);
        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru
            ? $stok->olculu_takip_turu
            : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu);

        if (! $tur?->olculuMu()) {
            return 1;
        }

        return max(1, StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', $parti->firma_id)
            ->where('stok_id', $parti->stok_id)
            ->where('stok_parcasi_id', $parti->getKey())
            ->where('ana_miktar', '>', 0)
            ->count());
    }

    /**
     * Kullanıcının sonradan değiştirebileceği, kaynak ölçü bakiyelerini koruyan eşit dağılım önerisi üretir.
     *
     * @return array<int, array<string, mixed>>
     */
    public function donusumOnerisi(StokKarti $stok, int $parcaSayisi): array
    {
        return $this->kaynakliDonusumOnerisi($stok, $parcaSayisi);
    }

    /** Mevcut aktif bir ana partinin yalnız kalan bakiyesi için düzenlenebilir dağılım önerisi üretir. */
    public function partiDonusumOnerisi(StokParcasi $parti, int $parcaSayisi): array
    {
        $this->donusturulebilirAnaPartiyiDogrula($parti);
        $stok = StokKarti::withoutGlobalScopes()->findOrFail($parti->stok_id);

        return $this->kaynakliDonusumOnerisi($stok, $parcaSayisi, $parti);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kaynakliDonusumOnerisi(StokKarti $stok, int $parcaSayisi, ?StokParcasi $anaParca = null): array
    {
        if ($parcaSayisi < 1 || $parcaSayisi > 5000) {
            throw new IsKuraliIstisnasi('Parça sayısı 1 ile 5000 arasında olmalıdır.');
        }

        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru
            ? $stok->olculu_takip_turu
            : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu);

        if (! $tur?->olculuMu()) {
            $satirlar = $this->esitOneriSatirlari(
                $anaParca ? $this->decimal($anaParca->kalan_miktar) : $this->toplamMiktar($stok),
                $parcaSayisi,
            );

            return array_map(fn (array $satir): array => [
                ...$satir,
                'maliyet' => $anaParca?->birim_maliyet,
                'renk_desen' => $anaParca?->renk_desen,
                'kalite_sinifi' => $anaParca?->kalite_sinifi,
            ], $satirlar);
        }

        $bakiyeler = StokOlcuBakiyesi::withoutGlobalScopes()
            ->where('firma_id', $stok->firma_id)
            ->where('stok_id', $stok->getKey())
            ->when(
                $anaParca,
                fn ($query) => $query->where('stok_parcasi_id', $anaParca->getKey()),
                fn ($query) => $query->whereNull('stok_parcasi_id'),
            )
            ->where('ana_miktar', '>', 0)
            ->with('olcu')
            ->orderBy('id')
            ->get();

        if ($bakiyeler->isEmpty()) {
            throw new IsKuraliIstisnasi('Dönüştürülecek ölçü bakiyesi bulunamadı.');
        }
        if ($parcaSayisi < $bakiyeler->count()) {
            throw new IsKuraliIstisnasi('Bu stokta '.$bakiyeler->count().' farklı ölçü bakiyesi var. En az '.$bakiyeler->count().' parça oluşturulmalıdır.');
        }

        /** @var array<int, int> $adetler */
        $adetler = $bakiyeler->mapWithKeys(fn (StokOlcuBakiyesi $bakiye): array => [(int) $bakiye->id => 1])->all();
        $kalanParca = $parcaSayisi - $bakiyeler->count();
        while ($kalanParca > 0) {
            $secilen = $bakiyeler->first();
            foreach ($bakiyeler as $bakiye) {
                $adayPay = bcdiv((string) $bakiye->ana_miktar, (string) $adetler[(int) $bakiye->id], 8);
                $secilenPay = bcdiv((string) $secilen->ana_miktar, (string) $adetler[(int) $secilen->id], 8);
                if (bccomp($adayPay, $secilenPay, 8) > 0) {
                    $secilen = $bakiye;
                }
            }
            $adetler[(int) $secilen->id]++;
            $kalanParca--;
        }

        $satirlar = [];
        foreach ($bakiyeler as $bakiye) {
            $adet = $adetler[(int) $bakiye->id];
            $dagitilan = '0';
            $esitMiktar = bcdiv((string) $bakiye->ana_miktar, (string) $adet, 8);
            for ($sira = 1; $sira <= $adet; $sira++) {
                $miktar = $sira === $adet
                    ? bcsub((string) $bakiye->ana_miktar, $dagitilan, 8)
                    : $esitMiktar;
                $dagitilan = bcadd($dagitilan, $miktar, 8);
                $satirlar[] = [
                    'stok_olcu_bakiyesi_id' => (int) $bakiye->id,
                    'stok_olcusu_id' => (int) $bakiye->stok_olcusu_id,
                    'olcu_kaynagi' => $this->olcuKaynagiEtiketi($bakiye),
                    'ana_miktar' => $miktar,
                    'parca_kodu' => '',
                    'maliyet' => $anaParca?->birim_maliyet,
                    'renk_desen' => $anaParca?->renk_desen,
                    'kalite_sinifi' => $anaParca?->kalite_sinifi,
                    ...$this->olcuFormVerisi($bakiye->olcu, $miktar),
                ];
            }
        }

        return $satirlar;
    }

    /**
     * Fiziksel bölme formu için toplamı tam koruyan ve kaynak ölçüyü taşıyan öneri üretir.
     * Son satır, sekiz ondalık basamakta oluşabilecek bölme farkını üstlenir.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bolmeOnerisi(StokParcasi $kaynak, int $parcaSayisi): array
    {
        if ($parcaSayisi < 2 || $parcaSayisi > 5000) {
            throw new IsKuraliIstisnasi('Yeni parça sayısı 2 ile 5000 arasında olmalıdır.');
        }
        if (! $kaynak->parca_mi || bccomp((string) $kaynak->kalan_miktar, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Yalnızca kalan bakiyesi olan aktif stok parçası bölünebilir.');
        }

        $bakiye = $kaynak->olcuBakiyeleri()
            ->withoutGlobalScopes()
            ->where('ana_miktar', '>', 0)
            ->with('olcu')
            ->first();
        $satirlar = [];
        $dagitilan = '0';
        $toplam = $this->decimal($kaynak->kalan_miktar);
        $esit = bcdiv($toplam, (string) $parcaSayisi, 8);

        for ($sira = 1; $sira <= $parcaSayisi; $sira++) {
            $miktar = $sira === $parcaSayisi ? bcsub($toplam, $dagitilan, 8) : $esit;
            $dagitilan = bcadd($dagitilan, $miktar, 8);
            $satirlar[] = [
                'ana_miktar' => $miktar,
                'maliyet' => $kaynak->birim_maliyet,
                'renk_desen' => $kaynak->renk_desen,
                'kalite_sinifi' => $kaynak->kalite_sinifi,
                ...$this->olcuFormVerisi($bakiye?->olcu, $miktar),
            ];
        }

        return $satirlar;
    }

    /**
     * @param  array<int, array{ana_miktar:numeric|string, stok_olcu_bakiyesi_id?:int, adet_esdegeri?:numeric|string, parca_kodu?:string, maliyet?:numeric|string, olcu?:array<string,mixed>}>  $parcalar
     * @return array{ana_parca:StokParcasi, parcalar:Collection}
     */
    public function donustur(StokKarti $stok, array $parcalar, ?int $ustParcaId = null): array
    {
        if ($parcalar === []) {
            throw new IsKuraliIstisnasi('En az bir stok parçası oluşturulmalıdır.');
        }

        return DB::transaction(function () use ($stok, $parcalar, $ustParcaId): array {
            $kilitliStok = StokKarti::withoutGlobalScopes()->lockForUpdate()->findOrFail($stok->getKey());
            $firmaId = (int) $kilitliStok->firma_id;
            $tur = $kilitliStok->olculu_takip_turu instanceof OlculuStokTakipTuru
                ? $kilitliStok->olculu_takip_turu
                : OlculuStokTakipTuru::tryFrom((string) $kilitliStok->olculu_takip_turu);
            $olculu = (bool) $tur?->olculuMu();
            $mevcutAnaParti = $ustParcaId !== null
                ? StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($ustParcaId)
                : null;
            if ($mevcutAnaParti) {
                $this->donusturulebilirAnaPartiyiDogrula($mevcutAnaParti, $kilitliStok);
            }
            $stokMiktari = $mevcutAnaParti
                ? $this->decimal($mevcutAnaParti->kalan_miktar)
                : $this->toplamMiktar($kilitliStok);
            $kaynakBakiyeler = $olculu
                ? StokOlcuBakiyesi::withoutGlobalScopes()
                    ->where('firma_id', $firmaId)
                    ->where('stok_id', $kilitliStok->id)
                    ->when(
                        $mevcutAnaParti,
                        fn ($query) => $query->where('stok_parcasi_id', $mevcutAnaParti->getKey()),
                        fn ($query) => $query->whereNull('stok_parcasi_id'),
                    )
                    ->where('ana_miktar', '>', 0)
                    ->lockForUpdate()
                    ->get()
                : collect();
            if ($olculu && $kaynakBakiyeler->isEmpty()) {
                throw new IsKuraliIstisnasi('Ölçülü stokta dönüşüm için kullanılabilir ölçü bakiyesi bulunamadı.');
            }
            if ($olculu && $kaynakBakiyeler->pluck('depo_id')->unique()->count() > 1) {
                throw new IsKuraliIstisnasi('Bir stok kartının ölçü bakiyeleri birden fazla depoda. Dönüşümden önce depoları ayrı ayrı netleştirin.');
            }
            $kaynakBakiye = $kaynakBakiyeler->first();
            $toplam = '0';
            $parcaKaynaklari = [];
            $kaynakToplamlari = [];
            foreach (array_values($parcalar) as $index => $parca) {
                $miktar = $this->pozitif($parca['ana_miktar'] ?? null, 'Parça ana miktarı');
                $toplam = bcadd($toplam, $miktar, 8);
                if ($olculu) {
                    $kaynakId = (int) ($parca['stok_olcu_bakiyesi_id'] ?? 0);
                    if ($kaynakId === 0 && $kaynakBakiyeler->count() === 1) {
                        $kaynakId = (int) $kaynakBakiye->id;
                    }
                    /** @var StokOlcuBakiyesi|null $satirKaynagi */
                    $satirKaynagi = $kaynakBakiyeler->firstWhere('id', $kaynakId);
                    if (! $satirKaynagi) {
                        throw new IsKuraliIstisnasi('Her parça için geçerli ölçü kaynağı seçilmelidir. Farklı ölçüler tek bakiyeden düşülemez.');
                    }
                    $parcaKaynaklari[$index] = $satirKaynagi;
                    $kaynakToplamlari[$kaynakId] = bcadd($kaynakToplamlari[$kaynakId] ?? '0', $miktar, 8);
                }
            }
            if (bccomp($toplam, $stokMiktari, 8) !== 0) {
                throw new IsKuraliIstisnasi('Parça dağılımı toplamı mevcut stok miktarıyla aynı olmalıdır.');
            }
            if ($olculu) {
                foreach ($kaynakBakiyeler as $bakiye) {
                    $dagitilan = $kaynakToplamlari[(int) $bakiye->id] ?? '0';
                    if (bccomp($dagitilan, (string) $bakiye->ana_miktar, 8) !== 0) {
                        throw new IsKuraliIstisnasi($this->olcuKaynagiEtiketi($bakiye).' için parça toplamı kaynak bakiyeyle aynı olmalıdır.');
                    }
                }
            }

            if ($ustParcaId === null && StokParcasi::query()
                ->where('firma_id', $firmaId)
                ->where('stok_id', $kilitliStok->id)
                ->where('kalan_miktar', '>', 0)
                ->exists()) {
                throw new IsKuraliIstisnasi('Bu stok kartında zaten aktif parti bakiyesi var; önce mevcut parti dağılımı netleştirilmelidir.');
            }

            $anaParca = $mevcutAnaParti !== null
                ? $mevcutAnaParti
                : $this->anaParcaOlustur($kilitliStok, $toplam);

            if ((int) $anaParca->firma_id !== $firmaId || (int) $anaParca->stok_id !== (int) $kilitliStok->id) {
                throw new IsKuraliIstisnasi('Ana parti seçilen stok kartına ait değil.');
            }

            $firmaKodu = strtoupper(trim((string) Firma::query()->whereKey($firmaId)->value('firma_kodu')));
            $stokKodu = strtoupper(trim((string) ($kilitliStok->kod ?: 'STK'.$kilitliStok->id)));
            $olusanlar = collect();
            foreach (array_values($parcalar) as $index => $parca) {
                /** @var StokOlcuBakiyesi|null $satirKaynagi */
                $satirKaynagi = $parcaKaynaklari[$index] ?? null;
                $sira = $index + 1;
                $kod = trim((string) ($parca['parca_kodu'] ?? ''));
                if ($kod === '') {
                    $kod = $this->yeniIlkParcaKodu($firmaId, $firmaKodu, $stokKodu, $sira);
                }
                if (StokParcasi::query()->where('firma_id', $firmaId)->where('parca_kodu', $kod)->exists()) {
                    throw new IsKuraliIstisnasi('Stok parçası kodu zaten kullanılıyor: '.$kod);
                }
                $miktar = $this->pozitif($parca['ana_miktar'] ?? null, 'Parça ana miktarı');
                $maliyet = filled($parca['maliyet'] ?? null)
                    ? $parca['maliyet']
                    : ($mevcutAnaParti?->birim_maliyet ?? $this->esitMaliyet($kilitliStok, $toplam, $miktar));
                $parti = StokParcasi::query()->create([
                    'firma_id' => $firmaId,
                    'stok_id' => $kilitliStok->id,
                    'depo_id' => $anaParca->depo_id ?: ($satirKaynagi?->depo_id ?: $kaynakBakiye?->depo_id ?: $kilitliStok->depo_id),
                    'ust_parca_id' => $anaParca->id,
                    'parca_kodu' => $kod,
                    'parca_kodu' => $kod,
                    'barkod' => $kod,
                    'parca_mi' => true,
                    'parca_durumu' => 'aktif',
                    'blok_no' => $mevcutAnaParti?->blok_no,
                    'ocak_tedarikci' => $mevcutAnaParti?->ocak_tedarikci,
                    'birim_maliyet' => $maliyet,
                    'giren_miktar' => $miktar,
                    'kalan_miktar' => $miktar,
                    'metrekare' => $tur === OlculuStokTakipTuru::Alan ? $miktar : null,
                    'kalinlik_cm' => null,
                    'renk_desen' => $this->metinVeyaNull($parca['renk_desen'] ?? $mevcutAnaParti?->renk_desen),
                    'kalite_sinifi' => $this->metinVeyaNull($parca['kalite_sinifi'] ?? $mevcutAnaParti?->kalite_sinifi),
                    'plaka_no' => $kod,
                    'uretim_tarihi' => $mevcutAnaParti?->uretim_tarihi,
                    'son_kullanma_tarihi' => $mevcutAnaParti?->son_kullanma_tarihi,
                ]);
                $olusanlar->push($parti);

                if ($olculu) {
                    $kaynakOlcu = StokOlcusu::withoutGlobalScopes()
                        ->where('firma_id', $firmaId)
                        ->where('stok_id', $kilitliStok->id)
                        ->whereKey((int) $satirKaynagi->stok_olcusu_id)
                        ->firstOrFail();
                    $olcu = $this->parcaOlcusunuCoz($kilitliStok, $kaynakOlcu, $parca, $kod, $miktar);
                    $donusum = $satirKaynagi->donusum_ana_miktari ?: $kaynakOlcu->bir_adet_ana_miktar;
                    $partiBakiye = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur(
                        $firmaId,
                        $kilitliStok,
                        $olcu,
                        Depo::withoutGlobalScopes()->findOrFail((int) $satirKaynagi->depo_id),
                        $parti,
                        $donusum,
                    );
                    $adet = $parca['adet_esdegeri'] ?? null;
                    app(StokOlcuBakiyeServisi::class)->giris($partiBakiye, $miktar, $adet);
                    app(StokOlcuBakiyeServisi::class)->cikis($satirKaynagi, $miktar, $adet);
                    $parti->update(['kalinlik_cm' => $this->kalinlikCm($olcu)]);
                }
            }

            $anaParca->update([
                'giren_miktar' => $mevcutAnaParti ? $anaParca->giren_miktar : $toplam,
                'kalan_miktar' => '0',
                'parca_mi' => false,
                'parca_durumu' => 'donusturuldu',
            ]);

            $this->log($kilitliStok, 'donusum', $anaParca, null, [
                'parca_sayisi' => $olusanlar->count(),
                'toplam_ana_miktar' => $toplam,
                'parcalar' => $olusanlar->map(fn (StokParcasi $parca): array => [
                    'id' => $parca->id, 'kod' => $parca->parca_kodu, 'ana_miktar' => (string) $parca->giren_miktar,
                ])->values()->all(),
            ]);

            return ['ana_parca' => $anaParca->refresh(), 'parcalar' => $olusanlar];
        });
    }

    /** @param array<int, array<string, mixed>> $parcalar */
    public function partiyiDonustur(StokParcasi $anaParca, array $parcalar): array
    {
        $this->donusturulebilirAnaPartiyiDogrula($anaParca);
        $stok = StokKarti::withoutGlobalScopes()->findOrFail($anaParca->stok_id);

        return $this->donustur($stok, $parcalar, (int) $anaParca->getKey());
    }

    /** Bir fiziksel parçayı keserek yeni stok parçaları oluşturur. Kaynak parça tüketilmiş sayılır. */
    public function parcayiBol(StokParcasi $kaynak, array $parcalar): array
    {
        if (! $kaynak->parca_mi || bccomp((string) $kaynak->kalan_miktar, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Yalnızca kalan bakiyesi olan aktif stok parçası bölünebilir.');
        }
        if (count($parcalar) < 2) {
            throw new IsKuraliIstisnasi('En az iki yeni stok parçası girilmelidir.');
        }

        return DB::transaction(function () use ($kaynak, $parcalar): array {
            $kilitli = StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($kaynak->getKey());
            $toplam = '0';
            foreach ($parcalar as $parca) {
                $toplam = bcadd($toplam, $this->pozitif($parca['ana_miktar'] ?? null, 'Yeni parça ana miktarı'), 8);
            }
            if (bccomp($toplam, (string) $kilitli->kalan_miktar, 8) !== 0) {
                throw new IsKuraliIstisnasi('Yeni parçaların toplamı kaynak parçanın kalan miktarına eşit olmalıdır.');
            }

            $stok = StokKarti::withoutGlobalScopes()->findOrFail($kilitli->stok_id);
            $olusanlar = collect();
            $kaynakBakiyeler = $kilitli->olcuBakiyeleri()
                ->withoutGlobalScopes()
                ->where('ana_miktar', '>', 0)
                ->lockForUpdate()
                ->get();
            if ($kaynakBakiyeler->count() > 1) {
                throw new IsKuraliIstisnasi('Bir fiziksel parçada birden fazla aktif ölçü bakiyesi bulundu. Bölme işleminden önce ölçü bakiyelerini tek kaynağa indirin.');
            }
            $kaynakBakiye = $kaynakBakiyeler->first();
            foreach (array_values($parcalar) as $index => $parca) {
                $miktar = $this->pozitif($parca['ana_miktar'] ?? null, 'Yeni parça ana miktarı');
                $kod = $this->yeniParcaKodu($kilitli, $index + 1);
                $yeni = StokParcasi::withoutGlobalScopes()->create([
                    'firma_id' => $kilitli->firma_id, 'stok_id' => $kilitli->stok_id, 'depo_id' => $kilitli->depo_id,
                    'ust_parca_id' => $kilitli->ust_parca_id ?: $kilitli->id,
                    'parca_kodu' => $kod, 'parca_kodu' => $kod, 'barkod' => $kod,
                    'parca_mi' => true, 'parca_durumu' => 'aktif',
                    'birim_maliyet' => $parca['maliyet'] ?? $kilitli->birim_maliyet,
                    'giren_miktar' => $miktar, 'kalan_miktar' => $miktar,
                    'metrekare' => ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru ? $stok->olculu_takip_turu : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu)) === OlculuStokTakipTuru::Alan ? $miktar : null,
                    'kalinlik_cm' => null,
                    'renk_desen' => $this->metinVeyaNull($parca['renk_desen'] ?? $kilitli->renk_desen),
                    'kalite_sinifi' => $this->metinVeyaNull($parca['kalite_sinifi'] ?? $kilitli->kalite_sinifi),
                    'plaka_no' => $kod,
                ]);
                $olusanlar->push($yeni);
                if ($kaynakBakiye) {
                    $kaynakOlcu = $kaynakBakiye->olcu()->withoutGlobalScopes()->firstOrFail();
                    $olcu = $this->parcaOlcusunuCoz($stok, $kaynakOlcu, $parca, $kod, $miktar);
                    $donusum = $kaynakBakiye->donusum_ana_miktari ?: $kaynakOlcu->bir_adet_ana_miktar;
                    $partiBakiye = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur(
                        (int) $kilitli->firma_id, $stok, $olcu, Depo::withoutGlobalScopes()->findOrFail((int) $kilitli->depo_id), $yeni, $donusum
                    );
                    app(StokOlcuBakiyeServisi::class)->giris($partiBakiye, $miktar);
                    $yeni->update(['kalinlik_cm' => $this->kalinlikCm($olcu)]);
                }
            }
            if ($kaynakBakiye) {
                app(StokOlcuBakiyeServisi::class)->cikis($kaynakBakiye, (string) $kilitli->kalan_miktar);
            }
            $kilitli->update(['kalan_miktar' => '0', 'parca_durumu' => 'bolundu']);
            $this->log($stok, 'bolme', $kilitli->ustParca()->withoutGlobalScopes()->first(), $kilitli, [
                'kaynak_kodu' => $kilitli->parca_kodu, 'parcalar' => $olusanlar->pluck('parca_kodu')->values()->all(), 'toplam_ana_miktar' => $toplam,
            ]);

            return ['kaynak' => $kilitli->refresh(), 'parcalar' => $olusanlar];
        });
    }

    /** @param array<int, int|string> $parcaIdleri
     * @return array<string, mixed>
     */
    public function birlesmeOnerisi(array $parcaIdleri): array
    {
        $parcalar = StokParcasi::withoutGlobalScopes()
            ->whereIn('id', array_values(array_unique(array_map('intval', $parcaIdleri))))
            ->where('parca_mi', true)
            ->where('kalan_miktar', '>', 0)
            ->with(['olcuBakiyeleri' => fn ($query) => $query->withoutGlobalScopes()->where('ana_miktar', '>', 0), 'olcuBakiyeleri.olcu'])
            ->get();
        $this->birlesebilirParcalariDogrula($parcalar);
        $toplam = $parcalar->reduce(fn (string $t, StokParcasi $p): string => bcadd($t, (string) $p->kalan_miktar, 8), '0');
        $ilk = $parcalar->firstOrFail();
        $ilkBakiye = $ilk->olcuBakiyeleri->first();

        return [
            'ana_miktar' => $toplam,
            'renk_desen' => $parcalar->pluck('renk_desen')->filter()->unique()->count() === 1 ? $ilk->renk_desen : null,
            'kalite_sinifi' => $parcalar->pluck('kalite_sinifi')->filter()->unique()->count() === 1 ? $ilk->kalite_sinifi : null,
            ...$this->olcuFormVerisi($ilkBakiye?->olcu()->withoutGlobalScopes()->first(), $toplam),
        ];
    }

    /**
     * En az iki fiziksel stok parçasını tek yeni parça halinde birleştirir.
     * Kaynaklar hareket geçmişleriyle korunur; yeni parçanın maliyeti ana miktar ağırlıklı ortalamadır.
     *
     * @param  array<int, int|string>  $parcaIdleri
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    public function parcalariBirlestir(array $parcaIdleri, array $veri): array
    {
        $idlerin = array_values(array_unique(array_map('intval', $parcaIdleri)));

        return DB::transaction(function () use ($idlerin, $veri): array {
            $parcalar = StokParcasi::withoutGlobalScopes()->whereIn('id', $idlerin)->lockForUpdate()->get();
            $this->birlesebilirParcalariDogrula($parcalar, count($idlerin));
            $ilk = $parcalar->firstOrFail();
            $stok = StokKarti::withoutGlobalScopes()->findOrFail($ilk->stok_id);
            $depo = Depo::withoutGlobalScopes()->findOrFail($ilk->depo_id);
            $toplam = $parcalar->reduce(fn (string $t, StokParcasi $p): string => bcadd($t, (string) $p->kalan_miktar, 8), '0');
            $maliyetToplami = $parcalar->reduce(
                fn (string $t, StokParcasi $p): string => bcadd($t, bcmul((string) $p->kalan_miktar, (string) $p->birim_maliyet, 16), 16),
                '0',
            );
            $birimMaliyet = bcdiv($maliyetToplami, $toplam, 8);
            $kod = $this->yeniBirlesikParcaKodu($ilk);
            $bakiyeler = collect();
            foreach ($parcalar as $parca) {
                $aktif = $parca->olcuBakiyeleri()->withoutGlobalScopes()->where('ana_miktar', '>', 0)->lockForUpdate()->get();
                if ($aktif->count() > 1) {
                    throw new IsKuraliIstisnasi('Birleştirilecek her fiziksel parçada en fazla bir aktif ölçü bakiyesi bulunmalıdır.');
                }
                if ($aktif->isNotEmpty()) {
                    $bakiyeler->push($aktif->first());
                }
            }
            if ($bakiyeler->isNotEmpty() && $bakiyeler->count() !== $parcalar->count()) {
                throw new IsKuraliIstisnasi('Ölçülü ve ölçüsüz fiziksel parçalar aynı işlemde birleştirilemez.');
            }

            $yeni = StokParcasi::withoutGlobalScopes()->create([
                'firma_id' => $ilk->firma_id,
                'stok_id' => $ilk->stok_id,
                'depo_id' => $ilk->depo_id,
                'ust_parca_id' => $ilk->ust_parca_id,
                'parca_kodu' => $kod,
                'parca_kodu' => $kod,
                'barkod' => $kod,
                'parca_mi' => true,
                'parca_durumu' => 'aktif',
                'birim_maliyet' => $birimMaliyet,
                'giren_miktar' => $toplam,
                'kalan_miktar' => $toplam,
                'metrekare' => ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru ? $stok->olculu_takip_turu : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu)) === OlculuStokTakipTuru::Alan ? $toplam : null,
                'renk_desen' => $this->metinVeyaNull($veri['renk_desen'] ?? null),
                'kalite_sinifi' => $this->metinVeyaNull($veri['kalite_sinifi'] ?? null),
                'plaka_no' => $kod,
                'uretim_tarihi' => $parcalar->pluck('uretim_tarihi')->filter()->min(),
                'son_kullanma_tarihi' => $parcalar->pluck('son_kullanma_tarihi')->filter()->min(),
            ]);

            $kaynakBakiyeVerileri = [];
            if ($bakiyeler->isNotEmpty()) {
                $kaynakOlcu = $bakiyeler->first()->olcu()->withoutGlobalScopes()->firstOrFail();
                $yeniOlcu = $this->parcaOlcusunuCoz($stok, $kaynakOlcu, $veri, $kod, $toplam);
                $hedef = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur(
                    (int) $ilk->firma_id, $stok, $yeniOlcu, $depo, $yeni, (string) $yeniOlcu->bir_adet_ana_miktar
                );
                app(StokOlcuBakiyeServisi::class)->giris($hedef, $toplam, '1');
                $yeni->update(['kalinlik_cm' => $this->kalinlikCm($yeniOlcu)]);

                foreach ($bakiyeler as $bakiye) {
                    $kaynakBakiyeVerileri[] = [
                        'bakiye_id' => $bakiye->id,
                        'stok_parcasi_id' => $bakiye->stok_parcasi_id,
                        'ana_miktar' => (string) $bakiye->ana_miktar,
                        'adet_esdegeri' => (string) $bakiye->adet_esdegeri,
                    ];
                    app(StokOlcuBakiyeServisi::class)->cikis($bakiye, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
                }
            }

            $kaynakDurumlari = [];
            $kaynakKalanMiktarlari = [];
            foreach ($parcalar as $parca) {
                $kaynakDurumlari[(string) $parca->id] = (string) $parca->parca_durumu;
                $kaynakKalanMiktarlari[(string) $parca->id] = (string) $parca->kalan_miktar;
                $parca->update(['kalan_miktar' => '0', 'parca_durumu' => 'birlestirildi']);
            }
            $this->log($stok, 'birlestirme', $ilk->ustParca()->withoutGlobalScopes()->first(), $yeni, [
                'kaynak_parca_idleri' => $parcalar->pluck('id')->values()->all(),
                'kaynak_durumlari' => $kaynakDurumlari,
                'kaynak_kalan_miktarlari' => $kaynakKalanMiktarlari,
                'kaynak_bakiyeleri' => $kaynakBakiyeVerileri,
                'hedef_parca_id' => $yeni->id,
                'hedef_parca_kodu' => $kod,
                'toplam_ana_miktar' => $toplam,
                'birim_maliyet' => $birimMaliyet,
            ]);

            return ['kaynaklar' => $parcalar->fresh(), 'parca' => $yeni->refresh()];
        });
    }

    /** Hareket görmemiş bir fiziksel birleştirmeyi kaynak parçalara geri dağıtır. */
    public function birlesmeyiGeriAl(StokParcaIslemLogu $islem): StokParcaIslemLogu
    {
        return DB::transaction(function () use ($islem): StokParcaIslemLogu {
            $log = StokParcaIslemLogu::withoutGlobalScopes()->lockForUpdate()->findOrFail($islem->getKey());
            if ($log->islem_turu !== 'birlestirme' || ($log->veri['geri_alindi'] ?? false)) {
                throw new IsKuraliIstisnasi('Yalnızca geri alınmamış bir parça birleştirmesi seçilebilir.');
            }
            $hedefId = (int) ($log->veri['hedef_parca_id'] ?? 0);
            $kaynakIdleri = array_map('intval', (array) ($log->veri['kaynak_parca_idleri'] ?? []));
            $hedef = StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($hedefId);
            $kaynaklar = StokParcasi::withoutGlobalScopes()->whereIn('id', $kaynakIdleri)->lockForUpdate()->get();
            if (StokHareketiParcasi::withoutGlobalScopes()->where('stok_parcasi_id', $hedefId)->exists()
                || StokParcasi::withoutGlobalScopes()->where('ust_parca_id', $hedefId)->exists()) {
                throw new IsKuraliIstisnasi('Birleşik parça hareket veya bölme gördüğü için birleştirme geri alınamaz.');
            }
            $hedefBakiyeler = $hedef->olcuBakiyeleri()->withoutGlobalScopes()->where('ana_miktar', '>', 0)->lockForUpdate()->get();
            foreach ((array) ($log->veri['kaynak_bakiyeleri'] ?? []) as $eski) {
                $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()->lockForUpdate()->findOrFail((int) $eski['bakiye_id']);
                app(StokOlcuBakiyeServisi::class)->giris($bakiye, (string) $eski['ana_miktar'], (string) $eski['adet_esdegeri']);
            }
            foreach ($hedefBakiyeler as $bakiye) {
                app(StokOlcuBakiyeServisi::class)->cikis($bakiye, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
            }
            $durumlar = (array) ($log->veri['kaynak_durumlari'] ?? []);
            $kalanMiktarlar = (array) ($log->veri['kaynak_kalan_miktarlari'] ?? []);
            foreach ($kaynaklar as $kaynak) {
                $kaynak->update([
                    'kalan_miktar' => $kalanMiktarlar[(string) $kaynak->id] ?? $kaynak->giren_miktar,
                    'parca_durumu' => $durumlar[(string) $kaynak->id] ?? 'aktif',
                ]);
            }
            $hedef->update(['kalan_miktar' => '0', 'parca_durumu' => 'geri_alindi']);
            $veri = $log->veri;
            $veri['geri_alindi'] = true;
            $veri['geri_alinma_tarihi'] = now()->toIso8601String();
            $log->update(['veri' => $veri]);
            $stok = StokKarti::withoutGlobalScopes()->findOrFail($log->stok_id);
            $this->log($stok, 'birlestirme_geri_alma', $hedef->ustParca()->withoutGlobalScopes()->first(), $hedef, [
                'birlestirme_log_id' => $log->id,
                'kaynak_parca_idleri' => $kaynakIdleri,
                'kaynak_parca_sayisi' => count($kaynakIdleri),
                'toplam_ana_miktar' => (string) ($log->veri['toplam_ana_miktar'] ?? $hedef->giren_miktar),
            ]);

            return $log->refresh();
        });
    }

    /**
     * Fiziksel parçanın güncel ana birim (ör. m²) maliyetini değiştirir.
     * Önceki stok hareketlerindeki maliyet anlık görüntülerine dokunulmaz.
     */
    public function parcaMaliyetiniGuncelle(StokParcasi $parca, mixed $yeniMaliyet): StokParcasi
    {
        $maliyet = bcadd($this->decimal($yeniMaliyet), '0', 8);
        if (bccomp($maliyet, '0', 8) < 0) {
            throw new IsKuraliIstisnasi('Parça maliyeti negatif olamaz.');
        }

        return DB::transaction(function () use ($parca, $maliyet): StokParcasi {
            $kilitli = StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($parca->getKey());
            if (! $kilitli->parca_mi) {
                throw new IsKuraliIstisnasi('Maliyet yalnızca fiziksel stok parçalarında değiştirilebilir.');
            }

            $eskiMaliyet = $this->decimal($kilitli->birim_maliyet);
            if (bccomp($eskiMaliyet, $maliyet, 8) === 0) {
                throw new IsKuraliIstisnasi('Yeni parça maliyeti mevcut maliyetle aynı.');
            }

            $stok = StokKarti::withoutGlobalScopes()->findOrFail($kilitli->stok_id);
            $kilitli->update(['birim_maliyet' => $maliyet]);
            $this->log($stok, 'maliyet_guncelleme', $kilitli->ustParca()->withoutGlobalScopes()->first(), $kilitli, [
                'parca_kodu' => $kilitli->parca_kodu,
                'eski_birim_maliyet' => $eskiMaliyet,
                'yeni_birim_maliyet' => $maliyet,
                'maliyet_birimi' => 'ana_birim',
            ]);

            return $kilitli->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function parcaBilgisiFormVerisi(StokParcasi $parca): array
    {
        $bakiye = $parca->olcuBakiyeleri()
            ->withoutGlobalScopes()
            ->where('ana_miktar', '>', 0)
            ->first();
        $olcu = $bakiye?->olcu()->withoutGlobalScopes()->first();

        return [
            'ana_miktar' => $bakiye?->ana_miktar ?: $parca->kalan_miktar,
            'renk_desen' => $parca->renk_desen,
            'kalite_sinifi' => $parca->kalite_sinifi,
            ...$this->olcuFormVerisi($olcu, $bakiye?->ana_miktar),
        ];
    }

    /**
     * Kalan fiziksel parçanın güncel ölçü ve sınıflandırma bilgisini değiştirir.
     * Geçmiş hareket ölçüleri korunur; yalnız güncel bakiye yeni ölçü tanımına taşınır.
     *
     * @param  array<string, mixed>  $veri
     */
    public function parcaBilgileriniGuncelle(StokParcasi $parca, array $veri): StokParcasi
    {
        return DB::transaction(function () use ($parca, $veri): StokParcasi {
            $kilitli = StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($parca->getKey());
            if (! $kilitli->parca_mi || bccomp((string) $kilitli->kalan_miktar, '0', 8) <= 0) {
                throw new IsKuraliIstisnasi('Yalnızca kalan bakiyesi olan fiziksel stok parçası güncellenebilir.');
            }

            $stok = StokKarti::withoutGlobalScopes()->findOrFail($kilitli->stok_id);
            $bakiyeler = $kilitli->olcuBakiyeleri()
                ->withoutGlobalScopes()
                ->where('ana_miktar', '>', 0)
                ->lockForUpdate()
                ->get();
            if ($bakiyeler->count() > 1) {
                throw new IsKuraliIstisnasi('Bir fiziksel parçada birden fazla aktif ölçü bakiyesi bulunduğu için bilgiler güncellenemez.');
            }

            $eskiRenk = $kilitli->renk_desen;
            $eskiKalite = $kilitli->kalite_sinifi;
            $yeniRenk = $this->metinVeyaNull($veri['renk_desen'] ?? null);
            $yeniKalite = $this->metinVeyaNull($veri['kalite_sinifi'] ?? null);
            $eskiOlcuId = null;
            $yeniOlcuId = null;
            $bakiye = $bakiyeler->first();

            if ($bakiye) {
                $kaynakOlcu = $bakiye->olcu()->withoutGlobalScopes()->firstOrFail();
                $eskiOlcuId = (int) $kaynakOlcu->id;
                $yeniOlcu = $this->parcaOlcusunuCoz(
                    $stok,
                    $kaynakOlcu,
                    $veri,
                    (string) ($kilitli->parca_kodu ?: $kilitli->parca_kodu),
                    (string) $bakiye->ana_miktar,
                );
                $yeniOlcuId = (int) $yeniOlcu->id;

                if ($yeniOlcuId !== $eskiOlcuId) {
                    $depo = Depo::withoutGlobalScopes()->findOrFail((int) $bakiye->depo_id);
                    $donusum = $bakiye->donusum_ana_miktari ?: $kaynakOlcu->bir_adet_ana_miktar;
                    $hedef = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur(
                        (int) $kilitli->firma_id,
                        $stok,
                        $yeniOlcu,
                        $depo,
                        $kilitli,
                        $donusum,
                    );
                    app(StokOlcuBakiyeServisi::class)->giris($hedef, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
                    app(StokOlcuBakiyeServisi::class)->cikis($bakiye, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
                }
            }

            if ($eskiOlcuId === $yeniOlcuId && $eskiRenk === $yeniRenk && $eskiKalite === $yeniKalite) {
                throw new IsKuraliIstisnasi('Parça bilgilerinde değişiklik yapılmadı.');
            }

            $kilitli->update([
                'renk_desen' => $yeniRenk,
                'kalite_sinifi' => $yeniKalite,
                'kalinlik_cm' => isset($yeniOlcu) ? $this->kalinlikCm($yeniOlcu) : $kilitli->kalinlik_cm,
                'metrekare' => ($stok->olculu_takip_turu instanceof OlculuStokTakipTuru ? $stok->olculu_takip_turu : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu)) === OlculuStokTakipTuru::Alan
                    ? ($bakiye?->ana_miktar ?: $kilitli->metrekare)
                    : $kilitli->metrekare,
            ]);
            $this->log($stok, 'bilgi_guncelleme', $kilitli->ustParca()->withoutGlobalScopes()->first(), $kilitli, [
                'parca_kodu' => $kilitli->parca_kodu,
                'eski_stok_olcusu_id' => $eskiOlcuId,
                'yeni_stok_olcusu_id' => $yeniOlcuId,
                'eski_renk_desen' => $eskiRenk,
                'yeni_renk_desen' => $yeniRenk,
                'eski_kalite_sinifi' => $eskiKalite,
                'yeni_kalite_sinifi' => $yeniKalite,
            ]);

            return $kilitli->refresh();
        });
    }

    /** Henüz hareket veya fiziksel bölme görmemiş bir parça dönüşümünü güvenle geri alır. */
    public function donusumuGeriAl(StokParcasi $anaParca): StokParcasi
    {
        return DB::transaction(function () use ($anaParca): StokParcasi {
            $ana = StokParcasi::withoutGlobalScopes()->lockForUpdate()->findOrFail($anaParca->getKey());
            if ($ana->parca_mi || $ana->parca_durumu !== 'donusturuldu') {
                throw new IsKuraliIstisnasi('Yalnızca tamamlanmış ana parti dönüşümü geri alınabilir.');
            }
            $parcalar = StokParcasi::withoutGlobalScopes()->where('ust_parca_id', $ana->id)->lockForUpdate()->get();
            if ($parcalar->isEmpty()) {
                throw new IsKuraliIstisnasi('Geri alınacak stok parçası bulunamadı.');
            }
            if (StokParcasi::withoutGlobalScopes()->whereIn('ust_parca_id', $parcalar->pluck('id'))->exists()
                || StokParcasi::withoutGlobalScopes()->whereIn('id', $parcalar->pluck('id'))->where('parca_durumu', 'bolundu')->exists()
                || StokHareketiParcasi::withoutGlobalScopes()->whereIn('stok_parcasi_id', $parcalar->pluck('id'))->exists()) {
                throw new IsKuraliIstisnasi('Parçalardan biri hareket veya bölme gördüğü için dönüşüm geri alınamaz.');
            }

            $stok = StokKarti::withoutGlobalScopes()->findOrFail($ana->stok_id);
            $toplam = $parcalar->reduce(
                fn (string $biriken, StokParcasi $parca): string => bcadd($biriken, (string) $parca->kalan_miktar, 8),
                '0',
            );
            foreach ($parcalar as $parca) {
                foreach ($parca->olcuBakiyeleri()->withoutGlobalScopes()->where('ana_miktar', '>', 0)->lockForUpdate()->get() as $bakiye) {
                    $olcu = $bakiye->olcu()->withoutGlobalScopes()->firstOrFail();
                    $depo = Depo::withoutGlobalScopes()->findOrFail($bakiye->depo_id);
                    $hedef = app(StokOlcuBakiyeServisi::class)->bakiyeBulVeyaOlustur((int) $ana->firma_id, $stok, $olcu, $depo, null, $bakiye->donusum_ana_miktari);
                    app(StokOlcuBakiyeServisi::class)->giris($hedef, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
                    app(StokOlcuBakiyeServisi::class)->cikis($bakiye, (string) $bakiye->ana_miktar, (string) $bakiye->adet_esdegeri);
                }
                $parca->update(['kalan_miktar' => '0', 'parca_durumu' => 'geri_alindi']);
            }
            $ana->update(['parca_durumu' => 'geri_alindi', 'kalan_miktar' => '0']);
            $this->log($stok, 'geri_alma', $ana, null, [
                'parca_sayisi' => $parcalar->count(),
                'parca_idleri' => $parcalar->pluck('id')->values()->all(),
                'toplam_ana_miktar' => $toplam,
            ]);

            return $ana->refresh();
        });
    }

    private function anaParcaOlustur(StokKarti $stok, string $miktar): StokParcasi
    {
        $kod = 'LOT-'.strtoupper((string) ($stok->kod ?: 'STK'.$stok->id)).'-'.now()->format('YmdHis');

        return StokParcasi::query()->create([
            'firma_id' => $stok->firma_id,
            'stok_id' => $stok->id,
            'depo_id' => $stok->depo_id,
            'parca_kodu' => $kod,
            'giren_miktar' => $miktar,
            'kalan_miktar' => '0',
            'parca_mi' => false,
            'parca_durumu' => 'donusturuldu',
        ]);
    }

    private function esitMaliyet(StokKarti $stok, string $toplam, string $miktar): string
    {
        // Stok kartındaki güncel maliyet ana ölçü birimi (ör. m²) maliyetidir.
        // Parçalar farklı miktarlarda olsa da başlangıç birim maliyeti aynı kalır.
        return $this->decimal($stok->guncel_birim_maliyet);
    }

    /** @return array<int, array<string, mixed>> */
    private function esitOneriSatirlari(string $toplam, int $adet): array
    {
        $satirlar = [];
        $dagitilan = '0';
        $esit = bcdiv($toplam, (string) $adet, 8);
        for ($sira = 1; $sira <= $adet; $sira++) {
            $miktar = $sira === $adet ? bcsub($toplam, $dagitilan, 8) : $esit;
            $dagitilan = bcadd($dagitilan, $miktar, 8);
            $satirlar[] = [
                'stok_olcu_bakiyesi_id' => null,
                'stok_olcusu_id' => null,
                'olcu_kaynagi' => 'Standart stok',
                'ana_miktar' => $miktar,
                'parca_kodu' => '',
                'maliyet' => null,
                ...$this->olcuFormVerisi(null),
            ];
        }

        return $satirlar;
    }

    private function olcuKaynagiEtiketi(StokOlcuBakiyesi $bakiye): string
    {
        $olcu = $bakiye->relationLoaded('olcu') ? $bakiye->olcu : $bakiye->olcu()->withoutGlobalScopes()->first();
        $ad = trim((string) ($olcu?->ad ?: $olcu?->kod ?: 'Ölçü #'.$bakiye->stok_olcusu_id));

        return $ad.' · '.$this->decimal($bakiye->ana_miktar).' ana miktar · '.$this->decimal($bakiye->adet_esdegeri).' adet';
    }

    /** @return array<string, mixed> */
    private function olcuFormVerisi(?StokOlcusu $olcu, ?string $anaMiktar = null): array
    {
        $veri = [
            'takip_turu' => $olcu?->takip_turu?->value,
            'olcu_birimi' => $olcu?->olcu_birimi ?: 'cm',
            'en' => $olcu?->en,
            'boy' => $olcu?->boy,
            'yukseklik' => $olcu?->yukseklik,
            'bir_adet_agirlik' => $olcu?->bir_adet_agirlik,
            'agirlik_birimi' => $olcu?->agirlik_birimi ?: 'kg',
        ];

        if (! $olcu || $anaMiktar === null) {
            return $veri;
        }

        $tur = $olcu->takip_turu instanceof OlculuStokTakipTuru
            ? $olcu->takip_turu
            : OlculuStokTakipTuru::tryFrom((string) $olcu->takip_turu);
        $anaMiktar = $this->pozitif($anaMiktar, 'Parça ana miktarı');
        if ($tur === OlculuStokTakipTuru::Uzunluk) {
            $veri['boy'] = $this->metredenOlcuBirimine($anaMiktar, (string) $veri['olcu_birimi']);
        } elseif ($tur === OlculuStokTakipTuru::Alan && bccomp((string) $olcu->en_m, '0', 8) > 0) {
            $boyMetre = bcdiv($anaMiktar, (string) $olcu->en_m, 16);
            $veri['boy'] = $this->metredenOlcuBirimine($boyMetre, (string) $veri['olcu_birimi']);
        } elseif ($tur === OlculuStokTakipTuru::Hacim
            && bccomp((string) $olcu->en_m, '0', 8) > 0
            && bccomp((string) $olcu->boy_m, '0', 8) > 0) {
            $taban = bcmul((string) $olcu->en_m, (string) $olcu->boy_m, 16);
            $yukseklikMetre = bcdiv($anaMiktar, $taban, 16);
            $veri['yukseklik'] = $this->metredenOlcuBirimine($yukseklikMetre, (string) $veri['olcu_birimi']);
        } elseif ($tur === OlculuStokTakipTuru::Agirlik) {
            $veri['bir_adet_agirlik'] = $this->kilogramdanAgirlikBirimine($anaMiktar, (string) $veri['agirlik_birimi']);
        }

        return $veri;
    }

    /**
     * Kullanıcı kaynak ölçüyü değiştirmediyse mevcut tanımı kullanır. Değiştirdiyse
     * yalnız bu fiziksel parçaya ait yeni ölçü tanımı üretir.
     *
     * @param  array<string, mixed>  $parca
     */
    private function parcaOlcusunuCoz(StokKarti $stok, StokOlcusu $kaynak, array $parca, string $parcaKodu, string $anaMiktar): StokOlcusu
    {
        $varsayilan = $this->olcuFormVerisi($kaynak, $anaMiktar);
        $cozulmus = array_merge($varsayilan, array_filter($parca, static fn ($value): bool => $value !== null && $value !== ''));
        if (! $this->olcuDegistiMi($kaynak, $cozulmus)) {
            if (bccomp((string) $kaynak->bir_adet_ana_miktar, $anaMiktar, 8) !== 0) {
                throw new IsKuraliIstisnasi('Fiziksel parça miktarı ile ölçüsü aynı değil. Önerilen ölçüleri kontrol edin.');
            }

            return $kaynak;
        }

        $veri = [
            'kod' => $this->parcaOlcuKodu($parcaKodu),
            'ad' => $parcaKodu.' parça ölçüsü',
            'olcu_birimi' => (string) ($cozulmus['olcu_birimi'] ?? $kaynak->olcu_birimi ?? 'cm'),
            'en' => $this->degerVeyaNull($cozulmus['en'] ?? $kaynak->en),
            'boy' => $this->degerVeyaNull($cozulmus['boy'] ?? $kaynak->boy),
            'yukseklik' => $this->degerVeyaNull($cozulmus['yukseklik'] ?? $kaynak->yukseklik),
            'bir_adet_agirlik' => $this->degerVeyaNull($cozulmus['bir_adet_agirlik'] ?? $kaynak->bir_adet_agirlik),
            'agirlik_birimi' => (string) ($cozulmus['agirlik_birimi'] ?? $kaynak->agirlik_birimi ?? 'kg'),
            'agirlik_turu' => $kaynak->agirlik_turu,
        ];

        try {
            $olcu = app(StokOlcuBakiyeServisi::class)->olcuOlustur((int) $stok->firma_id, $stok, $veri);
        } catch (InvalidArgumentException $exception) {
            throw new IsKuraliIstisnasi('Parça ölçüsü kaydedilemedi: '.$exception->getMessage(), previous: $exception);
        }

        if (bccomp((string) $olcu->bir_adet_ana_miktar, $anaMiktar, 8) !== 0) {
            throw new IsKuraliIstisnasi('Fiziksel parça ölçüsünün hesaplanan ana miktarı, parça ana miktarıyla aynı olmalıdır.');
        }

        return $olcu;
    }

    /** @param array<string, mixed> $parca */
    private function olcuDegistiMi(StokOlcusu $kaynak, array $parca): bool
    {
        foreach (['en', 'boy', 'yukseklik', 'bir_adet_agirlik'] as $alan) {
            if ($this->sayisalEsitMi($kaynak->{$alan}, $parca[$alan] ?? $kaynak->{$alan}) === false) {
                return true;
            }
        }

        return mb_strtolower(trim((string) ($kaynak->olcu_birimi ?: 'cm')), 'UTF-8') !== mb_strtolower(trim((string) ($parca['olcu_birimi'] ?? 'cm')), 'UTF-8')
            || mb_strtolower(trim((string) ($kaynak->agirlik_birimi ?: 'kg')), 'UTF-8') !== mb_strtolower(trim((string) ($parca['agirlik_birimi'] ?? 'kg')), 'UTF-8');
    }

    private function sayisalEsitMi(mixed $sol, mixed $sag): bool
    {
        $solBos = $sol === null || trim((string) $sol) === '';
        $sagBos = $sag === null || trim((string) $sag) === '';
        if ($solBos || $sagBos) {
            return $solBos && $sagBos;
        }

        $sag = str_replace(',', '.', trim((string) $sag));

        return is_numeric($sag) && bccomp((string) $sol, $sag, 8) === 0;
    }

    private function parcaOlcuKodu(string $parcaKodu): string
    {
        $temel = preg_replace('/[^A-Z0-9_-]+/u', '-', mb_strtoupper($parcaKodu, 'UTF-8')) ?: 'PARCA';
        $anaKod = mb_substr($temel, 0, 48, 'UTF-8').'-'.substr(sha1($parcaKodu), 0, 10);
        $kod = $anaKod;
        $revizyon = 1;
        while (StokOlcusu::withoutGlobalScopes()->where('kod', $kod)->exists()) {
            $kod = mb_substr($anaKod, 0, 59, 'UTF-8').'-R'.str_pad((string) $revizyon, 3, '0', STR_PAD_LEFT);
            $revizyon++;
        }

        return $kod;
    }

    private function metredenOlcuBirimine(string $metre, string $birim): string
    {
        return match (mb_strtolower(trim($birim), 'UTF-8')) {
            'mm' => bcmul($metre, '1000', 8),
            'cm' => bcmul($metre, '100', 8),
            default => bcadd($metre, '0', 8),
        };
    }

    private function kilogramdanAgirlikBirimine(string $kilogram, string $birim): string
    {
        return match (mb_strtolower(trim($birim), 'UTF-8')) {
            'g', 'gram' => bcmul($kilogram, '1000', 8),
            't', 'ton' => bcdiv($kilogram, '1000', 8),
            default => bcadd($kilogram, '0', 8),
        };
    }

    private function kalinlikCm(StokOlcusu $olcu): ?string
    {
        return $olcu->yukseklik_m === null ? null : bcmul((string) $olcu->yukseklik_m, '100', 3);
    }

    private function degerVeyaNull(mixed $value): mixed
    {
        return $value === null || trim((string) $value) === '' ? null : str_replace(',', '.', trim((string) $value));
    }

    private function metinVeyaNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function pozitif(mixed $value, string $label): string
    {
        $number = $this->decimal($value);
        if (bccomp($number, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi($label.' sıfırdan büyük olmalıdır.');
        }

        return $number;
    }

    private function decimal(mixed $value): string
    {
        $number = str_replace(',', '.', trim((string) ($value ?? '0')));
        if ($number === '' || ! is_numeric($number)) {
            throw new IsKuraliIstisnasi('Geçerli bir sayısal miktar girilmelidir.');
        }

        return $number;
    }

    private function yeniParcaKodu(StokParcasi $kaynak, int $sira): string
    {
        $temel = (string) ($kaynak->parca_kodu ?: $kaynak->parca_kodu);
        $deneme = $temel.'-SPL-'.str_pad((string) $sira, 4, '0', STR_PAD_LEFT);
        $sayac = $sira;
        while (StokParcasi::withoutGlobalScopes()->where('firma_id', $kaynak->firma_id)->where('parca_kodu', $deneme)->exists()) {
            $sayac++;
            $deneme = $temel.'-SPL-'.str_pad((string) $sayac, 4, '0', STR_PAD_LEFT);
        }

        return $deneme;
    }

    private function yeniBirlesikParcaKodu(StokParcasi $kaynak): string
    {
        $temel = (string) ($kaynak->ustParca?->parca_kodu ?: $kaynak->parca_kodu ?: $kaynak->parca_kodu);
        $sayac = 1;
        do {
            $deneme = $temel.'-MRG-'.str_pad((string) $sayac, 4, '0', STR_PAD_LEFT);
            $sayac++;
        } while (StokParcasi::withoutGlobalScopes()->where('firma_id', $kaynak->firma_id)
            ->where(fn ($query) => $query->where('parca_kodu', $deneme)->orWhere('barkod', $deneme))->exists());

        return $deneme;
    }

    /** @param Collection<int, StokParcasi> $parcalar */
    private function birlesebilirParcalariDogrula(Collection $parcalar, ?int $beklenenAdet = null): void
    {
        if ($parcalar->count() < 2 || ($beklenenAdet !== null && $parcalar->count() !== $beklenenAdet)) {
            throw new IsKuraliIstisnasi('Birleştirme için en az iki geçerli stok parçası seçilmelidir.');
        }
        if ($parcalar->contains(fn (StokParcasi $p): bool => ! $p->parca_mi || bccomp((string) $p->kalan_miktar, '0', 8) <= 0)) {
            throw new IsKuraliIstisnasi('Yalnızca kalan bakiyesi olan fiziksel stok parçaları birleştirilebilir.');
        }
        foreach (['firma_id', 'stok_id', 'depo_id', 'ust_parca_id'] as $alan) {
            if ($parcalar->pluck($alan)->map(fn ($v) => $v === null ? null : (int) $v)->unique(strict: true)->count() !== 1) {
                throw new IsKuraliIstisnasi('Birleştirilecek parçalar aynı firma, stok kartı, depo ve ana partiye ait olmalıdır.');
            }
        }
        if (StokParcasi::withoutGlobalScopes()->whereIn('ust_parca_id', $parcalar->pluck('id'))->exists()) {
            throw new IsKuraliIstisnasi('Daha önce fiziksel olarak bölünmüş ve alt parçası olan kayıtlar birleştirilemez.');
        }
    }

    private function yeniIlkParcaKodu(int $firmaId, string $firmaKodu, string $stokKodu, int $sira): string
    {
        $temel = ($firmaKodu !== '' ? $firmaKodu.'-' : '').$stokKodu.'-PLK-';
        $sayac = max(1, $sira);
        do {
            $deneme = $temel.str_pad((string) $sayac, 4, '0', STR_PAD_LEFT);
            $sayac++;
        } while (StokParcasi::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->where(function ($query) use ($deneme): void {
                $query->where('parca_kodu', $deneme)->orWhere('barkod', $deneme);
            })
            ->exists());

        return $deneme;
    }

    private function donusturulebilirAnaPartiyiDogrula(StokParcasi $parti, ?StokKarti $stok = null): void
    {
        if ($parti->parca_mi || bccomp((string) $parti->kalan_miktar, '0', 8) <= 0) {
            throw new IsKuraliIstisnasi('Yalnızca kalan bakiyesi olan aktif bir ana parti stok parçalarına dönüştürülebilir.');
        }
        if ($stok && ((int) $parti->firma_id !== (int) $stok->firma_id || (int) $parti->stok_id !== (int) $stok->getKey())) {
            throw new IsKuraliIstisnasi('Ana parti seçilen stok kartına ait değil.');
        }
        if (StokParcasi::withoutGlobalScopes()
            ->where('firma_id', $parti->firma_id)
            ->where('ust_parca_id', $parti->getKey())
            ->where('kalan_miktar', '>', 0)
            ->exists()) {
            throw new IsKuraliIstisnasi('Bu ana partinin zaten aktif stok parçaları var.');
        }
    }

    private function log(StokKarti $stok, string $tur, ?StokParcasi $anaParca, ?StokParcasi $kaynak, array $veri): void
    {
        StokParcaIslemLogu::withoutGlobalScopes()->create([
            'firma_id' => $stok->firma_id, 'stok_id' => $stok->id, 'islem_turu' => $tur,
            'ana_parca_id' => $anaParca?->id, 'kaynak_parca_id' => $kaynak?->id,
            'kullanici_id' => auth()->id(), 'veri' => $veri,
        ]);
    }
}
