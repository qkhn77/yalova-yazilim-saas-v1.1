<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\FaturaKalemiOlcuDagilimi;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokHareketiOlcuDagilimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Models\Muhasebe\StokParcasi;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Ortak fatura/teklif/sipariş işlemlerinde kullanılacak ölçülü kalem sözleşmesi. */
class FaturaOlcuKalemiServisi
{
    public function __construct(private readonly StokOlcuHesaplamaServisi $hesap, private readonly StokOlcuBakiyeServisi $bakiyeler) {}

    /**
     * Taslak kalemin ölçü dağılımlarını sunucu tarafında yeniden hesaplayıp saklar.
     * @param array<int, array<string, mixed>> $satirlar
     */
    public function dagilimlariSakla(FaturaKalemi $kalem, array $satirlar, bool $cikis = false): void
    {
        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->findOrFail($kalem->stok_id);
        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru ? $stok->olculu_takip_turu : OlculuStokTakipTuru::tryFrom((string) $stok->olculu_takip_turu);
        if (! $tur?->olculuMu()) {
            throw new InvalidArgumentException('Standart stok kalemi ölçü dağılımı içeremez.');
        }
        if ($satirlar === []) {
            throw new InvalidArgumentException('Ölçülü fatura kaleminde en az bir ölçü seçilmelidir.');
        }

        $dagilimlar = [];
        $toplamAna = '0';
        $toplamAdet = '0';
        foreach (array_values($satirlar) as $sira => $satir) {
            $olcu = StokOlcusu::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->where('stok_id', $stok->id)->whereKey((int) ($satir['stok_olcusu_id'] ?? 0))->firstOrFail();
            if (! $olcu->aktif_mi) throw new InvalidArgumentException('Pasif ölçü yeni faturada seçilemez.');
            $depo = Depo::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->whereKey((int) ($satir['depo_id'] ?? $kalem->depo_id ?? $stok->depo_id ?? 0))->firstOrFail();
            $birimId = (int) ($satir['islem_birimi_id'] ?? $stok->ana_birim_id);
            $girilen = $this->hesap->kaydet((string) ($satir['girilen_miktar'] ?? '0'));
            if (bccomp($girilen, '0', 8) <= 0) throw new InvalidArgumentException('Ölçü dağılım miktarı pozitif olmalıdır.');
            $faktor = (string) $olcu->bir_adet_ana_miktar;
            if ($faktor === '' || bccomp($faktor, '0', 8) <= 0) throw new InvalidArgumentException('Ölçü dönüşüm katsayısı bulunamadı.');
            $ana = $birimId === (int) $stok->ikincil_birim_id ? $this->hesap->adettenAnaMiktara($girilen, $faktor) : $girilen;
            $adet = $this->hesap->anaMiktardanAdede($ana, $faktor);
            $partiId = isset($satir['stok_parcasi_id']) && $satir['stok_parcasi_id'] ? (int) $satir['stok_parcasi_id'] : null;
            $bakiye = null;
            if (! empty($satir['stok_olcu_bakiyesi_id'])) {
                $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()
                    ->where('firma_id', $kalem->firma_id)
                    ->where('stok_id', $stok->id)
                    ->where('stok_olcusu_id', $olcu->id)
                    ->where('depo_id', $depo->id)
                    ->findOrFail((int) $satir['stok_olcu_bakiyesi_id']);
                $bakiyePartiId = $bakiye->stok_parcasi_id ? (int) $bakiye->stok_parcasi_id : null;
                if ($partiId !== null && $partiId !== $bakiyePartiId) {
                    throw new InvalidArgumentException('Ölçü bakiyesi parti bağlantısı ile uyumlu değil.');
                }
                $partiId ??= $bakiyePartiId;
            }
            $parti = $partiId
                ? StokParcasi::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->where('stok_id', $stok->id)->where('depo_id', $depo->id)->findOrFail($partiId)
                : null;
            if ($bakiye && (int) ($bakiye->parca_kapsami ?? 0) !== (int) ($parti?->id ?? 0)) {
                throw new InvalidArgumentException('Ölçü bakiyesi parti bağlantısı ile uyumlu değil.');
            }
            if ($cikis && ! $bakiye) {
                throw new InvalidArgumentException('Ölçülü çıkışta ölçü bakiyesi seçimi zorunludur.');
            }
            $toplamAna = bcadd($toplamAna, $ana, 8);
            $toplamAdet = bcadd($toplamAdet, $adet, 8);
            $dagilimlar[] = array_merge(compact('sira', 'olcu', 'depo', 'parti', 'bakiye', 'birimId', 'girilen', 'ana', 'adet', 'faktor'), [
                'kaynak_olcu_dagilimi_id' => $satir['kaynak_olcu_dagilimi_id'] ?? null,
            ]);
        }

        DB::transaction(function () use ($kalem, $stok, $dagilimlar, $toplamAna, $toplamAdet): void {
            $kalem->olcuDagilimlari()->delete();
            foreach ($dagilimlar as $d) {
                FaturaKalemiOlcuDagilimi::create([
                    'firma_id' => $kalem->firma_id, 'fatura_kalemi_id' => $kalem->id,
                    'kaynak_olcu_dagilimi_id' => isset($d['kaynak_olcu_dagilimi_id']) ? (int) $d['kaynak_olcu_dagilimi_id'] : null,
                    'stok_id' => $stok->id,
                    'stok_olcusu_id' => $d['olcu']->id, 'stok_olcu_bakiyesi_id' => $d['bakiye']?->id, 'depo_id' => $d['depo']->id,
                    'stok_parcasi_id' => $d['parti']?->id, 'islem_birimi_id' => $d['birimId'], 'girilen_miktar' => $d['girilen'],
                    'ana_miktar' => $d['ana'], 'adet_esdegeri' => $d['adet'], 'sira' => $d['sira'],
                    'takip_turu' => $d['olcu']->takip_turu->value, 'olcu_birimi' => $d['olcu']->olcu_birimi,
                    'en' => $d['olcu']->en, 'boy' => $d['olcu']->boy, 'yukseklik' => $d['olcu']->yukseklik,
                    'en_m' => $d['olcu']->en_m, 'boy_m' => $d['olcu']->boy_m, 'yukseklik_m' => $d['olcu']->yukseklik_m,
                    'bir_adet_ana_miktar' => $d['faktor'],
                ]);
            }
            $guncellenecek = ['ana_miktar' => $toplamAna, 'adet_esdegeri' => $toplamAdet, 'islem_birimi_id' => $dagilimlar[0]['birimId']];
            $otomatikPartiDagilimi = [];
            foreach ($dagilimlar as $dagilim) {
                if (! $dagilim['parti']) {
                    continue;
                }
                $parcaKodu = (string) $dagilim['parti']->parca_kodu;
                $otomatikPartiDagilimi[$parcaKodu] = bcadd($otomatikPartiDagilimi[$parcaKodu] ?? '0', $dagilim['ana'], 8);
            }
            if ($otomatikPartiDagilimi !== []) {
                $beklenen = collect($otomatikPartiDagilimi)->map(fn (string $miktar, $parcaKodu): array => [
                    'parca_kodu' => (string) $parcaKodu,
                    'miktar' => $miktar,
                ])->values()->all();
                $mevcut = collect((array) ($kalem->parca_dagilimi ?? []))->mapWithKeys(fn (array $satir): array => [
                    trim((string) ($satir['parca_kodu'] ?? '')) => bcadd((string) ($satir['miktar'] ?? '0'), '0', 8),
                ])->filter(fn (string $miktar, string $parcaKodu): bool => $parcaKodu !== '')->all();
                if ($mevcut !== []) {
                    ksort($mevcut);
                    $kontrol = $otomatikPartiDagilimi;
                    ksort($kontrol);
                    if ($mevcut !== $kontrol) {
                        throw new InvalidArgumentException('Parti dağılımı, seçilen fiziksel ölçü bakiyeleriyle aynı olmalıdır.');
                    }
                }
                $guncellenecek['parca_dagilimi'] = $beklenen;
                $guncellenecek['parca_kodu'] = count($beklenen) === 1 ? $beklenen[0]['parca_kodu'] : null;
            }
            $kalem->update($guncellenecek);
        });
    }

