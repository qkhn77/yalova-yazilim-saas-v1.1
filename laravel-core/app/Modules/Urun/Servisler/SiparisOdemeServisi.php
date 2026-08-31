<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisGecmisi;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Models\Muhasebe\StokSeriNo;
use App\Muhasebe\Enumlar\StokBelgeTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Servisler\StokHareketServisi;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Services\EcommerceBildirimServisi;
use App\Services\EcommerceMuhasebeEntegrasyonServisi;
use App\Services\SistemOlayServisi;
use App\Support\DenetimYardimcisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiparisOdemeServisi
{
    public function __construct(
        private readonly FinansHareketServisi $finansHareketServisi,
        private readonly SiparisGecmisServisi $gecmisServisi,
        private readonly EcommerceBildirimServisi $bildirimServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
        private readonly EcommerceMuhasebeEntegrasyonServisi $ecommerceMuhasebeEntegrasyonServisi,
    ) {}

    public function bekleyenOdemeOlustur(Siparis $siparis, string $provider = Odeme::PROVIDER_MOCK): Odeme
    {
        $siparis->loadMissing('kalemler');

        Odeme::query()
            ->where('siparis_id', $siparis->id)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->update(['durum' => Odeme::DURUM_IPTAL]);

        return Odeme::query()->create([
            'siparis_id' => $siparis->id,
            'odeme_no' => $this->odemeNoUret(),
            'tutar' => $siparis->genel_toplam,
            'para_birimi' => $siparis->para_birimi ?? 'TRY',
            'durum' => Odeme::DURUM_BEKLEMEDE,
            'provider' => $provider,
            'provider_ref' => $provider.'_'.Str::uuid()->toString(),
        ]);
    }

    /**
     * Provider'a özel bekleyen ödeme kaydı oluşturur.
     * Bu kayıt, provider callback'inde idempotency için provider_ref eşleşmesinde kullanılır.
     */
    public function providerBekleyenOdemeOlustur(Siparis $siparis, string $provider): Odeme
    {
        $siparis->loadMissing('kalemler');

        $providerRef = $siparis->id.'-'.Str::random(24);

        Odeme::query()
            ->where('siparis_id', $siparis->id)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->update(['durum' => Odeme::DURUM_IPTAL]);

        return Odeme::query()->create([
            'siparis_id' => $siparis->id,
            'odeme_no' => $this->odemeNoUret(),
            'tutar' => $siparis->genel_toplam,
            'para_birimi' => $siparis->para_birimi ?? 'TRY',
            'durum' => Odeme::DURUM_BEKLEMEDE,
            'provider' => $provider,
            'provider_ref' => $providerRef,
        ]);
    }

    /**
     * Provider bazlı yeni ödeme denemesi (retry) başlatır.
     * Başarısız callback'ten sonra operatörün "Yeni deneme" butonuna bastığı senaryo için.
     */
    public function providerYeniOdemeDenemesiBaslat(Siparis $siparis, string $provider): Odeme
    {
        return DB::transaction(function () use ($siparis, $provider): Odeme {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();

            if (! Siparis::odemeAkisindaDurumMu($kilitli->durum)) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş için yeni ödeme denemesi başlatılamaz.',
                ]);
            }

            $this->odemeSuresiKontrol($kilitli, zamanAsiminiYoksay: false);

            if (Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('durum', Odeme::DURUM_BASARILI)
                ->exists()) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş zaten ödenmiş.',
                ]);
            }

            $odeme = $this->providerBekleyenOdemeOlustur($kilitli, $provider);
            if ($kilitli->durum !== Siparis::DURUM_ONAY_BEKLIYOR) {
                $kilitli->update(['durum' => Siparis::DURUM_ONAY_BEKLIYOR]);
            }

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_TEKRAR_DENENDI,
                'Ödeme tekrar denendi (provider).',
                [
                    'odeme_id' => $odeme->id,
                    'odeme_no' => $odeme->odeme_no,
                    'provider' => $provider,
                ],
            );

            return $odeme;
        });
    }

    /**
     * Başarısız denemeden sonra yeni bekleyen ödeme satırı (sipariş ödeme bekliyor kalır).
     */
    public function yeniOdemeDenemesiBaslat(Siparis $siparis): Odeme
    {
        return DB::transaction(function () use ($siparis): Odeme {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();

            if (! Siparis::odemeAkisindaDurumMu($kilitli->durum)) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş için yeni ödeme denemesi başlatılamaz.',
                ]);
            }

            $this->odemeSuresiKontrol($kilitli, zamanAsiminiYoksay: false);

            if (Odeme::query()->where('siparis_id', $kilitli->id)->where('durum', Odeme::DURUM_BASARILI)->exists()) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş zaten ödenmiş.',
                ]);
            }

            $odeme = $this->bekleyenOdemeOlustur($kilitli);
            if ($kilitli->durum !== Siparis::DURUM_ONAY_BEKLIYOR) {
                $kilitli->update(['durum' => Siparis::DURUM_ONAY_BEKLIYOR]);
            }

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_TEKRAR_DENENDI,
                'Ödeme tekrar denendi',
                [
                    'odeme_id' => $odeme->id,
                    'odeme_no' => $odeme->odeme_no,
                ],
            );

            return $odeme;
        });
    }

    /**
     * Ödeme başarılı: rezerv + stok düş, sipariş ödendi, finans (yapılandırılmışsa), ödeme başarılı (idempotent).
     */
    public function mockOdemeBasarili(Siparis $siparis, bool $zamanAsiminiYoksay = false): void
    {
        $eskiDurum = (string) $siparis->durum;

        DB::transaction(function () use ($siparis, $zamanAsiminiYoksay): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();

            if (Siparis::iptalEdildiDurumMu($kilitli->durum)) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş iptal edilmiş.',
                ]);
            }

            $this->odemeSuresiKontrol($kilitli, $zamanAsiminiYoksay);

            $basariliOdemeVar = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('durum', Odeme::DURUM_BASARILI)
                ->exists();

            if ($basariliOdemeVar) {
                if (Siparis::odemeAlindiDurumMu($kilitli->durum) && $kilitli->stok_dusuldu_mi) {
                    $this->sonBekleyenOdemeyiBasariliIsaretle($kilitli->id);
                    $this->eticaretTahsilatiOlustur($kilitli);

                    return;
                }

                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş için başarılı ödeme zaten kayıtlı (çift ödeme engellendi).',
                ]);
            }

            if ($kilitli->stok_dusuldu_mi) {
                if (! Siparis::odemeAlindiDurumMu($kilitli->durum)) {
                    $kilitli->update(['durum' => Siparis::DURUM_ONAYLANDI_YENI]);
                }
                $this->sonBekleyenOdemeyiBasariliIsaretle($kilitli->id);
                $this->eticaretTahsilatiOlustur($kilitli);
                $this->gecmisServisi->kaydet(
                    $kilitli->fresh(),
                    SiparisGecmisi::OLAY_ODEME_BASARILI,
                    'Ödeme başarılı',
                    ['yol' => 'stok_zaten_dusmus'],
                );

                return;
            }

            $this->stokDus($kilitli);

            $kilitli->update([
                'durum' => Siparis::DURUM_ONAYLANDI_YENI,
                'stok_dusuldu_mi' => true,
            ]);

            $this->sonBekleyenOdemeyiBasariliIsaretle($kilitli->id);
            $this->eticaretTahsilatiOlustur($kilitli->fresh());
            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_BASARILI,
                'Ödeme başarılı',
                ['yol' => 'ilk_stok_dusumu'],
            );
        });

        $son = $siparis->fresh();
        if ($son instanceof Siparis && Siparis::odemeAlindiDurumMu($son->durum) && ! Siparis::odemeAlindiDurumMu($eskiDurum)) {
            $this->siparisOnaylandiBildir($son);
        }
    }

    public function adminManuelOdemeOnayla(Siparis $siparis): void
    {
        $this->mockOdemeBasarili($siparis, zamanAsiminiYoksay: true);
        DenetimYardimcisi::kaydet(
            olay: 'siparis.manuel_odeme_onayi',
            konuTipi: Siparis::class,
            konuId: (int) $siparis->id,
            firmaId: (int) $siparis->firma_id,
            eskiVeri: null,
            yeniVeri: ['durum' => Siparis::DURUM_ONAYLANDI_YENI]
        );
        $this->gecmisServisi->kaydet(
            $siparis->fresh(),
            SiparisGecmisi::OLAY_MANUEL_ODEME,
            'Yönetici manuel ödeme onayı',
        );
    }

    /**
     * Ödeme başarısız: sipariş iptal EDİLMEZ; tekrar denenebilir. Rezerv korunur.
     */
    public function mockOdemeBasarisiz(Siparis $siparis): void
    {
        $eskiDurum = (string) $siparis->durum;

        DB::transaction(function () use ($siparis): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();

            if (Siparis::iptalEdildiDurumMu($kilitli->durum)) {
                return;
            }

            if ($kilitli->stok_dusuldu_mi) {
                throw ValidationException::withMessages([
                    'odeme' => 'Ödeme alınmış sipariş için bu işlem uygun değil.',
                ]);
            }

            if (! Siparis::odemeAkisindaDurumMu($kilitli->durum)) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bu sipariş ödeme bekliyor durumunda değil.',
                ]);
            }

            $this->odemeSuresiKontrol($kilitli, zamanAsiminiYoksay: false);

            $guncellenen = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('durum', Odeme::DURUM_BEKLEMEDE)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['durum' => Odeme::DURUM_BASARISIZ]);

            if ($guncellenen === 0) {
                throw ValidationException::withMessages([
                    'odeme' => 'Bekleyen ödeme denemesi bulunamadı.',
                ]);
            }

            $kilitli->increment('odeme_deneme_sayisi');
            $kilitli->update(['durum' => Siparis::DURUM_BASARISIZ_ODEME]);

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_BASARISIZ,
                'Ödeme başarısız',
                [
                    'deneme_sayisi' => (int) $kilitli->odeme_deneme_sayisi,
                ],
            );
        });

        $son = $siparis->fresh();
        if ($son instanceof Siparis && $son->durum === Siparis::DURUM_BASARISIZ_ODEME && $eskiDurum !== Siparis::DURUM_BASARISIZ_ODEME) {
            $this->odemeBasarisizBildir($son);
        }
    }

    /**
     * Provider callback: başarılı ödeme sonucu (idempotent).
     */
    public function providerOdemeCallbackBasarili(string $provider, string $providerRef, int $siparisId, array $meta = []): void
    {
        $eskiDurum = (string) Siparis::query()->whereKey($siparisId)->value('durum');

        DB::transaction(function () use ($provider, $providerRef, $siparisId, $meta): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparisId)->lockForUpdate()->firstOrFail();

            if (Siparis::iptalEdildiDurumMu($kilitli->durum)) {
                return;
            }

            $basariliOdemeVardi = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('durum', Odeme::DURUM_BASARILI)
                ->exists();

            $stokOnceDusulmustu = (bool) $kilitli->stok_dusuldu_mi;

            $odeme = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('provider', $provider)
                ->where('provider_ref', $providerRef)
                ->orderByDesc('id')
                ->first();

            if ($odeme && $odeme->durum === Odeme::DURUM_BASARILI && Siparis::odemeAlindiDurumMu($kilitli->durum) && $kilitli->stok_dusuldu_mi) {
                $this->olay('odeme.callback.duplicate', 'info', 'Ayni provider callback tekrar geldi, islem atlandi.', [
                    'firma_id' => (int) $kilitli->firma_id,
                    'siparis_id' => (int) $kilitli->id,
                ]);

                return; // tamamen idempotent
            }

            // Provider callback başarısında siparis/stok zaten güncellenmiş olabilir.
            if (! $odeme) {
                $odeme = Odeme::query()->create([
                    'siparis_id' => $kilitli->id,
                    'odeme_no' => $this->odemeNoUret(),
                    'tutar' => $kilitli->genel_toplam,
                    'para_birimi' => $kilitli->para_birimi ?? 'TRY',
                    'durum' => Odeme::DURUM_BASARILI,
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                ]);
            } else {
                $odeme->update(['durum' => Odeme::DURUM_BASARILI]);
            }

            if ($stokOnceDusulmustu) {
                if (! Siparis::odemeAlindiDurumMu($kilitli->durum)) {
                    $kilitli->update(['durum' => Siparis::DURUM_ONAYLANDI_YENI]);
                }

                $this->eticaretTahsilatiOlustur($kilitli->fresh());

                // Daha önce stok düşmüş ve başarılı ödeme kaydı varsa log çoğaltmayalım.
                if (! $basariliOdemeVardi) {
                    $this->gecmisServisi->kaydet(
                        $kilitli->fresh(),
                        SiparisGecmisi::OLAY_ODEME_BASARILI,
                        'Ödeme başarılı',
                        ['yol' => 'stok_zaten_dusmus'],
                    );
                }

                return;
            }

            // İlk kez stok düşülüyor (çekirdek akış).
            $this->stokDus($kilitli);
            $kilitli->update([
                'durum' => Siparis::DURUM_ONAYLANDI_YENI,
                'stok_dusuldu_mi' => true,
            ]);

            $this->eticaretTahsilatiOlustur($kilitli->fresh());

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_BASARILI,
                'Ödeme başarılı',
                [
                    'yol' => 'ilk_stok_dusumu',
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'meta' => $meta,
                ],
            );
            $this->olay('odeme.basarili', 'info', 'Odeme basarili olarak islendi.', [
                'firma_id' => (int) $kilitli->firma_id,
                'siparis_id' => (int) $kilitli->id,
            ]);
        });

        $son = Siparis::query()->whereKey($siparisId)->first();
        if ($son instanceof Siparis && Siparis::odemeAlindiDurumMu($son->durum) && ! Siparis::odemeAlindiDurumMu($eskiDurum)) {
            $this->siparisOnaylandiBildir($son);
        }
    }

    /**
     * Provider callback: başarısız ödeme sonucu (idempotent).
     */
    public function providerOdemeCallbackBasarisiz(string $provider, string $providerRef, int $siparisId, array $meta = []): void
    {
        $eskiDurum = (string) Siparis::query()->whereKey($siparisId)->value('durum');

        DB::transaction(function () use ($provider, $providerRef, $siparisId, $meta): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparisId)->lockForUpdate()->firstOrFail();

            if (Siparis::iptalEdildiDurumMu($kilitli->durum)) {
                return;
            }

            if ((bool) $kilitli->stok_dusuldu_mi || Siparis::odemeAlindiDurumMu($kilitli->durum)) {
                // Sipariş zaten başarıya dönmüşse başarısız callback'i yoksay.
                return;
            }

            if (! Siparis::odemeAkisindaDurumMu($kilitli->durum)) {
                return;
            }

            $odeme = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('provider', $provider)
                ->where('provider_ref', $providerRef)
                ->orderByDesc('id')
                ->first();

            if ($odeme && $odeme->durum === Odeme::DURUM_BASARISIZ) {
                $this->olay('odeme.callback.duplicate', 'info', 'Ayni basarisiz callback tekrar geldi, islem atlandi.', [
                    'firma_id' => (int) $kilitli->firma_id,
                    'siparis_id' => (int) $kilitli->id,
                ]);

                return; // aynı providerRef için tekrar callback idempotent
            }

            if (! $odeme) {
                $odeme = Odeme::query()->create([
                    'siparis_id' => $kilitli->id,
                    'odeme_no' => $this->odemeNoUret(),
                    'tutar' => $kilitli->genel_toplam,
                    'para_birimi' => $kilitli->para_birimi ?? 'TRY',
                    'durum' => Odeme::DURUM_BASARISIZ,
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                ]);
            } else {
                $odeme->update(['durum' => Odeme::DURUM_BASARISIZ]);
            }

            $kilitli->increment('odeme_deneme_sayisi');
            $kilitli->update(['durum' => Siparis::DURUM_BASARISIZ_ODEME]);

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_BASARISIZ,
                'Ödeme başarısız',
                [
                    'deneme_sayisi' => (int) $kilitli->odeme_deneme_sayisi,
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'meta' => $meta,
                ],
            );
            $this->olay('odeme.basarisiz', 'warning', 'Odeme basarisiz callback olarak islendi.', [
                'firma_id' => (int) $kilitli->firma_id,
                'siparis_id' => (int) $kilitli->id,
            ]);
        });

        $son = Siparis::query()->whereKey($siparisId)->first();
        if ($son instanceof Siparis && $son->durum === Siparis::DURUM_BASARISIZ_ODEME && $eskiDurum !== Siparis::DURUM_BASARISIZ_ODEME) {
            $this->odemeBasarisizBildir($son);
        }
    }

    public function siparisIptalEt(Siparis $siparis, ?string $iptalNedeni = null): void
    {
        DB::transaction(function () use ($siparis, $iptalNedeni): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();
            $this->siparisIptalKilidiAltinda($kilitli, $iptalNedeni);
        });
    }

    /**
     * Zaman aşımı: ödeme başarıya dönmemiş siparişler "Başarısız Ödeme" yapılır ve rezerv çözülür.
     */
    public function siparisZamanAsimindaIptal(Siparis $siparis): void
    {
        $eskiDurum = (string) $siparis->durum;

        DB::transaction(function () use ($siparis): void {
            /** @var Siparis $kilitli */
            $kilitli = Siparis::query()->whereKey($siparis->id)->lockForUpdate()->firstOrFail();

            if (! Siparis::odemeAkisindaDurumMu($kilitli->durum)) {
                return;
            }

            if ($kilitli->odeme_suresi_bitis_at === null || now()->lte($kilitli->odeme_suresi_bitis_at)) {
                return;
            }

            $this->olay('siparis.odeme_zaman_asimi', 'warning', 'Siparis odeme suresi doldu, basarisiz odeme sureci tetiklendi.', [
                'firma_id' => (int) $kilitli->firma_id,
                'siparis_id' => (int) $kilitli->id,
            ]);

            if ($kilitli->stok_dusuldu_mi || Siparis::odemeAlindiDurumMu($kilitli->durum)) {
                return;
            }

            $guncellenen = Odeme::query()
                ->where('siparis_id', $kilitli->id)
                ->where('durum', Odeme::DURUM_BEKLEMEDE)
                ->update(['durum' => Odeme::DURUM_BASARISIZ]);

            if ($guncellenen > 0) {
                $kilitli->increment('odeme_deneme_sayisi');
            }

            $this->rezervKaldir($kilitli);
            $kilitli->update([
                'durum' => Siparis::DURUM_BASARISIZ_ODEME,
                'stok_dusuldu_mi' => false,
            ]);

            $this->gecmisServisi->kaydet(
                $kilitli->fresh(),
                SiparisGecmisi::OLAY_ODEME_BASARISIZ,
                'Ödeme süresi doldu / ödeme tamamlanamadı',
                [
                    'neden' => 'zaman_asimi',
                    'deneme_sayisi' => (int) $kilitli->odeme_deneme_sayisi,
                ],
            );
        });

        $son = $siparis->fresh();
        if ($son instanceof Siparis && $son->durum === Siparis::DURUM_BASARISIZ_ODEME && $eskiDurum !== Siparis::DURUM_BASARISIZ_ODEME) {
            $this->odemeBasarisizBildir($son);
        }
    }

    private function siparisIptalKilidiAltinda(Siparis $kilitli, ?string $iptalNedeni = null): void
    {
        if (Siparis::iptalEdildiDurumMu($kilitli->durum)) {
            return;
        }

        if (Siparis::teslimEdildiDurumMu($kilitli->durum)) {
            throw ValidationException::withMessages([
                'siparis' => 'Tamamlanmış sipariş için iptal bu servis üzerinden yapılamaz; iade / muhasebe akışı ayrı tasarlanmalıdır.',
            ]);
        }

        $eskiDurum = (string) $kilitli->durum;
        $stokOnceDusulmustu = (bool) $kilitli->stok_dusuldu_mi;
        $odemeBasariliKaydiVardi = Odeme::query()
            ->where('siparis_id', $kilitli->id)
            ->where('durum', Odeme::DURUM_BASARILI)
            ->exists();

        $finansIadeOtomatik = (bool) config('ecommerce.finans_iade_otomatik', true);
        $finansIadeOlustu = false;
        $finansTersKayitId = null;

        Odeme::query()
            ->where('siparis_id', $kilitli->id)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->update(['durum' => Odeme::DURUM_IPTAL]);

        if ($kilitli->stok_dusuldu_mi) {
            $this->stokIade($kilitli);
        } else {
            $this->rezervKaldir($kilitli);
        }

        // Finans iade: önce stok/reserve çözülür, sonra muhasebe ters kaydı üretilir.
        if ($finansIadeOtomatik && $odemeBasariliKaydiVardi) {
            $firmaId = (int) ($kilitli->firma_id ?? 0);
            $siparisId = (int) $kilitli->id;

            // Duplicate ters kaydı engellemek için daha önce oluşturulmuş Mahsup kaydı varsa tekrar üretme.
            $mevcutTers = FinansHareketi::query()->withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
                ->where('referans_id', $siparisId)
                ->where('tur', FinansHareketTuru::Mahsup->value)
                ->where('durum', FinansHareketDurumu::Aktif)
                ->orderByDesc('id')
                ->first();

            if ($mevcutTers) {
                $finansIadeOlustu = true;
                $finansTersKayitId = (int) $mevcutTers->id;

                $this->gecmisServisi->kaydet(
                    $kilitli->fresh(),
                    SiparisGecmisi::OLAY_FINANS_IADE_OLUSTURULDU,
                    'Finans iade ters kaydı zaten vardı (duplicate engellendi).',
                    [
                        'finans_ters_kayit_id' => $finansTersKayitId,
                    ],
                );
            } else {
                $finans = FinansHareketi::query()->withoutGlobalScopes()
                    ->where('firma_id', $firmaId)
                    ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
                    ->where('referans_id', $siparisId)
                    ->where('tur', FinansHareketTuru::Tahsilat->value)
                    ->where('durum', FinansHareketDurumu::Aktif)
                    ->orderByDesc('id')
                    ->first();

                if ($finans) {
                    $tersAciklama = 'E-ticaret sipariş iade/ters tahsilat: '.$kilitli->siparis_no;
                    $ters = $this->finansHareketServisi->tersKayitOlusturEcommerce(
                        $finans,
                        $iptalNedeni ? ('İptal: '.$iptalNedeni.' · '.$tersAciklama) : $tersAciklama
                    );

                    // Ters kaydı sipariş referansına bağlayalım (raporlama için).
                    $ters->update([
                        'referans_turu' => Siparis::REFERANS_TURU_FINANS,
                        'referans_id' => (int) $kilitli->id,
                    ]);

                    $finansIadeOlustu = true;
                    $finansTersKayitId = (int) $ters->id;

                    $this->gecmisServisi->kaydet(
                        $kilitli->fresh(),
                        SiparisGecmisi::OLAY_FINANS_IADE_OLUSTURULDU,
                        'Finans iade için ters kaydı oluşturuldu.',
                        [
                            'finans_ters_kayit_id' => $finansTersKayitId,
                        ],
                    );
                } else {
                    // Başarılı ödeme var ama aktif tahsilat finans kaydı bulunamadı.
                    // İptali bloklamadan kontrollü log bırakıyoruz (legacy ayar / geçiş senaryosu).
                    $this->gecmisServisi->kaydet(
                        $kilitli->fresh(),
                        SiparisGecmisi::OLAY_FINANS_IADE_BASARISIZ,
                        'Finans iade ters kaydı oluşturulamadı: aktif tahsilat finans kaydı bulunamadı.',
                        [
                            'firma_id' => $firmaId,
                            'siparis_id' => $siparisId,
                        ],
                    );
                }
            }
        }

        $guncelle = [
            'durum' => $this->iptalDurumuBelirle((string) $kilitli->durum),
            'stok_dusuldu_mi' => false,
        ];
        if ($iptalNedeni !== null && $iptalNedeni !== '') {
            $guncelle['iptal_nedeni'] = $iptalNedeni;
        }
        $kilitli->update($guncelle);

        $this->gecmisServisi->kaydet(
            $kilitli->fresh(),
            SiparisGecmisi::OLAY_IPTAL,
            $iptalNedeni,
            [
                'stok_once_dusulmustu' => $stokOnceDusulmustu,
                'odeme_basarili_kaydi_vardi' => $odemeBasariliKaydiVardi,
                'finans_iade_otomatik' => $finansIadeOtomatik,
                'finans_iade_olustu' => $finansIadeOlustu,
                'finans_ters_kayit_id' => $finansTersKayitId,
            ],
        );
        $this->olay('siparis.iptal', 'warning', 'Siparis iptal edildi.', [
            'firma_id' => (int) $kilitli->firma_id,
            'siparis_id' => (int) $kilitli->id,
            'finans_hareket_id' => $finansTersKayitId,
        ]);

        $yeniDurum = (string) ($guncelle['durum'] ?? '');
        if ($yeniDurum !== '' && $yeniDurum !== $eskiDurum) {
            $this->siparisIptalBildir($kilitli->fresh(), $eskiDurum, $yeniDurum);
        }
    }

    private function odemeSuresiKontrol(Siparis $siparis, bool $zamanAsiminiYoksay): void
    {
        if ($zamanAsiminiYoksay) {
            return;
        }

        if ($siparis->odeme_suresi_bitis_at !== null && now()->gt($siparis->odeme_suresi_bitis_at)) {
            throw ValidationException::withMessages([
                'odeme' => 'Ödeme süresi doldu. Lütfen yeni sipariş oluşturun veya yönetici onayı isteyin.',
            ]);
        }
    }

    private function iptalDurumuBelirle(string $mevcutDurum): string
    {
        if (in_array($mevcutDurum, [
            Siparis::DURUM_DETAY_BEKLEYEN,
            Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_IPTAL_TALEBI,
            Siparis::DURUM_IADE_TALEBI,
            Siparis::DURUM_BASARISIZ_ODEME,
        ], true)) {
            return Siparis::DURUM_IPTAL_EDILDI;
        }

        // Geriye uyumluluk: legacy akışlar hâlâ "iptal" bekliyor.
        return Siparis::DURUM_IPTAL;
    }

    private function sonBekleyenOdemeyiBasariliIsaretle(int $siparisId): void
    {
        Odeme::query()
            ->where('siparis_id', $siparisId)
            ->where('durum', Odeme::DURUM_BEKLEMEDE)
            ->orderByDesc('id')
            ->limit(1)
            ->update(['durum' => Odeme::DURUM_BASARILI]);
    }

    private function eticaretTahsilatiOlustur(Siparis $siparis): void
    {
        $firmaId = (int) ($siparis->firma_id ?? 0);
        if ($firmaId <= 0) {
            return;
        }
        $this->ecommerceMuhasebeEntegrasyonServisi->siparisiMuhasebeyeEntegreEt($siparis);
    }

    private function stokDus(Siparis $siparis): void
    {
        $siparis->load('kalemler');

        foreach ($siparis->kalemler as $kalem) {
            /** @var SiparisKalemi $kalem */
            $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                ->whereKey($kalem->stok_karti_id)
                ->lockForUpdate()
                ->first());

            if (! $stok) {
                throw ValidationException::withMessages([
                    'stok' => $kalem->urun_adi_snapshot.' ürünü artık mevcut değil.',
                ]);
            }

            if (! (bool) $stok->stok_takip) {
                continue;
            }

            $miktar = (float) $kalem->miktar;
            $rezervKalemde = (float) ($kalem->stok_rezerv_miktari ?? 0);

            if ($rezervKalemde > 0) {
                $rezervHavuz = (float) ($stok->rezerve_miktar ?? 0);
                if ($rezervHavuz + 0.0001 < $rezervKalemde) {
                    $this->olay('stok.rezerv_stok_tutarsizligi', 'error', 'Rezerv miktari stok rezerv havuzundan buyuk.', [
                        'firma_id' => (int) $stok->firma_id,
                        'siparis_id' => (int) $siparis->id,
                        'stok_id' => (int) $stok->id,
                    ]);
                    throw ValidationException::withMessages([
                        'stok' => $stok->ad.' rezervasyon tutarsızlığı.',
                    ]);
                }

                $yeniRezerv = max(0.0, $rezervHavuz - $rezervKalemde);
                StokKarti::tenantScopeOlmadan(function () use ($stok, $yeniRezerv): void {
                    StokKarti::query()->whereKey($stok->id)->update(['rezerve_miktar' => $yeniRezerv]);
                });
                $this->depoRezerviniAzalt($siparis, $kalem, $rezervKalemde);

                SiparisKalemi::query()->whereKey($kalem->id)->update(['stok_rezerv_miktari' => 0]);
            }

            $stokHareketi = app(StokHareketServisi::class)->kayitOlustur((int) $siparis->firma_id, [
                'stok_id' => (int) $stok->id,
                'depo_id' => (int) ($kalem->depo_id ?? $stok->depo_id ?? 0),
                'islem_turu' => StokHareketIslemTuru::Satis,
                'miktar' => $miktar,
                'birim_fiyat' => $kalem->birim_fiyat,
                'belge_turu' => StokBelgeTuru::Duzeltme,
                'belge_id' => (int) $siparis->id,
                'referans_tipi' => StokBelgeTuru::Duzeltme->value,
                'referans_id' => (int) $siparis->id,
                'aciklama' => 'E-ticaret sipariş stok çıkışı #'.$siparis->siparis_no,
                'tarih' => now(),
                'seri_nolari' => $this->eTicaretSerileriniSec($stok, $kalem, $miktar),
            ], true);

        }
    }

    private function stokIade(Siparis $siparis): void
    {
        $siparis->load('kalemler');

        foreach ($siparis->kalemler as $kalem) {
            /** @var SiparisKalemi $kalem */
            $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                ->whereKey($kalem->stok_karti_id)
                ->lockForUpdate()
                ->first());

            if (! $stok || ! (bool) $stok->stok_takip) {
                continue;
            }

            app(StokHareketServisi::class)->kayitOlustur((int) $siparis->firma_id, [
                'stok_id' => (int) $stok->id,
                'depo_id' => (int) ($kalem->depo_id ?? $stok->depo_id ?? 0),
                'islem_turu' => StokHareketIslemTuru::SatisIadesi,
                'miktar' => $kalem->miktar,
                'birim_fiyat' => $kalem->birim_fiyat,
                'belge_turu' => StokBelgeTuru::Duzeltme,
                'belge_id' => (int) $siparis->id,
                'referans_tipi' => StokBelgeTuru::Duzeltme->value,
                'referans_id' => (int) $siparis->id,
                'aciklama' => 'E-ticaret sipariş stok iadesi #'.$siparis->siparis_no,
                'tarih' => now(),
                'seri_nolari' => array_values(array_filter(array_map('trim', (array) ($kalem->seri_nolari ?? [])))),
            ], true);
        }
    }

    private function rezervKaldir(Siparis $siparis): void
    {
        $siparis->load('kalemler');

        foreach ($siparis->kalemler as $kalem) {
            /** @var SiparisKalemi $kalem */
            $rez = (float) ($kalem->stok_rezerv_miktari ?? 0);
            if ($rez <= 0) {
                continue;
            }

            $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                ->whereKey($kalem->stok_karti_id)
                ->lockForUpdate()
                ->first());

            if (! $stok || ! (bool) $stok->stok_takip) {
                SiparisKalemi::query()->whereKey($kalem->id)->update(['stok_rezerv_miktari' => 0]);

                continue;
            }

            $yeniRezerv = max(0.0, (float) ($stok->rezerve_miktar ?? 0) - $rez);
            StokKarti::tenantScopeOlmadan(function () use ($stok, $yeniRezerv): void {
                StokKarti::query()->whereKey($stok->id)->update(['rezerve_miktar' => $yeniRezerv]);
            });
            $this->depoRezerviniAzalt($siparis, $kalem, $rez);
            $this->olay('stok.rezerv_cozuldu', 'info', 'Siparis iptalinde stok rezervi cozuldu.', [
                'firma_id' => (int) $siparis->firma_id,
                'siparis_id' => (int) $siparis->id,
                'stok_id' => (int) $stok->id,
            ]);

            SiparisKalemi::query()->whereKey($kalem->id)->update(['stok_rezerv_miktari' => 0]);
        }
    }

    private function depoRezerviniAzalt(Siparis $siparis, SiparisKalemi $kalem, float $miktar): void
    {
        $depoId = (int) ($kalem->depo_id ?? 0);
        if ($depoId < 1 || $miktar <= 0) {
            return;
        }

        StokDepoBakiyesi::tenantScopeOlmadan(function () use ($siparis, $kalem, $depoId, $miktar): void {
            $bakiye = StokDepoBakiyesi::query()
                ->where('firma_id', (int) $siparis->firma_id)
                ->where('depo_id', $depoId)
                ->where('stok_id', (int) $kalem->stok_karti_id)
                ->lockForUpdate()
                ->first();

            if (! $bakiye) {
                $this->olay('stok.depo_rezerv_kaydi_bulunamadi', 'warning', 'Depo rezerv kaydı bulunamadı.', [
                    'firma_id' => (int) $siparis->firma_id,
                    'siparis_id' => (int) $siparis->id,
                    'stok_id' => (int) $kalem->stok_karti_id,
                    'depo_id' => $depoId,
                ]);

                return;
            }

            $mevcut = (float) ($bakiye->rezerve_miktar ?? 0);
            $bakiye->update(['rezerve_miktar' => max(0.0, $mevcut - $miktar)]);
        });
    }

    /** @return array<int, string> */
    private function eTicaretSerileriniSec(StokKarti $stok, SiparisKalemi $kalem, float $miktar): array
    {
        if ((string) ($stok->stok_takip_tipi ?? '') !== StokKarti::STOK_TAKIP_TIPI_SERI) {
            return [];
        }

        $istenen = (int) round($miktar);
        if ($istenen < 1) {
            return [];
        }

        $sorgu = StokSeriNo::tenantScopeOlmadan(fn () => StokSeriNo::query()
            ->where('firma_id', (int) $stok->firma_id)
            ->where('stok_id', (int) $stok->id)
            ->where('durum', 'stokta')
            ->when((int) ($kalem->depo_id ?? 0) > 0, fn ($query) => $query->where('depo_id', (int) $kalem->depo_id))
            ->when((int) ($kalem->depo_id ?? 0) < 1, fn ($query) => $query->whereNull('depo_id'))
            ->lockForUpdate()
            ->orderBy('id')
            ->limit($istenen)
            ->pluck('seri_no')
            ->map(fn ($seriNo): string => trim((string) $seriNo))
            ->filter()
            ->values()
            ->all());

        if (count($sorgu) > 0 && count($sorgu) < $istenen) {
            throw ValidationException::withMessages([
                'stok' => $stok->ad.' için yeterli kullanılabilir seri numarası bulunamadı.',
            ]);
        }

        if (count($sorgu) === $istenen) {
            $kalem->update(['seri_nolari' => $sorgu]);
        }

        return $sorgu;
    }

    private function odemeNoUret(): string
    {
        $prefix = 'ODM'.now()->format('Ymd');
        $son = Odeme::query()
            ->where('odeme_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('odeme_no');

        $sira = 1;
        if (is_string($son) && str_starts_with($son, $prefix)) {
            $num = (int) substr($son, strlen($prefix));
            $sira = $num + 1;
        }

        return $prefix.str_pad((string) $sira, 6, '0', STR_PAD_LEFT);
    }

    private function siparisOnaylandiBildir(Siparis $siparis): void
    {
        DB::afterCommit(function () use ($siparis): void {
            $this->bildirimServisi->siparisOnaylandi($siparis->fresh());
        });
    }

    private function odemeBasarisizBildir(Siparis $siparis): void
    {
        DB::afterCommit(function () use ($siparis): void {
            $this->bildirimServisi->odemeBasarisiz($siparis->fresh());
        });
    }

    private function siparisIptalBildir(Siparis $siparis, string $eskiDurum, string $yeniDurum): void
    {
        DB::afterCommit(function () use ($siparis, $eskiDurum, $yeniDurum): void {
            $this->bildirimServisi->siparisDurumDegisti($siparis->fresh(), $eskiDurum, $yeniDurum);
        });
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function olay(string $tip, string $seviye, string $mesaj, array $context = []): void
    {
        $this->sistemOlayServisi->olayKaydet($tip, $seviye, $mesaj, $context);
    }
}
