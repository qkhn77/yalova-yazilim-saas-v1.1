<?php

namespace App\Services\Restoran;

use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\KasaHareketi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\PosHareketi;
use App\Models\Muhasebe\PosHesabi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Muhasebe\Yardimcilar\FinansAuditBaglami;
use App\Services\TenantContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoranTahsilatServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    public function adisyonTahsilatiOlustur(RestoranAdisyonu $adisyon): FinansHareketi
    {
        return DB::transaction(function () use ($adisyon): FinansHareketi {
            $kilitli = $this->adisyonuKilitle($adisyon);

            if ($kilitli->finans_hareketi_id) {
                return FinansHareketi::query()
                    ->withoutGlobalScope(FirmaIdTenantScope::class)
                    ->findOrFail($kilitli->finans_hareketi_id);
            }

            $this->adisyonTahsilataUygunMu($kilitli);

            $odeme = [
                'odeme_kanali' => $kilitli->odeme_kanali,
                'kasa_hesap_id' => $kilitli->kasa_hesap_id,
                'banka_hesap_id' => $kilitli->banka_hesap_id,
                'pos_hesap_id' => $kilitli->pos_hesap_id,
                'tutar' => $kilitli->genel_toplam,
                'notlar' => null,
            ];

            $finans = $this->finansHareketiOlustur($kilitli, (float) $kilitli->genel_toplam, $kilitli->tahsilat_at ?: now());
            $this->hesapHareketiOlustur($kilitli, $finans, $odeme);
            $this->tahsilatKaydiOlustur($kilitli, $finans, $odeme);
            $this->adisyonuKapat($kilitli, $finans);

            return $finans;
        });
    }

    /**
     * @param  array{odeme_kanali:string,tutar:float|int|string,kasa_hesap_id?:int|null,banka_hesap_id?:int|null,pos_hesap_id?:int|null,notlar?:string|null}  $odeme
     */
    public function parcaliTahsilatOlustur(RestoranAdisyonu $adisyon, array $odeme): RestoranAdisyonTahsilati
    {
        return DB::transaction(function () use ($adisyon, $odeme): RestoranAdisyonTahsilati {
            $kilitli = $this->adisyonuKilitle($adisyon);
            $this->parcaliTahsilataUygunMu($kilitli, $odeme);

            $tutar = round((float) $odeme['tutar'], 2);
            $finans = $this->finansHareketiOlustur($kilitli, $tutar, now());
            $this->hesapHareketiOlustur($kilitli, $finans, $odeme);
            $tahsilat = $this->tahsilatKaydiOlustur($kilitli, $finans, $odeme);

            $toplamTahsilat = $this->adisyonTahsilatToplami((int) $kilitli->firma_id, (int) $kilitli->id);
            if ($toplamTahsilat + 0.0001 >= round((float) $kilitli->genel_toplam, 2)) {
                $this->adisyonuKapat($kilitli, $finans);
            } elseif ($kilitli->durum === RestoranAdisyonu::DURUM_ACIK) {
                $kilitli->forceFill(['durum' => RestoranAdisyonu::DURUM_ODEMEDE])->save();
            }

            return $tahsilat->refresh();
        });
    }

    public function tahsilatIptalEt(RestoranAdisyonTahsilati $tahsilat, ?string $aciklama = null): RestoranAdisyonTahsilati
    {
        return DB::transaction(function () use ($tahsilat, $aciklama): RestoranAdisyonTahsilati {
            $kilitli = RestoranAdisyonTahsilati::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()
                ->findOrFail($tahsilat->getKey());
            $this->aktifFirmaDogrula((int) $kilitli->firma_id);

            if ($kilitli->durum === RestoranAdisyonTahsilati::DURUM_IPTAL) {
                return $kilitli->refresh();
            }

            $finans = FinansHareketi::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->findOrFail($kilitli->finans_hareketi_id);

            $tersFinansId = $kilitli->iptal_finans_hareketi_id;
            if ((string) ($finans->durum->value ?? $finans->durum) === FinansHareketDurumu::Aktif->value) {
                $ters = $this->finansHareketServisi->tersKayitOlustur(
                    $finans,
                    $aciklama ?: 'Restoran tahsilati iptal edildi: #'.$kilitli->id
                );
                $tersFinansId = (int) $ters->getKey();
            }

            $kilitli->forceFill([
                'durum' => RestoranAdisyonTahsilati::DURUM_IPTAL,
                'iptal_finans_hareketi_id' => $tersFinansId,
                'iptal_at' => now(),
                'iptal_notu' => $aciklama,
            ])->save();

            $adisyon = $this->adisyonuKilitle($kilitli->adisyon()->withoutGlobalScope(FirmaIdTenantScope::class)->firstOrFail());
            $this->adisyonTahsilatDurumunuYenile($adisyon, 'Restoran tahsilat iptali stok iadesi');

            return $kilitli->refresh();
        });
    }

    /**
     * Restoran tahsilatını atomik olarak iptal eder ve yeni tahsilat oluşturur.
     * Yeni finans hareketi eski finans hareketine düzeltme ilişkisiyle bağlanır.
     *
     * @param array{odeme_kanali:string,tutar:float|int|string,kasa_hesap_id?:int|null,banka_hesap_id?:int|null,pos_hesap_id?:int|null,notlar?:string|null} $odeme
     */
    public function tahsilatIptalEtVeDuzelt(RestoranAdisyonTahsilati $tahsilat, array $odeme, ?string $aciklama = null): RestoranAdisyonTahsilati
    {
        return DB::transaction(function () use ($tahsilat, $odeme, $aciklama): RestoranAdisyonTahsilati {
            $kilitli = RestoranAdisyonTahsilati::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->lockForUpdate()
                ->findOrFail($tahsilat->getKey());
            $this->aktifFirmaDogrula((int) $kilitli->firma_id);

            if ($kilitli->durum === RestoranAdisyonTahsilati::DURUM_IPTAL) {
                throw ValidationException::withMessages(['tahsilat' => ['Tahsilat zaten iptal edilmiş.']]);
            }

            $eskiFinansId = (int) $kilitli->finans_hareketi_id;
            $this->tahsilatIptalEt($kilitli, $aciklama ?: 'Restoran tahsilatı düzeltmesi');
            $yeni = $this->parcaliTahsilatOlustur($kilitli->adisyon()->withoutGlobalScope(FirmaIdTenantScope::class)->firstOrFail(), $odeme);
            $yeni->finansHareketi?->update(['duzeltme_kaynagi_id' => $eskiFinansId]);

            return $yeni->refresh();
        });
    }

    private function adisyonuKilitle(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        $kilitli = RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->lockForUpdate()
            ->findOrFail($adisyon->getKey());
        $this->aktifFirmaDogrula((int) $kilitli->firma_id);

        return $kilitli;
    }

    private function aktifFirmaDogrula(int $firmaId): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();
        if ($aktifFirmaId !== null && $firmaId !== $aktifFirmaId) {
            throw ValidationException::withMessages([
                'firma_id' => ['Restoran tahsilat islemi sadece aktif firma icin yapilabilir.'],
            ]);
        }
    }

    private function adisyonTahsilataUygunMu(RestoranAdisyonu $adisyon): void
    {
        if ((float) $adisyon->genel_toplam <= 0) {
            throw ValidationException::withMessages([
                'genel_toplam' => ['Tahsilat icin adisyon genel toplami sifirdan buyuk olmalidir.'],
            ]);
        }

        if ($adisyon->durum === RestoranAdisyonu::DURUM_IPTAL) {
            throw ValidationException::withMessages([
                'durum' => ['Iptal adisyon tahsil edilemez.'],
            ]);
        }

        if (! in_array((string) $adisyon->odeme_kanali, ['kasa', 'banka', 'pos'], true)) {
            throw ValidationException::withMessages([
                'odeme_kanali' => ['Restoran tahsilati icin kasa, banka veya POS kanali secilmelidir.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $odeme
     */
    private function parcaliTahsilataUygunMu(RestoranAdisyonu $adisyon, array $odeme): void
    {
        if ((float) $adisyon->genel_toplam <= 0) {
            throw ValidationException::withMessages([
                'genel_toplam' => ['Tahsilat icin adisyon genel toplami sifirdan buyuk olmalidir.'],
            ]);
        }

        if (in_array((string) $adisyon->durum, [RestoranAdisyonu::DURUM_IPTAL, RestoranAdisyonu::DURUM_KAPANDI], true)) {
            throw ValidationException::withMessages([
                'durum' => ['Kapali veya iptal adisyona parcali tahsilat eklenemez.'],
            ]);
        }

        if (! in_array((string) ($odeme['odeme_kanali'] ?? ''), ['kasa', 'banka', 'pos'], true)) {
            throw ValidationException::withMessages([
                'odeme_kanali' => ['Tahsilat icin kasa, banka veya POS kanali secilmelidir.'],
            ]);
        }

        $tutar = round((float) ($odeme['tutar'] ?? 0), 2);
        if ($tutar <= 0) {
            throw ValidationException::withMessages([
                'tutar' => ['Tahsilat tutari sifirdan buyuk olmalidir.'],
            ]);
        }

        $kalan = round((float) $adisyon->genel_toplam - $this->adisyonTahsilatToplami((int) $adisyon->firma_id, (int) $adisyon->id), 2);
        if ($tutar > $kalan + 0.0001) {
            throw ValidationException::withMessages([
                'tutar' => ['Tahsilat tutari kalan adisyon tutarini asamaz.'],
            ]);
        }
    }

    private function finansHareketiOlustur(RestoranAdisyonu $adisyon, float $tutar, mixed $tarih): FinansHareketi
    {
        $paraBirimi = strtoupper((string) ($adisyon->para_birimi ?: 'TRY'));

        return FinansHareketi::query()->create(array_merge(
            FinansAuditBaglami::otomatikFinansAlanlari(),
            [
                'firma_id' => $adisyon->firma_id,
                'tur' => FinansHareketTuru::Tahsilat,
                'tarih' => $tarih,
                'vade_tarihi' => null,
                'tutar' => $tutar,
                'baz_tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'baz_para_birimi' => $paraBirimi,
                'kur' => 1,
                'cari_id' => $adisyon->cari_id,
                'aciklama' => 'Restoran adisyon tahsilati: '.$adisyon->adisyon_no,
                'referans_turu' => 'restoran_adisyon',
                'referans_id' => $adisyon->id,
                'durum' => FinansHareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]
        ));
    }

    /**
     * @param  array<string, mixed>  $odeme
     */
    private function hesapHareketiOlustur(RestoranAdisyonu $adisyon, FinansHareketi $finans, array $odeme): Model
    {
        $kanal = (string) $odeme['odeme_kanali'];

        if ($kanal === 'kasa') {
            $kasa = $this->hesapDogrula(KasaHesabi::class, (int) $adisyon->firma_id, $odeme['kasa_hesap_id'] ?? null, (string) $adisyon->para_birimi, 'kasa_hesap_id');

            return KasaHareketi::query()->create([
                'firma_id' => $adisyon->firma_id,
                'finans_hareket_id' => $finans->id,
                'kasa_hesap_id' => $kasa->id,
                'tutar' => $finans->tutar,
                'para_birimi' => $finans->para_birimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);
        }

        if ($kanal === 'banka') {
            $banka = $this->hesapDogrula(BankaHesabi::class, (int) $adisyon->firma_id, $odeme['banka_hesap_id'] ?? null, (string) $adisyon->para_birimi, 'banka_hesap_id');

            return BankaHareketi::query()->create([
                'firma_id' => $adisyon->firma_id,
                'finans_hareket_id' => $finans->id,
                'banka_hesap_id' => $banka->id,
                'tutar' => $finans->tutar,
                'para_birimi' => $finans->para_birimi,
                'durum' => HareketDurumu::Aktif,
                'iptal_edilen_hareket_id' => null,
            ]);
        }

        $pos = $this->hesapDogrula(PosHesabi::class, (int) $adisyon->firma_id, $odeme['pos_hesap_id'] ?? null, (string) $adisyon->para_birimi, 'pos_hesap_id');

        return PosHareketi::query()->create([
            'firma_id' => $adisyon->firma_id,
            'finans_hareket_id' => $finans->id,
            'pos_hesap_id' => $pos->id,
            'tutar' => $finans->tutar,
            'brut_tutar' => $finans->tutar,
            'komisyon_tutari' => 0,
            'para_birimi' => $finans->para_birimi,
            'durum' => HareketDurumu::Aktif,
            'iptal_edilen_hareket_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $odeme
     */
    private function tahsilatKaydiOlustur(RestoranAdisyonu $adisyon, FinansHareketi $finans, array $odeme): RestoranAdisyonTahsilati
    {
        return RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->create([
                'firma_id' => $adisyon->firma_id,
                'adisyon_id' => $adisyon->id,
                'finans_hareketi_id' => $finans->id,
                'kasa_hesap_id' => $odeme['kasa_hesap_id'] ?? null,
                'banka_hesap_id' => $odeme['banka_hesap_id'] ?? null,
                'pos_hesap_id' => $odeme['pos_hesap_id'] ?? null,
                'odeme_kanali' => (string) $odeme['odeme_kanali'],
                'tutar' => $finans->tutar,
                'para_birimi' => $finans->para_birimi,
                'tahsilat_at' => $finans->tarih,
                'durum' => RestoranAdisyonTahsilati::DURUM_AKTIF,
                'notlar' => $odeme['notlar'] ?? null,
            ]);
    }

    private function adisyonuKapat(RestoranAdisyonu $adisyon, FinansHareketi $finans): void
    {
        $stokServisi = app(RestoranStokServisi::class);
        $stokServisi->stokYeterliliginiDogrula($adisyon);
        $stokServisi->adisyonStokHareketleriniOlustur($adisyon);

        $adisyon->forceFill([
            'finans_hareketi_id' => $adisyon->finans_hareketi_id ?: $finans->id,
            'durum' => RestoranAdisyonu::DURUM_KAPANDI,
            'kapanis_at' => $adisyon->kapanis_at ?: now(),
            'tahsilat_at' => $adisyon->tahsilat_at ?: now(),
        ])->save();
    }

    private function adisyonTahsilatDurumunuYenile(RestoranAdisyonu $adisyon, ?string $stokIadeAciklamasi = null): void
    {
        $toplamTahsilat = $this->adisyonTahsilatToplami((int) $adisyon->firma_id, (int) $adisyon->id);
        $genelToplam = round((float) $adisyon->genel_toplam, 2);

        if ($toplamTahsilat + 0.0001 >= $genelToplam && $genelToplam > 0) {
            $sonAktifTahsilat = RestoranAdisyonTahsilati::query()
                ->withoutGlobalScope(FirmaIdTenantScope::class)
                ->where('firma_id', $adisyon->firma_id)
                ->where('adisyon_id', $adisyon->id)
                ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
                ->orderByDesc('id')
                ->first();

            $adisyon->forceFill([
                'durum' => RestoranAdisyonu::DURUM_KAPANDI,
                'finans_hareketi_id' => $sonAktifTahsilat?->finans_hareketi_id,
                'kapanis_at' => $adisyon->kapanis_at ?: now(),
                'tahsilat_at' => $adisyon->tahsilat_at ?: now(),
            ])->save();

            return;
        }

        if ($adisyon->durum === RestoranAdisyonu::DURUM_KAPANDI) {
            app(RestoranStokServisi::class)->adisyonStokHareketleriniTersle($adisyon, $stokIadeAciklamasi);
            app(RestoranFaturaServisi::class)->bekleyenFaturayiIptalEt($adisyon, 'Restoran adisyon tahsilati iptal edildi.');
        }

        $adisyon->forceFill([
            'durum' => $toplamTahsilat > 0 ? RestoranAdisyonu::DURUM_ODEMEDE : RestoranAdisyonu::DURUM_ACIK,
            'finans_hareketi_id' => null,
            'kapanis_at' => null,
            'tahsilat_at' => null,
        ])->save();
    }

    private function adisyonTahsilatToplami(int $firmaId, int $adisyonId): float
    {
        return round((float) RestoranAdisyonTahsilati::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $firmaId)
            ->where('adisyon_id', $adisyonId)
            ->where('durum', RestoranAdisyonTahsilati::DURUM_AKTIF)
            ->sum('tutar'), 2);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function hesapDogrula(string $model, int $firmaId, mixed $id, string $paraBirimi, string $alan): Model
    {
        if (! $id) {
            throw ValidationException::withMessages([$alan => ['Tahsilat hesabi secilmelidir.']]);
        }

        $hesap = $model::query()
            ->withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereKey($id)
            ->first();

        if (! $hesap) {
            throw ValidationException::withMessages([$alan => ['Secilen tahsilat hesabi bu firmaya ait degil.']]);
        }

        if (strtoupper((string) $hesap->getAttribute('para_birimi')) !== strtoupper($paraBirimi)) {
            throw ValidationException::withMessages([$alan => ['Tahsilat hesabinin para birimi adisyonla uyumlu degil.']]);
        }

        return $hesap;
    }
}
