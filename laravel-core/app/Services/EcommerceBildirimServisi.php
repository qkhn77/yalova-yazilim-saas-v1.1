<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceBildirimLog;
use App\Models\Ecommerce\EcommerceBildirimSablonu;
use App\Models\Ecommerce\Siparis;
use App\Models\Firma;
use App\Support\EcommerceBildirimTanimlari;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EcommerceBildirimServisi
{
    public function __construct(
        private readonly FirmaAyarDeposu $depo,
        private readonly EcommerceBildirimAyarServisi $ayarServisi,
        private readonly EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
    ) {}

    public function siparisAlindi(Siparis $siparis): void
    {
        $this->olayTetikle($siparis, 'siparis_alindi');
    }

    public function siparisOnaylandi(Siparis $siparis): void
    {
        $this->olayTetikle($siparis, 'siparis_onaylandi');
    }

    public function kargoBilgisiGuncellendi(Siparis $siparis): void
    {
        $this->olayTetikle($siparis, 'kargo_bilgisi_guncellendi');
    }

    public function odemeBasarisiz(Siparis $siparis): void
    {
        $this->olayTetikle($siparis, 'odeme_basarisiz', [
            'odeme_linki' => route('odeme.show', $siparis),
        ]);
    }

    public function siparisDurumDegisti(Siparis $siparis, string $eskiDurum, string $yeniDurum): void
    {
        $normalizeYeni = $this->normalizeDurum($yeniDurum);

        $olay = match ($normalizeYeni) {
            Siparis::DURUM_GONDERILDI => 'kargoya_verildi',
            Siparis::DURUM_TESLIM_EDILDI => 'teslim_edildi',
            Siparis::DURUM_IPTAL_TALEBI => 'iptal_talebi',
            Siparis::DURUM_IPTAL_EDILDI => 'iptal_edildi',
            Siparis::DURUM_IADE_TALEBI => 'iade_talebi',
            Siparis::DURUM_IADE_EDILDI => 'iade_edildi',
            default => null,
        };

        if ($olay === null) {
            return;
        }

        $this->olayTetikle($siparis, $olay, [
            'eski_durum' => $eskiDurum,
            'yeni_durum' => $normalizeYeni,
        ]);
    }

    public function logKaydiTekrarGonder(EcommerceBildirimLog $log): void
    {
        $hedef = trim((string) ($log->hedef ?? ''));
        $kanal = (string) $log->kanal;

        $log->increment('deneme_sayisi');
        $log->update(['hata' => null]);

        if ($kanal === EcommerceBildirimTanimlari::KANAL_EMAIL) {
            if ($hedef === '' || ! filter_var($hedef, FILTER_VALIDATE_EMAIL)) {
                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_BASARISIZ,
                    'hata' => 'gecersiz_hedef_eposta',
                ]);

                return;
            }

            try {
                Mail::html($this->mailHtml((string) ($log->icerik ?? '')), function ($message) use ($hedef, $log): void {
                    $message->to($hedef)->subject((string) ($log->baslik ?? 'Bildirim'));
                });

                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_GONDERILDI,
                    'gonderildi_at' => now(),
                ]);

                return;
            } catch (Throwable $e) {
                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_BASARISIZ,
                    'hata' => $e->getMessage(),
                ]);

                return;
            }
        }

        if ($kanal === EcommerceBildirimTanimlari::KANAL_PANEL) {
            $log->update([
                'durum' => EcommerceBildirimLog::DURUM_GONDERILDI,
                'gonderildi_at' => now(),
            ]);

            return;
        }

        $log->update([
            'durum' => EcommerceBildirimLog::DURUM_KUYRUKTA,
            'hata' => 'kanal_entegrasyonu_yok',
        ]);
    }

    /**
     * @param  array<string, mixed>  $ek
     */
    private function olayTetikle(Siparis $siparis, string $olay, array $ek = []): void
    {
        $firmaId = (int) ($siparis->firma_id ?? 0);
        if ($firmaId <= 0) {
            return;
        }

        if (! $this->ecommerceFirmaAyarServisi->firmaEtkinMi($firmaId, false)) {
            return;
        }

        $gonderimler = EcommerceBildirimTanimlari::varsayilanGonderimler()[$olay] ?? [];
        if (empty($gonderimler)) {
            return;
        }

        foreach ($gonderimler as $gonderim) {
            $kanal = (string) ($gonderim['kanal'] ?? '');
            $hedefTipi = (string) ($gonderim['hedef'] ?? 'musteri');

            if ($kanal === '') {
                continue;
            }

            if (! $this->ayarServisi->kanalAktifMi($firmaId, $olay, $kanal)) {
                continue;
            }

            $this->kanalGonder($siparis, $olay, $kanal, $hedefTipi, $ek);
        }
    }

    /**
     * @param  array<string, mixed>  $ek
     */
    private function kanalGonder(Siparis $siparis, string $olay, string $kanal, string $hedefTipi, array $ek): void
    {
        $firmaId = (int) ($siparis->firma_id ?? 0);
        $locale = (string) $this->depo->oku($firmaId, 'varsayilan_dil', 'tr');
        $locale = $locale !== '' ? $locale : 'tr';

        $hedef = $this->hedefBul($siparis, $hedefTipi, $kanal, $firmaId);
        if ($hedef === null) {
            return;
        }

        if ($this->yakinLogVarMi($siparis->id, $olay, $kanal, $hedef)) {
            return;
        }

        [$baslik, $icerik] = $this->sablonCoz($siparis, $olay, $kanal, $locale, $hedefTipi, $ek);
        if (trim($baslik) === '' && trim($icerik) === '') {
            return;
        }

        $log = EcommerceBildirimLog::query()->create([
            'firma_id' => $firmaId,
            'siparis_id' => (int) $siparis->id,
            'olay' => $olay,
            'kanal' => $kanal,
            'locale' => $locale,
            'hedef' => $hedef,
            'baslik' => $baslik,
            'icerik' => $icerik,
            'durum' => EcommerceBildirimLog::DURUM_KUYRUKTA,
            'deneme_sayisi' => 1,
        ]);

        if ($kanal === EcommerceBildirimTanimlari::KANAL_EMAIL) {
            if (! filter_var($hedef, FILTER_VALIDATE_EMAIL)) {
                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_BASARISIZ,
                    'hata' => 'gecersiz_hedef_eposta',
                ]);

                return;
            }

            try {
                Mail::html($this->mailHtml($icerik), function ($message) use ($hedef, $baslik): void {
                    $message->to($hedef)->subject($baslik !== '' ? $baslik : 'Bildirim');
                });

                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_GONDERILDI,
                    'gonderildi_at' => now(),
                ]);
            } catch (Throwable $e) {
                $log->update([
                    'durum' => EcommerceBildirimLog::DURUM_BASARISIZ,
                    'hata' => $e->getMessage(),
                ]);
            }

            return;
        }

        if ($kanal === EcommerceBildirimTanimlari::KANAL_PANEL) {
            $log->update([
                'durum' => EcommerceBildirimLog::DURUM_GONDERILDI,
                'gonderildi_at' => now(),
            ]);

            return;
        }

        $log->update([
            'durum' => EcommerceBildirimLog::DURUM_KUYRUKTA,
            'hata' => 'kanal_entegrasyonu_yok',
        ]);
    }

    private function hedefBul(Siparis $siparis, string $hedefTipi, string $kanal, int $firmaId): ?string
    {
        if ($hedefTipi === 'admin') {
            if ($kanal === EcommerceBildirimTanimlari::KANAL_EMAIL) {
                $adminEposta = $this->adminEposta($firmaId);
                return $adminEposta ?: null;
            }

            if ($kanal === EcommerceBildirimTanimlari::KANAL_SMS) {
                return $this->adminTelefon($firmaId);
            }

            return 'admin';
        }

        if ($kanal === EcommerceBildirimTanimlari::KANAL_EMAIL) {
            $email = trim((string) ($siparis->musteri_email ?? ''));
            if ($email !== '') {
                return $email;
            }

            $kullaniciEmail = $siparis->kullanici?->email;
            return is_string($kullaniciEmail) && $kullaniciEmail !== '' ? $kullaniciEmail : null;
        }

        if ($kanal === EcommerceBildirimTanimlari::KANAL_SMS) {
            $telefon = trim((string) ($siparis->musteri_telefon ?? ''));
            return $telefon !== '' ? $telefon : null;
        }

        return 'musteri';
    }

    private function adminEposta(int $firmaId): ?string
    {
        $ozel = trim((string) $this->depo->oku($firmaId, 'ecommerce_bildirim_admin_eposta', ''));
        if ($ozel !== '') {
            return $ozel;
        }

        $firma = Firma::query()->find($firmaId);
        $eposta = $firma?->eposta;

        return is_string($eposta) && $eposta !== '' ? $eposta : null;
    }

    private function adminTelefon(int $firmaId): ?string
    {
        $firma = Firma::query()->find($firmaId);
        $telefon = $firma?->telefon;

        return is_string($telefon) && $telefon !== '' ? $telefon : null;
    }

    /**
     * @param  array<string, mixed>  $ek
     * @return array{0: string, 1: string}
     */
    private function sablonCoz(Siparis $siparis, string $olay, string $kanal, string $locale, string $hedefTipi, array $ek): array
    {
        $template = EcommerceBildirimSablonu::query()
            ->where('firma_id', (int) $siparis->firma_id)
            ->where('olay', $olay)
            ->where('kanal', $kanal)
            ->where('locale', $locale)
            ->first();

        if (! $template && $locale !== 'tr') {
            $template = EcommerceBildirimSablonu::query()
                ->where('firma_id', (int) $siparis->firma_id)
                ->where('olay', $olay)
                ->where('kanal', $kanal)
                ->where('locale', 'tr')
                ->first();
        }

        if ($template && ! (bool) $template->aktif_mi) {
            return ['', ''];
        }

        if ($template) {
            $baslik = (string) ($template->baslik ?? '');
            $icerik = (string) ($template->icerik ?? '');
        } else {
            [$baslik, $icerik] = $this->varsayilanSablon($olay, $kanal, $hedefTipi);
        }

        $degiskenler = $this->degiskenleriHazirla($siparis, $ek);

        return [
            $this->sablonUygula($baslik, $degiskenler),
            $this->sablonUygula($icerik, $degiskenler),
        ];
    }

    /**
     * @param  array<string, mixed>  $ek
     * @return array<string, string>
     */
    private function degiskenleriHazirla(Siparis $siparis, array $ek): array
    {
        $degiskenler = [
            'siparis_no' => (string) ($siparis->siparis_no ?? ''),
            'musteri_ad' => (string) ($siparis->musteri_ad_soyad ?? ''),
            'musteri_email' => (string) ($siparis->musteri_email ?? ''),
            'musteri_telefon' => (string) ($siparis->musteri_telefon ?? ''),
            'genel_toplam' => (string) ($siparis->genel_toplam ?? ''),
            'para_birimi' => (string) ($siparis->para_birimi ?? 'TRY'),
            'kargo_firmasi' => (string) ($siparis->kargo_firmasi ?? ''),
            'kargo_takip_no' => (string) ($siparis->takip_no ?? ''),
            'kargo_takip_linki' => (string) (app(EcommerceKargoTakipServisi::class)->takipUrl($siparis->kargo_firmasi, $siparis->takip_no) ?? ''),
            'odeme_linki' => (string) route('odeme.show', $siparis),
        ];

        foreach ($ek as $anahtar => $deger) {
            $degiskenler[$anahtar] = is_scalar($deger) ? (string) $deger : '';
        }

        return $degiskenler;
    }

    /**
     * @param  array<string, string>  $degiskenler
     */
    private function sablonUygula(string $metin, array $degiskenler): string
    {
        if ($metin === '') {
            return '';
        }

        $aranan = [];
        $degis = [];
        foreach ($degiskenler as $anahtar => $deger) {
            $aranan[] = '{'.$anahtar.'}';
            $degis[] = $deger;
        }

        return str_replace($aranan, $degis, $metin);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function varsayilanSablon(string $olay, string $kanal, string $hedefTipi): array
    {
        $siparisNo = '{siparis_no}';
        $musteriAd = '{musteri_ad}';
        $toplam = '{genel_toplam} {para_birimi}';
        $kargoFirma = '{kargo_firmasi}';
        $kargoNo = '{kargo_takip_no}';
        $odemeLink = '{odeme_linki}';

        return match ($olay) {
            'siparis_alindi' => [
                $hedefTipi === 'admin' ? 'Yeni sipariş alındı' : 'Siparişiniz alındı',
                $hedefTipi === 'admin'
                    ? "Yeni sipariş alındı.\nSipariş No: {$siparisNo}\nTutar: {$toplam}"
                    : "Merhaba {$musteriAd}, siparişiniz alındı.\nSipariş No: {$siparisNo}\nTutar: {$toplam}\nEn kısa sürede onaylanacaktır.",
            ],
            'siparis_onaylandi' => [
                'Siparişiniz onaylandı',
                "Merhaba {$musteriAd}, siparişiniz onaylandı.\nSipariş No: {$siparisNo}\nTutar: {$toplam}",
            ],
            'kargoya_verildi' => [
                'Kargonuz yola çıktı',
                "Merhaba {$musteriAd}, siparişiniz kargoya verildi.\nSipariş No: {$siparisNo}\nKargo: {$kargoFirma}\nTakip No: {$kargoNo}\nTakip Linki: {kargo_takip_linki}",
            ],
            'kargo_bilgisi_guncellendi' => [
                'Kargo bilgileriniz güncellendi',
                "Merhaba {$musteriAd}, siparişinizin kargo bilgileri güncellendi.\nSipariş No: {$siparisNo}\nKargo: {$kargoFirma}\nTakip No: {$kargoNo}\nTakip Linki: {kargo_takip_linki}",
            ],
            'teslim_edildi' => [
                'Siparişiniz teslim edildi',
                "Merhaba {$musteriAd}, siparişiniz teslim edildi.\nSipariş No: {$siparisNo}",
            ],
            'iptal_talebi' => [
                'Sipariş iptal talebi',
                "Sipariş için iptal talebi alındı.\nSipariş No: {$siparisNo}",
            ],
            'iptal_edildi' => [
                'Siparişiniz iptal edildi',
                "Merhaba {$musteriAd}, siparişiniz iptal edildi.\nSipariş No: {$siparisNo}",
            ],
            'iade_talebi' => [
                'Sipariş iade talebi',
                "Sipariş için iade talebi alındı.\nSipariş No: {$siparisNo}",
            ],
            'iade_edildi' => [
                'Sipariş iadesi tamamlandı',
                "Merhaba {$musteriAd}, siparişinizin iade işlemi tamamlandı.\nSipariş No: {$siparisNo}",
            ],
            'odeme_basarisiz' => [
                'Ödeme başarısız',
                $hedefTipi === 'admin'
                    ? "Sipariş için ödeme başarısız.\nSipariş No: {$siparisNo}"
                    : "Merhaba {$musteriAd}, ödeme işlemi başarısız oldu.\nSipariş No: {$siparisNo}\nÖdeme linki: {$odemeLink}",
            ],
            default => ['', ''],
        };
    }

    private function mailHtml(string $icerik): string
    {
        $icerik = trim($icerik);
        if ($icerik === '') {
            return '';
        }

        return nl2br(e($icerik));
    }

    private function normalizeDurum(string $durum): string
    {
        return match ($durum) {
            Siparis::DURUM_ODEME_BEKLENIYOR => Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
            Siparis::DURUM_BEKLEMEDE,
            Siparis::DURUM_ONAYLANDI => Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_KARGOLANDI => Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TAMAMLANDI => Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_IPTAL => Siparis::DURUM_IPTAL_EDILDI,
            default => $durum,
        };
    }

    private function yakinLogVarMi(int $siparisId, string $olay, string $kanal, string $hedef): bool
    {
        return EcommerceBildirimLog::query()
            ->where('siparis_id', $siparisId)
            ->where('olay', $olay)
            ->where('kanal', $kanal)
            ->where('hedef', $hedef)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();
    }
}
