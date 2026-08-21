<?php

namespace App\Modules\Urun\Servisler;

use App\Models\Ecommerce\Sepet;
use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Ecommerce\Odeme;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokDepoBakiyesi;
use App\Services\FirmaAyarDeposu;
use App\Services\EcommerceBildirimServisi;
use App\Services\EcommerceFirmaAyarServisi;
use App\Services\EcommerceCheckoutOdemeYontemiServisi;
use App\Services\EcommerceKargoServisi;
use App\Services\EcommerceKampanyaServisi;
use App\Services\EcommerceUlkeServisi;
use App\Services\Front\FrontFiyatServisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckoutServisi
{
    public function __construct(
        private readonly SepetServisi $sepetServisi,
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
        private readonly EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
        private readonly EcommerceCheckoutOdemeYontemiServisi $ecommerceCheckoutOdemeYontemiServisi,
        private readonly EcommerceBildirimServisi $bildirimServisi,
        private readonly EcommerceKampanyaServisi $kampanyaServisi,
        private readonly FrontFiyatServisi $frontFiyatServisi,
        private readonly EcommerceKargoServisi $kargoServisi,
        private readonly EcommerceUlkeServisi $ulkeServisi,
    ) {}

    /**
     * @param  array<string, mixed>  $veri
     */
    public function siparisOlustur(Sepet $sepet, array $veri): Siparis
    {
        $firmaId = (int) ($this->firmaIdBul($sepet) ?? 0);
        if ($firmaId <= 0) {
            throw ValidationException::withMessages([
                'sepet' => 'Sepetteki ürünlerin firma bilgisi belirlenemedi. Lütfen sepeti yenileyip tekrar deneyin.',
            ]);
        }

        $validator = Validator::make($veri, [
            'musteri_ad_soyad' => ['required', 'string', 'min:3', 'max:255'],
            'musteri_telefon' => ['required', 'string', 'max:50'],
            'musteri_email' => ['nullable', 'email', 'max:255'],
            'teslimat_adresi' => ['required', 'string', 'min:10'],
            'teslimat_ulke' => ['nullable', 'string', 'max:10'],
            'teslimat_il' => ['required', 'string', 'max:120'],
            'teslimat_ilce' => ['nullable', 'string', 'max:120'],
            'teslimat_posta_kodu' => ['nullable', 'string', 'max:20'],
            'kargo_yontemi_id' => ['nullable', 'integer'],
            'odeme_yontemi_secimi' => ['required', 'string', 'max:64'],
            'notlar' => ['nullable', 'string'],
            'kupon_kodu' => ['nullable', 'string', 'max:64'],
        ], [
            'teslimat_il.required' => 'İl / şehir bilgisi zorunludur.',
            'odeme_yontemi_secimi.required' => 'Lütfen bir ödeme yöntemi seçin.',
        ]);
        $onayli = $validator->validate();

        if (! $this->ulkeServisi->postaKoduGecerliMi((string) ($onayli['teslimat_ulke'] ?? 'TR'), $onayli['teslimat_posta_kodu'] ?? null)) {
            $kural = $this->ulkeServisi->postaKoduKurali((string) ($onayli['teslimat_ulke'] ?? 'TR'));
            throw ValidationException::withMessages([
                'teslimat_posta_kodu' => 'Seçtiğiniz ülke için posta kodu formatı geçerli değil. Örnek: '.($kural['example'] ?? 'Posta kodu'),
            ]);
        }

        if (! $this->ulkeServisi->bolgeGecerliMi((string) ($onayli['teslimat_ulke'] ?? 'TR'), $onayli['teslimat_il'] ?? null)) {
            throw ValidationException::withMessages([
                'teslimat_il' => 'Seçtiğiniz ülke için geçerli bir il / eyalet seçin.',
            ]);
        }

        if ($sepet->kalemler->isEmpty()) {
            throw ValidationException::withMessages([
                'sepet' => 'Sepet boş. Sipariş oluşturamazsınız.',
            ]);
        }

        $siparis = DB::transaction(function () use ($sepet, $onayli, $firmaId): Siparis {
            $sepet->load('kalemler');
            $siparisParaBirimi = $this->frontFiyatServisi->aktifParaBirimi();
            $this->frontFiyatServisi->eksikKurKayitlariniTemizle();
            $this->sepetKurBilgileriniKontrolEt($sepet, $siparisParaBirimi);

            foreach ($sepet->kalemler as $kalem) {
                $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                    ->whereKey($kalem->stok_karti_id)
                    ->lockForUpdate()
                    ->first());

                if (! $stok) {
                    throw ValidationException::withMessages([
                        'stok' => $kalem->urun_adi_snapshot.' ürünü artık mevcut değil. Lütfen ürünü sepetten kaldırıp tekrar deneyin.',
                    ]);
                }

                if ((bool) $stok->stok_takip && $stok->musaitStokMiktari() + 0.0001 < (float) $kalem->miktar) {
                    throw ValidationException::withMessages([
                        'stok' => $stok->ad.' için yeterli stok yok. Lütfen adet miktarını azaltın veya ürünü sepetten kaldırın.',
                    ]);
                }
            }

            $toplamlar = $this->sepetServisi->toplamlar(
                $sepet,
                $onayli['kupon_kodu'] ?? null,
                Auth::id()
            );
            $this->eksikKurVarsaDurdur('sepet');

            $odemeYontemi = $this->ecommerceCheckoutOdemeYontemiServisi->secimBul(
                $firmaId,
                (string) ($onayli['odeme_yontemi_secimi'] ?? ''),
                (float) ($toplamlar['genel_toplam'] ?? 0),
                (string) ($toplamlar['para_birimi'] ?? $siparisParaBirimi)
            );

            if ($odemeYontemi === null) {
                throw ValidationException::withMessages([
                    'odeme_yontemi_secimi' => 'Seçtiğiniz ödeme yöntemi artık kullanılamıyor.',
                ]);
            }

            $adres = [
                'teslimat_ulke' => strtoupper((string) ($onayli['teslimat_ulke'] ?? 'TR')) ?: 'TR',
                'teslimat_il' => (string) ($onayli['teslimat_il'] ?? ''),
            ];
            $kargoSecenekleri = $this->kargoServisi->checkoutSecenekleri($firmaId, $sepet, $toplamlar, $adres);
            $kargoYontemi = null;
            $kargoUcreti = 0.0;
            $kargoParaBirimi = 'TRY';

            if ($kargoSecenekleri->isEmpty()) {
                throw ValidationException::withMessages([
                    'kargo_yontemi_id' => 'Teslimat adresiniz için uygun aktif kargo yöntemi bulunamadı. Lütfen ülke, il / eyalet ve posta kodu bilgisini kontrol edin.',
                ]);
            }

            if ($kargoSecenekleri->isNotEmpty()) {
                $kargoYontemiId = (int) ($onayli['kargo_yontemi_id'] ?? 0);
                $kargoYontemi = EcommerceKargoYontemi::query()
                    ->where('firma_id', $firmaId)
                    ->where('aktif_mi', true)
                    ->whereKey($kargoYontemiId)
                    ->first();

                if (! $kargoYontemi) {
                    throw ValidationException::withMessages([
                        'kargo_yontemi_id' => 'Lütfen geçerli bir kargo yöntemi seçin.',
                    ]);
                }

                $kargoUcreti = $this->kargoServisi->seciliYontemUcreti($kargoYontemi, $sepet, $toplamlar, $adres);
                if ($kargoUcreti < 0) {
                    throw ValidationException::withMessages([
                        'kargo_yontemi_id' => 'Seçtiğiniz kargo yöntemi teslimat adresi için uygun değil.',
                    ]);
                }

                $kargoParaBirimi = strtoupper((string) ($kargoYontemi->para_birimi ?: 'TRY'));
                if (! $this->frontFiyatServisi->cevrilebilirMi($kargoParaBirimi, $siparisParaBirimi)) {
                    throw ValidationException::withMessages([
                        'kargo_yontemi_id' => $kargoParaBirimi.'/'.$siparisParaBirimi.' kuru bulunamadığı için bu kargo yöntemiyle sipariş oluşturulamıyor.',
                    ]);
                }
            }

            $kargoUcretiSiparisParaBirimi = $this->frontFiyatServisi->cevir($kargoUcreti, $kargoParaBirimi, $siparisParaBirimi);
            $genelToplam = round((float) $toplamlar['genel_toplam'] + $kargoUcretiSiparisParaBirimi, 2);
            if (! $this->ecommerceCheckoutOdemeYontemiServisi->odemeSecenegiTutarIcinUygunMu($odemeYontemi, $genelToplam, $siparisParaBirimi)) {
                throw ValidationException::withMessages([
                    'odeme_yontemi_secimi' => 'Seçtiğiniz ödeme yöntemi bu sipariş tutarı veya para birimi için uygun değil. Lütfen farklı bir ödeme yöntemi seçin.',
                ]);
            }

            $odemeDakika = $this->ecommerceFirmaAyarServisi->odemeDakika($firmaId);
            $odemeDakika = max(1, (int) $odemeDakika);
            $siparisNo = $this->siparisNoUret();
            $eftMi = (string) ($odemeYontemi['tip'] ?? '') === 'havale_eft';
            $havaleReferansKodu = $eftMi ? $siparisNo : null;
            $havaleAciklamaNotu = $eftMi
                ? $this->havaleAciklamaNotuHazirla($odemeYontemi, $siparisNo, (string) $onayli['musteri_ad_soyad'])
                : null;

            $siparis = Siparis::query()->create([
                'siparis_no' => $siparisNo,
                'firma_id' => $firmaId > 0 ? $firmaId : null,
                'kullanici_id' => Auth::id(),
                'musteri_ad_soyad' => $onayli['musteri_ad_soyad'],
                'musteri_email' => $onayli['musteri_email'] ?? null,
                'musteri_telefon' => $onayli['musteri_telefon'],
                'teslimat_adresi' => $onayli['teslimat_adresi'],
                'teslimat_ulke' => strtoupper((string) ($onayli['teslimat_ulke'] ?? 'TR')) ?: 'TR',
                'teslimat_il' => $onayli['teslimat_il'] ?? null,
                'teslimat_ilce' => $onayli['teslimat_ilce'] ?? null,
                'teslimat_posta_kodu' => $onayli['teslimat_posta_kodu'] ?? null,
                'notlar' => $onayli['notlar'] ?? null,
                'para_birimi' => $siparisParaBirimi,
                'ara_toplam' => $toplamlar['ara_toplam'],
                'kdv_toplam' => $toplamlar['kdv_toplam'],
                'indirim_toplami' => $toplamlar['indirim_toplami'] ?? 0,
                'genel_toplam' => $genelToplam,
                'durum' => $eftMi ? Siparis::DURUM_EFT_ONAYI_BEKLIYOR : Siparis::DURUM_ONAY_BEKLIYOR,
                'kampanya_id' => $toplamlar['uygulanan_kampanya']['id'] ?? null,
                'kampanya_adi' => $toplamlar['uygulanan_kampanya']['ad'] ?? null,
                'kupon_kodu' => $toplamlar['uygulanan_kampanya']['kupon_kodu'] ?? null,
                'stok_dusuldu_mi' => false,
                'odeme_yontemi_kodu' => (string) ($odemeYontemi['kod'] ?? ''),
                'odeme_yontemi_ad' => (string) ($odemeYontemi['ad'] ?? ''),
                'odeme_provider' => (string) ($odemeYontemi['provider'] ?? ''),
                'ecommerce_odeme_yontemi_id' => (int) ($odemeYontemi['ecommerce_odeme_yontemi_id'] ?? 0) ?: null,
                'odeme_suresi_bitis_at' => $eftMi ? null : now()->addMinutes($odemeDakika),
                'odeme_deneme_sayisi' => 0,
                'havale_banka_hesap_id' => $eftMi && (int) ($odemeYontemi['banka_hesap_id'] ?? 0) > 0 ? (int) $odemeYontemi['banka_hesap_id'] : null,
                'havale_banka_adi' => $eftMi ? (string) ($odemeYontemi['banka_adi'] ?? '') : null,
                'havale_hesap_sahibi' => $eftMi ? (string) ($odemeYontemi['hesap_sahibi'] ?? '') : null,
                'havale_iban' => $eftMi ? (string) ($odemeYontemi['iban'] ?? '') : null,
                'havale_aciklama_notu' => $havaleAciklamaNotu,
                'havale_referans_kodu' => $havaleReferansKodu,
                'kargo_yontemi_id' => $kargoYontemi?->id,
                'kargo_ucreti' => $kargoUcretiSiparisParaBirimi,
                'kargo_para_birimi' => $siparisParaBirimi,
                'kargo_firmasi' => $kargoYontemi?->ad,
            ]);

            foreach ($sepet->kalemler as $kalem) {
                $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()
                    ->whereKey($kalem->stok_karti_id)
                    ->lockForUpdate()
                    ->first());

                if (! $stok) {
                    throw ValidationException::withMessages([
                        'stok' => $kalem->urun_adi_snapshot.' ürünü artık mevcut değil. Lütfen ürünü sepetten kaldırıp tekrar deneyin.',
                    ]);
                }

                $depoId = $this->stokDepoId($firmaId, $stok);
                $depoBakiyesi = $depoId !== null
                    ? $this->depoBakiyesiHazirla($firmaId, $depoId, $stok)
                    : null;
                $rezervMiktar = (bool) $stok->stok_takip ? (float) $kalem->miktar : 0.0;
                if ($rezervMiktar > 0) {
                    $musait = $depoBakiyesi !== null
                        ? max(0.0, (float) $depoBakiyesi->miktar - (float) $depoBakiyesi->rezerve_miktar)
                        : (float) $stok->musaitStokMiktari();
                    if ($musait + 0.0001 < $rezervMiktar) {
                        throw ValidationException::withMessages([
                            'stok' => $stok->ad.' için yeterli stok kalmadı. Lütfen sepetinizi yenileyip adet miktarını azaltın.',
                        ]);
                    }

                    StokKarti::tenantScopeOlmadan(function () use ($stok, $rezervMiktar): void {
                        StokKarti::query()->whereKey($stok->id)->increment('rezerve_miktar', $rezervMiktar);
                    });

                    if ($depoBakiyesi !== null) {
                        $depoBakiyesi->increment('rezerve_miktar', $rezervMiktar);
                    }
                }

                SiparisKalemi::query()->create([
                    'siparis_id' => $siparis->id,
                    'stok_karti_id' => $stok->id,
                    'depo_id' => $depoId,
                    'urun_adi_snapshot' => $kalem->urun_adi_snapshot,
                    'urun_kodu_snapshot' => $kalem->urun_kodu_snapshot,
                    'miktar' => $kalem->miktar,
                    'stok_rezerv_miktari' => $rezervMiktar,
                    'birim_fiyat' => $this->frontFiyatServisi->cevir(
                        (float) $kalem->birim_fiyat,
                        strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY')),
                        $siparisParaBirimi
                    ),
                    'para_birimi' => $siparisParaBirimi,
                    'kdv_orani' => $kalem->kdv_orani,
                    'satir_toplami' => $this->frontFiyatServisi->cevir(
                        (float) $kalem->satir_toplami,
                        strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY')),
                        $siparisParaBirimi
                    ),
                ]);
            }

            $this->siparisOdemeServisi->bekleyenOdemeOlustur(
                $siparis,
                $eftMi ? Odeme::PROVIDER_HAVALE_EFT : Odeme::PROVIDER_MOCK,
            );
            $this->kampanyaServisi->kampanyaKullaniminiKaydet($siparis);

            return $siparis->fresh(['kalemler', 'odemeler']);
        });

        $this->bildirimServisi->siparisAlindi($siparis);

        return $siparis;
    }

    private function stokDepoId(int $firmaId, StokKarti $stok): ?int
    {
        $ayar = app(FirmaAyarDeposu::class);
        if (! (bool) $ayar->oku($firmaId, 'stok_depo_modulu_aktif_mi', false)) {
            return null;
        }

        $adaylar = [
            (int) ($stok->depo_id ?? 0),
            (int) ($ayar->oku($firmaId, 'stok_varsayilan_depo_id', 0) ?? 0),
        ];

        foreach ($adaylar as $depoId) {
            if ($depoId > 0 && Depo::tenantScopeOlmadan(fn () => Depo::query()
                ->where('firma_id', $firmaId)
                ->whereKey($depoId)
                ->aktif()
                ->exists())) {
                return $depoId;
            }
        }

        return Depo::tenantScopeOlmadan(fn () => Depo::query()
            ->where('firma_id', $firmaId)
            ->aktif()
            ->where('varsayilan_mi', true)
            ->value('id'));
    }

    private function depoBakiyesiHazirla(int $firmaId, int $depoId, StokKarti $stok): StokDepoBakiyesi
    {
        return StokDepoBakiyesi::tenantScopeOlmadan(function () use ($firmaId, $depoId, $stok): StokDepoBakiyesi {
            $bakiye = StokDepoBakiyesi::query()
                ->where('firma_id', $firmaId)
                ->where('depo_id', $depoId)
                ->where('stok_id', $stok->id)
                ->lockForUpdate()
                ->first();

            if ($bakiye) {
                return $bakiye;
            }

            $digerDepoBakiyesiVar = StokDepoBakiyesi::query()
                ->where('firma_id', $firmaId)
                ->where('stok_id', $stok->id)
                ->exists();

            return StokDepoBakiyesi::query()->create([
                'firma_id' => $firmaId,
                'depo_id' => $depoId,
                'stok_id' => $stok->id,
                'miktar' => $digerDepoBakiyesiVar ? '0' : (string) ($stok->stok_miktari ?? 0),
                'rezerve_miktar' => '0',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $odemeYontemi
     */
    private function havaleAciklamaNotuHazirla(array $odemeYontemi, string $siparisNo, string $musteriAdSoyad): string
    {
        $sablon = trim((string) ($odemeYontemi['odeme_notu'] ?? ''));

        if ($sablon === '') {
            return 'Lütfen ödeme açıklama alanına '.$siparisNo.' sipariş numarasını yazın. Yönetici onayından sonra sipariş süreci başlatılacaktır.';
        }

        return strtr($sablon, [
            '{siparis_no}' => $siparisNo,
            '{musteri_ad_soyad}' => $musteriAdSoyad,
        ]);
    }

    private function siparisNoUret(): string
    {
        $prefix = 'SIP'.now()->format('Ymd');
        $son = Siparis::query()
            ->where('siparis_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('siparis_no');

        $sira = 1;
        if (is_string($son) && str_starts_with($son, $prefix)) {
            $num = (int) substr($son, strlen($prefix));
            $sira = $num + 1;
        }

        return $prefix.str_pad((string) $sira, 6, '0', STR_PAD_LEFT);
    }

    private function firmaIdBul(Sepet $sepet): ?int
    {
        $firmaId = null;
        foreach ($sepet->kalemler as $kalem) {
            $stok = StokKarti::tenantScopeOlmadan(fn () => StokKarti::query()->find($kalem->stok_karti_id));
            if (! $stok) {
                continue;
            }
            $stokFirma = (int) ($stok->firma_id ?? 0);
            if ($stokFirma <= 0) {
                continue;
            }
            if ($firmaId === null) {
                $firmaId = $stokFirma;

                continue;
            }
            if ($firmaId !== $stokFirma) {
                return null;
            }
        }

        return $firmaId;
    }

    private function sepetKurBilgileriniKontrolEt(Sepet $sepet, string $siparisParaBirimi): void
    {
        foreach ($sepet->kalemler as $kalem) {
            $kalemParaBirimi = strtoupper((string) ($kalem->getAttribute('para_birimi') ?: 'TRY'));
            if ($this->frontFiyatServisi->cevrilebilirMi($kalemParaBirimi, $siparisParaBirimi)) {
                continue;
            }

            throw ValidationException::withMessages([
                'sepet' => $kalemParaBirimi.'/'.$siparisParaBirimi.' kuru bulunamadığı için sepetiniz siparişe dönüştürülemiyor.',
            ]);
        }
    }

    private function eksikKurVarsaDurdur(string $alan): void
    {
        if (! $this->frontFiyatServisi->eksikKurVarMi()) {
            return;
        }

        throw ValidationException::withMessages([
            $alan => implode(' ', $this->frontFiyatServisi->eksikKurMesajlari()).' Lütfen güncel döviz kuru eklendikten sonra tekrar deneyin.',
        ]);
    }
}