    /** Tek sabit ölçülü stoklarda seçim yapılmadan güvenli dağılım üretir. */
    public function tekOlcuDagiliminiOtomatikTamamla(FaturaKalemi $kalem, bool $cikis = false): void
    {
        if ($kalem->olcuDagilimlari()->exists() || ! $kalem->stok_id) {
            return;
        }

        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->find((int) $kalem->stok_id);
        $tur = $stok?->olculu_takip_turu instanceof OlculuStokTakipTuru
            ? $stok->olculu_takip_turu
            : OlculuStokTakipTuru::tryFrom((string) ($stok?->olculu_takip_turu ?? 'standart'));
        if (! $stok || ! $tur?->olculuMu()) {
            return;
        }

        $olculer = StokOlcusu::withoutGlobalScopes()
            ->where('firma_id', $kalem->firma_id)
            ->where('stok_id', $stok->id)
            ->where('aktif_mi', true)
            ->get();
        if ($olculer->count() !== 1) {
            return;
        }

        $olcu = $olculer->first();
        $depoId = (int) ($kalem->depo_id ?: $stok->depo_id);
        $bakiyeId = null;
        // Eski taslaklarda miktar ana birimde (m²), yeni kayıtlarda ise
        // seçilen satış biriminde tutulabilir; snapshot bu ayrımı korur.
        $snapshot = json_decode((string) $kalem->olcu_donusum_snapshot, true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $anaBirimId = (int) ($stok->ana_birim_id ?: 0);
        $ikincilBirimId = (int) ($stok->ikincil_birim_id ?: 0);
        $snapshotFiyatBirimiId = (int) ($snapshot['fiyat_birimi_id'] ?? 0);
        $snapshotFiyatBirimi = (string) ($snapshot['fiyat_birimi'] ?? '');
        if ($snapshotFiyatBirimi === 'ana' || ($snapshotFiyatBirimiId > 0 && $snapshotFiyatBirimiId === $anaBirimId)) {
            $islemBirimiId = $anaBirimId;
            $girilenMiktar = (string) ($snapshot['ana_miktar'] ?? $kalem->ana_miktar ?? $kalem->miktar);
        } elseif ($snapshotFiyatBirimi === 'adet' || ($snapshotFiyatBirimiId > 0 && $snapshotFiyatBirimiId === $ikincilBirimId)) {
            $islemBirimiId = $ikincilBirimId ?: $anaBirimId;
            $girilenMiktar = (string) ($snapshot['adet_esdegeri'] ?? $kalem->adet_esdegeri ?? $kalem->miktar);
        } else {
            $islemBirimiId = $ikincilBirimId ?: $anaBirimId;
            $girilenMiktar = (string) $kalem->miktar;
        }
        if ($cikis) {
            $bakiyeler = StokOlcuBakiyesi::withoutGlobalScopes()
                ->where('firma_id', $kalem->firma_id)
                ->where('stok_id', $stok->id)
                ->where('stok_olcusu_id', $olcu->id)
                ->when($depoId > 0, fn ($query) => $query->where('depo_id', $depoId))
                ->where('ana_miktar', '>', 0)
                ->get();
            if ($bakiyeler->count() !== 1) {
                return;
            }
            $bakiyeId = (int) $bakiyeler->first()->id;
            $depoId = (int) $bakiyeler->first()->depo_id;
        }

        $this->dagilimlariSakla($kalem, [[
            'stok_olcusu_id' => (int) $olcu->id,
            'stok_olcu_bakiyesi_id' => $bakiyeId,
            'depo_id' => $depoId,
            'islem_birimi_id' => $islemBirimiId,
            'girilen_miktar' => $girilenMiktar,
        ]], $cikis);
    }

    /** Onayda dağılımları tekrar kilitler, bakiyeleri günceller ve hareket snapshot’ı üretir. */
    public function onaydaUygula(FaturaKalemi $kalem, StokHareketi $hareket, bool $giris): void
    {
        $dagilimlar = $kalem->olcuDagilimlari()->orderBy('sira')->lockForUpdate()->get();
        if ($dagilimlar->isEmpty()) throw new InvalidArgumentException('Ölçülü fatura kalemi için kullanıcı ölçü seçimi zorunludur.');
        $this->fiyatSnapshotDogrula($kalem, $dagilimlar);
        $uygulanacak = [];
        foreach ($dagilimlar as $dagilim) {
            $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->findOrFail($kalem->stok_id);
            $olcu = StokOlcusu::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->where('stok_id', $stok->id)->findOrFail($dagilim->stok_olcusu_id);
            $depo = Depo::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->findOrFail($dagilim->depo_id);
            $bakiye = $giris ? $this->bakiyeler->bakiyeBulVeyaOlustur((int) $kalem->firma_id, $stok, $olcu, $depo) : StokOlcuBakiyesi::withoutGlobalScopes()->lockForUpdate()->findOrFail($dagilim->stok_olcu_bakiyesi_id);
            $giris ? $this->bakiyeler->giris($bakiye, anaMiktar: (string) $dagilim->ana_miktar) : $this->bakiyeler->cikis($bakiye, anaMiktar: (string) $dagilim->ana_miktar);
            $uygulanacak[] = ['bakiye' => $bakiye, 'ana_miktar' => (string) $dagilim->ana_miktar, 'adet_esdegeri' => (string) $dagilim->adet_esdegeri, 'islem_birimi_id' => (int) $dagilim->islem_birimi_id, 'girilen_miktar' => (string) $dagilim->girilen_miktar];
        }
        $this->bakiyeler->dagilimlariKaydet($hareket, $uygulanacak);
    }

    private function fiyatSnapshotDogrula(FaturaKalemi $kalem, $dagilimlar): void
    {
        // Bu kurulumdaki fatura kalemi şemasında fiyat birimi/miktarı ayrı
        // kolonlar değildir; miktar dönüşümü olcu_donusum_snapshot içinde
        // tutulur. Eski kayıtları bu alanlar yokmuş gibi doğrulamaya çalışmak
        // ölçülü faturanın onayını hatalı biçimde durdurur.
        if (! array_key_exists('fiyat_birimi_id', $kalem->getAttributes())
            || ! array_key_exists('fiyat_miktari', $kalem->getAttributes())) {
            return;
        }
        if (! $kalem->fiyat_birimi_id && blank($kalem->olcu_donusum_snapshot)) {
            return;
        }
        $stok = StokKarti::withoutGlobalScopes()->where('firma_id', $kalem->firma_id)->findOrFail($kalem->stok_id);
        $snapshot = json_decode((string) $kalem->olcu_donusum_snapshot, true);
        if (! is_array($snapshot)) {
            throw new InvalidArgumentException('Ölçülü kalemin fiyat dönüşüm snapshotı bulunamadı.');
        }
        $ana = '0';
        $adet = '0';
        $faktorler = [];
        foreach ($dagilimlar as $dagilim) {
            $ana = bcadd($ana, (string) $dagilim->ana_miktar, 16);
            $adet = bcadd($adet, (string) $dagilim->adet_esdegeri, 16);
            $faktorler[] = (string) $dagilim->bir_adet_ana_miktar;
        }
        $fiyatBirimi = (int) $kalem->fiyat_birimi_id === (int) $stok->ikincil_birim_id ? 'adet' : 'ana';
        if ($fiyatBirimi === 'adet' && count(array_unique(array_map(fn (string $f): string => bcadd($f, '0', 8), $faktorler))) > 1 && ! (bool) ($snapshot['dogrudan_ortak_adet_fiyati'] ?? false)) {
            throw new InvalidArgumentException('Farklı ölçü katsayılarında otomatik adet fiyatı kullanılamaz.');
        }
        $beklenenMiktar = $fiyatBirimi === 'adet' ? $adet : $ana;
        foreach ([
            [(string) $kalem->fiyat_miktari, $beklenenMiktar, 'Ölçülü fiyat miktarı dağılımlarla uyumsuz.'],
            [(string) ($snapshot['fiyat_miktari'] ?? ''), bcadd($beklenenMiktar, '0', 8), 'Fiyat miktarı snapshotı değiştirilemez.'],
            [(string) ($snapshot['birim_fiyat'] ?? ''), (string) $kalem->birim_fiyat, 'Birim fiyat snapshotı değiştirilemez.'],
        ] as [$gelen, $beklenen, $mesaj]) {
            if ($gelen === '' || bccomp($gelen, $beklenen, 8) !== 0) {
                throw new InvalidArgumentException($mesaj);
            }
        }
    }

    /** Ters hareketlerde belge dağılımını koruyarak aynı ölçü bakiyesine ters uygular. */
    public function tersHareketUygula(StokHareketi $kaynak, StokHareketi $ters, bool $giris = true): void
    {
        $dagilimlar = StokHareketiOlcuDagilimi::query()->where('firma_id', $kaynak->firma_id)->where('stok_hareketi_id', $kaynak->id)->orderBy('id')->lockForUpdate()->get();
        if ($dagilimlar->isEmpty()) return;
        $uygulanacak = [];
        foreach ($dagilimlar as $dagilim) {
            $bakiye = StokOlcuBakiyesi::withoutGlobalScopes()->where('firma_id', $kaynak->firma_id)->whereKey($dagilim->stok_olcu_bakiyesi_id)->lockForUpdate()->firstOrFail();
            $giris ? $this->bakiyeler->giris($bakiye, anaMiktar: (string) $dagilim->ana_miktar) : $this->bakiyeler->cikis($bakiye, anaMiktar: (string) $dagilim->ana_miktar);
            $uygulanacak[] = ['bakiye' => $bakiye, 'ana_miktar' => (string) $dagilim->ana_miktar, 'adet_esdegeri' => (string) $dagilim->adet_esdegeri, 'islem_birimi_id' => (int) $dagilim->islem_birimi_id, 'girilen_miktar' => (string) $dagilim->girilen_miktar];
        }
        $this->bakiyeler->dagilimlariKaydet($ters, $uygulanacak);
    }
}
