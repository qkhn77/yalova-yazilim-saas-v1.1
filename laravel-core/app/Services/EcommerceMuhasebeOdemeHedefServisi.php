<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceOdemeYontemi;
use App\Models\Ecommerce\Siparis;
use App\Models\Muhasebe\BankaHesabi;
use Illuminate\Validation\ValidationException;

class EcommerceMuhasebeOdemeHedefServisi
{
    /**
     * @return array{kanal:string,hesap_id:int,hesap_adi:string}
     */
    public function siparisIcinTahsilatHedefi(Siparis $siparis): array
    {
        $yontem = $this->odemeYontemiKaydi($siparis);
        $ayarlar = is_array($yontem?->saglayici_ayarlar) ? $yontem->saglayici_ayarlar : [];

        $kanal = trim((string) ($ayarlar['muhasebe_tahsilat_kanali'] ?? ''));
        $hesapId = match ($kanal) {
            'kasa' => (int) ($ayarlar['muhasebe_kasa_hesap_id'] ?? 0),
            'banka' => (int) ($ayarlar['muhasebe_banka_hesap_id'] ?? 0),
            'pos' => (int) ($ayarlar['muhasebe_pos_hesap_id'] ?? 0),
            default => 0,
        };

        if ($kanal !== '' && $hesapId > 0) {
            return [
                'kanal' => $kanal,
                'hesap_id' => $hesapId,
                'hesap_adi' => trim((string) ($this->hesapGorunenAdi($siparis, $kanal, $hesapId) ?? '')),
            ];
        }

        if ((string) $siparis->odeme_provider === 'havale_eft' && (int) ($siparis->havale_banka_hesap_id ?? 0) > 0) {
            $hesapId = (int) $siparis->havale_banka_hesap_id;

            return [
                'kanal' => 'banka',
                'hesap_id' => $hesapId,
                'hesap_adi' => trim((string) ($this->hesapGorunenAdi($siparis, 'banka', $hesapId) ?? (string) ($siparis->havale_banka_adi ?? ''))),
            ];
        }

        $firmaIds = app(EcommerceFirmaAyarServisi::class)->tahsilatIds((int) $siparis->firma_id);

        if ((int) ($firmaIds['pos_id'] ?? 0) > 0 && (string) $siparis->odeme_provider !== 'havale_eft') {
            return [
                'kanal' => 'pos',
                'hesap_id' => (int) $firmaIds['pos_id'],
                'hesap_adi' => trim((string) ($this->hesapGorunenAdi($siparis, 'pos', (int) $firmaIds['pos_id']) ?? '')),
            ];
        }

        if ((int) ($firmaIds['kasa_id'] ?? 0) > 0) {
            return [
                'kanal' => 'kasa',
                'hesap_id' => (int) $firmaIds['kasa_id'],
                'hesap_adi' => trim((string) ($this->hesapGorunenAdi($siparis, 'kasa', (int) $firmaIds['kasa_id']) ?? '')),
            ];
        }

        throw ValidationException::withMessages([
            'odeme_yontemi_secimi' => 'Seçilen ödeme yöntemi için muhasebe tahsilat hesabı tanımlı değil. Ödeme yöntemi düzenleme sayfasından hedef hesap seçin.',
        ]);
    }

    private function odemeYontemiKaydi(Siparis $siparis): ?EcommerceOdemeYontemi
    {
        $yontemId = (int) ($siparis->ecommerce_odeme_yontemi_id ?? 0);
        if ($yontemId > 0) {
            return EcommerceOdemeYontemi::query()
                ->where('firma_id', (int) $siparis->firma_id)
                ->whereKey($yontemId)
                ->first();
        }

        $kod = trim((string) ($siparis->odeme_yontemi_kodu ?? ''));
        if ($kod === '') {
            return null;
        }

        return EcommerceOdemeYontemi::query()
            ->where('firma_id', (int) $siparis->firma_id)
            ->where('kod', $kod)
            ->orderByDesc('varsayilan_mi')
            ->orderBy('id')
            ->first();
    }

    private function hesapGorunenAdi(Siparis $siparis, string $kanal, int $hesapId): ?string
    {
        $firmaId = (int) $siparis->firma_id;

        return match ($kanal) {
            'kasa' => \App\Models\Muhasebe\KasaHesabi::query()
                ->where('firma_id', $firmaId)
                ->whereKey($hesapId)
                ->value('ad'),
            'banka' => BankaHesabi::query()
                ->where('firma_id', $firmaId)
                ->whereKey($hesapId)
                ->value('ad'),
            'pos' => \App\Models\Muhasebe\PosHesabi::query()
                ->where('firma_id', $firmaId)
                ->whereKey($hesapId)
                ->value('ad'),
            default => null,
        };
    }
}
