<?php

namespace App\Services\Restoran;

use App\Models\Personel\Personel;
use App\Models\Restoran\RestoranAdisyonu;
use App\Models\Scopes\FirmaIdTenantScope;
use App\Services\TenantContextService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class RestoranPaketServisServisi
{
    public function kuryeAta(RestoranAdisyonu $adisyon, int $kuryePersonelId): RestoranAdisyonu
    {
        $adisyon = $this->adisyonuYenile($adisyon);
        $this->paketSiparisDogrula($adisyon);
        $this->sonlanmisPaketDogrula($adisyon);
        $this->durumGecisiDogrula((string) $adisyon->paket_durum, RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI);
        $this->kuryeDogrula($adisyon, $kuryePersonelId);

        $adisyon->fill([
            'kurye_personel_id' => $kuryePersonelId,
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI,
        ])->save();

        return $adisyon->refresh();
    }

    public function teslimatPlanla(RestoranAdisyonu $adisyon, Carbon|string $tahminiTeslimat, ?string $not = null): RestoranAdisyonu
    {
        $adisyon = $this->adisyonuYenile($adisyon);
        $this->paketSiparisDogrula($adisyon);
        $this->sonlanmisPaketDogrula($adisyon);

        $tarih = Carbon::parse($tahminiTeslimat);
        if ($tarih->lt(now()->subMinute())) {
            throw ValidationException::withMessages([
                'tahmini_teslimat_at' => 'Tahmini teslimat gecmis bir zaman olamaz.',
            ]);
        }

        $adisyon->fill([
            'tahmini_teslimat_at' => $tarih,
            'teslimat_notu' => $this->teslimatNotunuDogrula($not),
        ])->save();

        return $adisyon->refresh();
    }

    public function yolaCikar(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        $adisyon = $this->adisyonuYenile($adisyon);
        $this->paketSiparisDogrula($adisyon);
        $this->sonlanmisPaketDogrula($adisyon);

        if (! $adisyon->kurye_personel_id) {
            throw ValidationException::withMessages([
                'kurye_personel_id' => 'Paket siparişi yola çıkarmak için kurye atanmalıdır.',
            ]);
        }

        $this->durumGecisiDogrula((string) $adisyon->paket_durum, RestoranAdisyonu::PAKET_DURUM_YOLDA);

        $adisyon->fill([
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_YOLDA,
        ])->save();

        return $adisyon->refresh();
    }

    public function teslimEdildi(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        $adisyon = $this->adisyonuYenile($adisyon);
        $this->paketSiparisDogrula($adisyon);
        $this->sonlanmisPaketDogrula($adisyon);
        $this->durumGecisiDogrula((string) $adisyon->paket_durum, RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI);

        $adisyon->fill([
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
            'teslimat_at' => now(),
        ])->save();

        return $adisyon->refresh();
    }

    public function iptalEt(RestoranAdisyonu $adisyon, ?string $neden = null): RestoranAdisyonu
    {
        $adisyon = $this->adisyonuYenile($adisyon);
        $this->paketSiparisDogrula($adisyon);
        $this->sonlanmisPaketDogrula($adisyon);

        if ($adisyon->finans_hareketi_id) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'Tahsilatı yapılmış paket sipariş bu akıştan iptal edilemez.',
            ]);
        }

        $not = trim((string) $adisyon->notlar);
        if ($neden !== null && trim($neden) !== '') {
            $not = trim($not."\nPaket iptal nedeni: ".trim($neden));
        }

        $adisyon->fill([
            'paket_durum' => RestoranAdisyonu::PAKET_DURUM_IPTAL,
            'durum' => RestoranAdisyonu::DURUM_IPTAL,
            'kapanis_at' => now(),
            'notlar' => $not !== '' ? $not : null,
        ])->save();

        return $adisyon->refresh();
    }

    private function adisyonuYenile(RestoranAdisyonu $adisyon): RestoranAdisyonu
    {
        $this->aktifFirmaDogrula((int) $adisyon->firma_id);

        return RestoranAdisyonu::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($adisyon->id)
            ->firstOrFail();
    }

    private function paketSiparisDogrula(RestoranAdisyonu $adisyon): void
    {
        if (! in_array((string) $adisyon->siparis_tipi, ['paket', 'online'], true)) {
            throw ValidationException::withMessages([
                'siparis_tipi' => 'Bu işlem sadece paket veya online siparişlerde yapılabilir.',
            ]);
        }

        if ($adisyon->durum === RestoranAdisyonu::DURUM_IPTAL) {
            throw ValidationException::withMessages([
                'adisyon_id' => 'İptal edilmiş adisyonda paket servis işlemi yapılamaz.',
            ]);
        }
    }

    private function sonlanmisPaketDogrula(RestoranAdisyonu $adisyon): void
    {
        if (in_array((string) $adisyon->paket_durum, [
            RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
            RestoranAdisyonu::PAKET_DURUM_IPTAL,
        ], true)) {
            throw ValidationException::withMessages([
                'paket_durum' => 'Sonlanmış paket siparişte durum değiştirilemez.',
            ]);
        }
    }

    private function durumGecisiDogrula(string $mevcutDurum, string $hedefDurum): void
    {
        $izinliGecisler = [
            RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR => [
                RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI,
                RestoranAdisyonu::PAKET_DURUM_IPTAL,
            ],
            RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI => [
                RestoranAdisyonu::PAKET_DURUM_YOLDA,
                RestoranAdisyonu::PAKET_DURUM_IPTAL,
            ],
            RestoranAdisyonu::PAKET_DURUM_YOLDA => [
                RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI,
                RestoranAdisyonu::PAKET_DURUM_IPTAL,
            ],
        ];

        if (! in_array($hedefDurum, $izinliGecisler[$mevcutDurum] ?? [], true)) {
            throw ValidationException::withMessages([
                'paket_durum' => 'Paket servis durum geçişi geçerli değil.',
            ]);
        }
    }

    private function kuryeDogrula(RestoranAdisyonu $adisyon, int $kuryePersonelId): void
    {
        $kurye = Personel::query()
            ->withoutGlobalScope(FirmaIdTenantScope::class)
            ->where('firma_id', $adisyon->firma_id)
            ->whereKey($kuryePersonelId)
            ->first();

        if (! $kurye) {
            throw ValidationException::withMessages([
                'kurye_personel_id' => 'Kurye aynı firmaya ait aktif bir personel olmalıdır.',
            ]);
        }

        if ((string) $kurye->durum !== Personel::DURUM_AKTIF) {
            throw ValidationException::withMessages([
                'kurye_personel_id' => 'Pasif personel kurye olarak atanamaz.',
            ]);
        }
    }

    private function aktifFirmaDogrula(int $firmaId): void
    {
        $aktifFirmaId = app(TenantContextService::class)->aktifFirmaId();

        if ($aktifFirmaId && (int) $aktifFirmaId !== $firmaId) {
            throw ValidationException::withMessages([
                'firma_id' => 'Paket servis işlemi sadece aktif firma için yapılabilir.',
            ]);
        }
    }

    private function teslimatNotunuDogrula(?string $not): ?string
    {
        $not = trim((string) $not);
        if ($not === '') {
            return null;
        }

        if (mb_strlen($not) > 300) {
            throw ValidationException::withMessages([
                'teslimat_notu' => 'Teslimat notu en fazla 300 karakter olabilir.',
            ]);
        }

        return $not;
    }
}
