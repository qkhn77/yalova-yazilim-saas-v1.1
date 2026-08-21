<?php

namespace App\Services;

use App\Models\Ecommerce\Siparis;
use App\Modules\Urun\Servisler\SiparisDurumGecisServisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EcommerceSiparisTalepServisi
{
    public function __construct(
        private readonly SiparisDurumGecisServisi $durumGecisServisi,
    ) {}

    public function talepAc(Siparis $siparis, string $talepTuru, string $neden, ?int $kullaniciId = null): Siparis
    {
        $talepTuru = $talepTuru === 'iade' ? 'iade' : 'iptal';
        $neden = trim($neden);

        if ($neden === '') {
            throw ValidationException::withMessages(['neden' => 'Talep nedeni zorunludur.']);
        }

        $hedefDurum = $talepTuru === 'iade'
            ? Siparis::DURUM_IADE_TALEBI
            : Siparis::DURUM_IPTAL_TALEBI;

        return DB::transaction(function () use ($siparis, $talepTuru, $neden, $kullaniciId, $hedefDurum): Siparis {
            $guncelSiparis = $siparis->fresh() ?? $siparis;

            if (in_array((string) $guncelSiparis->durum, [Siparis::DURUM_IPTAL_TALEBI, Siparis::DURUM_IADE_TALEBI], true)) {
                throw ValidationException::withMessages(['neden' => 'Bu sipariş için zaten açık bir talep bulunuyor.']);
            }

            $this->durumGecisServisi->durumuGuncelle($guncelSiparis, $hedefDurum, $kullaniciId);

            $guncelSiparis = $guncelSiparis->fresh() ?? $guncelSiparis;
            $talepBasligi = $talepTuru === 'iade' ? 'Müşteri iade talebi' : 'Müşteri iptal talebi';
            $guncelSiparis->forceFill([
                'iptal_nedeni' => $talepTuru === 'iptal' ? $neden : $guncelSiparis->iptal_nedeni,
                'musteri_notu' => trim((string) ($guncelSiparis->musteri_notu ? $guncelSiparis->musteri_notu."\n" : '').$talepBasligi.': '.$neden),
                'operasyon_notu' => trim((string) ($guncelSiparis->operasyon_notu ? $guncelSiparis->operasyon_notu."\n" : '').$talepBasligi.': '.$neden),
            ])->save();

            return $guncelSiparis->fresh() ?? $guncelSiparis;
        });
    }

    public function iptalTalebiAcilabilirMi(Siparis $siparis): bool
    {
        return in_array((string) $siparis->durum, [
            Siparis::DURUM_ONAY_BEKLIYOR,
            Siparis::DURUM_EFT_ONAYI_BEKLIYOR,
            Siparis::DURUM_ONAYLANDI_YENI,
            Siparis::DURUM_BASARISIZ_ODEME,
            Siparis::DURUM_DETAY_BEKLEYEN,
            Siparis::DURUM_ODEME_BEKLENIYOR,
            Siparis::DURUM_ODENDI,
            Siparis::DURUM_HAZIRLANIYOR,
        ], true);
    }

    public function iadeTalebiAcilabilirMi(Siparis $siparis): bool
    {
        return in_array((string) $siparis->durum, [
            Siparis::DURUM_GONDERILDI,
            Siparis::DURUM_TESLIM_EDILDI,
            Siparis::DURUM_KARGOLANDI,
            Siparis::DURUM_TAMAMLANDI,
        ], true);
    }
}
