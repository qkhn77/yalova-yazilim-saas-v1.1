<?php

namespace App\Muhasebe\Guvenlik;

use App\Models\Firma;
use App\Models\User;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Illuminate\Support\Facades\Auth;

/**
 * Muhasebe servis katmanında firma (tenant) sınırı.
 *
 * - Normal kullanıcı: yalnızca oturumdaki aktif firma ile yazma.
 * - Süper yönetici: tüm firmalara yazma (panel / operasyonel kullanım); ek policy bu adımda yok.
 * - Kullanıcı yoksa (konsol/kuyruk): açıkça hata — arka plan işleri ayrı bağlam ile çağırmalıdır.
 */
class MuhasebeFirmaErisimDenetleyicisi
{
    public function __construct(
        private readonly TenantContextService $kiraciBaglami,
    ) {}

    /**
     * Yazma işlemi öncesi çağrılır. Hedef kaydın firma_id değeri ile doğrulanır.
     *
     * @throws IsKuraliIstisnasi
     */
    public function yazmaIcinFirmaKontrolEt(int $firmaId): void
    {
        $kullanici = Auth::user();
        if (! $kullanici instanceof User) {
            throw new IsKuraliIstisnasi('Muhasebe yazma işlemi için kimlik doğrulanmış kullanıcı gerekir.');
        }

        if ($this->superYoneticiMi($kullanici)) {
            return;
        }

        $aktif = $this->kiraciBaglami->aktifFirmaId();
        if ($aktif === null) {
            throw new IsKuraliIstisnasi('Aktif firma seçilmeden muhasebe işlemi yapılamaz.');
        }

        if ($aktif !== $firmaId) {
            throw new IsKuraliIstisnasi('Bu firmaya ait olmayan kayıt üzerinde işlem yapılamaz.');
        }
    }

    /**
     * Ön yüz sipariş ödemesi gibi oturumsuz / sistem çağrıları: yalnızca firma varlığı.
     */
    public function eTicaretYazmaIcinFirmaKontrolEt(int $firmaId): void
    {
        if (! Firma::query()->whereKey($firmaId)->exists()) {
            throw new IsKuraliIstisnasi('Geçersiz firma.');
        }
    }

    public function superYoneticiMi(?User $kullanici): bool
    {
        return KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }

    /**
     * Okuma / rapor için: süper admin serbest; kiracı yalnızca kendi firması.
     */
    public function okumaIcinFirmaKontrolEt(int $firmaId): void
    {
        $this->yazmaIcinFirmaKontrolEt($firmaId);
    }
}
