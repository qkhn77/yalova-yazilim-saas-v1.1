<?php

namespace App\Services;

use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisKanali;
use App\TeknikServis\Servisler\TeknikServisFisNumarasiServisi;
use Illuminate\Database\Eloquent\Builder;

class TeknikServisGenelAyarServisi
{
    public function __construct(
        private readonly FirmaAyarDeposu $depo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ayarlariGetir(int $firmaId): array
    {
        return [
            'teknik_servis_varsayilan_servis_durumu_id' => $this->varsayilanServisDurumuId($firmaId),
            'teknik_servis_varsayilan_oncelik' => $this->varsayilanOncelik($firmaId),
            'teknik_servis_varsayilan_servis_kanali' => $this->varsayilanServisKanali($firmaId),
            'teknik_servis_varsayilan_musteri_onay_durumu' => $this->varsayilanMusteriOnayDurumu($firmaId),
            'teknik_servis_fis_no_prefix' => $this->fisNoPrefix($firmaId),
            'teknik_servis_varsayilan_bakim_periyot_ay' => $this->varsayilanBakimPeriyotAy($firmaId),
            'teknik_servis_varsayilan_garanti_ay' => $this->varsayilanGarantiAy($firmaId),
            'teknik_servis_bekleyen_fatura_senkron_aktif_mi' => $this->bekleyenFaturaSenkronAktifMi($firmaId),
            'teknik_servis_teslimde_faturayi_onayla_mi' => $this->teslimdeFaturayiOnaylaMi($firmaId),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function kaydetAyarlar(int $firmaId, array $data): void
    {
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_servis_durumu_id', $this->pozitifIntVeyaNull($data['teknik_servis_varsayilan_servis_durumu_id'] ?? null));
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_oncelik', $this->enumDegeri($data['teknik_servis_varsayilan_oncelik'] ?? null, Oncelik::class, Oncelik::Normal->value));
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_servis_kanali', $this->enumDegeri($data['teknik_servis_varsayilan_servis_kanali'] ?? null, ServisKanali::class, ServisKanali::Magaza->value));
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_musteri_onay_durumu', $this->enumDegeri($data['teknik_servis_varsayilan_musteri_onay_durumu'] ?? null, MusteriOnayDurumu::class, MusteriOnayDurumu::Beklemede->value));
        $this->depo->yaz($firmaId, 'teknik_servis_fis_no_prefix', $this->fisNoPrefixTemizle($data['teknik_servis_fis_no_prefix'] ?? null));
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_bakim_periyot_ay', $this->araliktaInt($data['teknik_servis_varsayilan_bakim_periyot_ay'] ?? null, 1, 120, 6));
        $this->depo->yaz($firmaId, 'teknik_servis_varsayilan_garanti_ay', $this->araliktaInt($data['teknik_servis_varsayilan_garanti_ay'] ?? null, 0, 120, 0));
        $this->depo->yaz($firmaId, 'teknik_servis_bekleyen_fatura_senkron_aktif_mi', (bool) ($data['teknik_servis_bekleyen_fatura_senkron_aktif_mi'] ?? true));
        $this->depo->yaz($firmaId, 'teknik_servis_teslimde_faturayi_onayla_mi', (bool) ($data['teknik_servis_teslimde_faturayi_onayla_mi'] ?? true));
    }

    public function varsayilanServisDurumuId(int $firmaId): ?int
    {
        $id = $this->pozitifIntVeyaNull($this->depo->oku($firmaId, 'teknik_servis_varsayilan_servis_durumu_id'));

        return $id !== null && $this->servisDurumuVarMi($firmaId, $id) ? $id : null;
    }

    public function varsayilanOncelik(int $firmaId): string
    {
        return $this->enumDegeri($this->depo->oku($firmaId, 'teknik_servis_varsayilan_oncelik'), Oncelik::class, Oncelik::Normal->value);
    }

    public function varsayilanServisKanali(int $firmaId): string
    {
        return $this->enumDegeri($this->depo->oku($firmaId, 'teknik_servis_varsayilan_servis_kanali'), ServisKanali::class, ServisKanali::Magaza->value);
    }

    public function varsayilanMusteriOnayDurumu(int $firmaId): string
    {
        return $this->enumDegeri($this->depo->oku($firmaId, 'teknik_servis_varsayilan_musteri_onay_durumu'), MusteriOnayDurumu::class, MusteriOnayDurumu::Beklemede->value);
    }

    public function fisNoPrefix(int $firmaId): string
    {
        return $this->fisNoPrefixTemizle($this->depo->oku($firmaId, 'teknik_servis_fis_no_prefix'));
    }

    public function varsayilanBakimPeriyotAy(int $firmaId): int
    {
        return $this->araliktaInt($this->depo->oku($firmaId, 'teknik_servis_varsayilan_bakim_periyot_ay'), 1, 120, 6);
    }

    public function varsayilanGarantiAy(int $firmaId): int
    {
        return $this->araliktaInt($this->depo->oku($firmaId, 'teknik_servis_varsayilan_garanti_ay'), 0, 120, 0);
    }

    public function bekleyenFaturaSenkronAktifMi(int $firmaId): bool
    {
        return (bool) $this->depo->oku($firmaId, 'teknik_servis_bekleyen_fatura_senkron_aktif_mi', true);
    }

    public function teslimdeFaturayiOnaylaMi(int $firmaId): bool
    {
        return (bool) $this->depo->oku($firmaId, 'teknik_servis_teslimde_faturayi_onayla_mi', true);
    }

    public function fisNoOrnegi(int $firmaId): string
    {
        return app(TeknikServisFisNumarasiServisi::class)->sonrakiAday($firmaId, $this->fisNoPrefix($firmaId));
    }

    public function fisNoFormatOrnegi(int $firmaId): string
    {
        return $this->fisNoPrefix($firmaId).'1001';
    }

    private function servisDurumuVarMi(int $firmaId, int $durumId): bool
    {
        return TeknikServisDurumTanimi::query()
            ->withoutGlobalScopes()
            ->whereKey($durumId)
            ->where('aktif', true)
            ->where(function (Builder $query) use ($firmaId): void {
                $query->whereNull('firma_id')
                    ->orWhere('firma_id', $firmaId);
            })
            ->exists();
    }

    /**
     * @param class-string<\BackedEnum> $enum
     */
    private function enumDegeri(mixed $deger, string $enum, string $varsayilan): string
    {
        $deger = (string) $deger;

        foreach ($enum::cases() as $vaka) {
            if ($vaka->value === $deger) {
                return $deger;
            }
        }

        return $varsayilan;
    }

    private function pozitifIntVeyaNull(mixed $deger): ?int
    {
        $int = (int) $deger;

        return $int > 0 ? $int : null;
    }

    private function araliktaInt(mixed $deger, int $min, int $max, int $varsayilan): int
    {
        $int = (int) $deger;

        if ($int < $min || $int > $max) {
            return $varsayilan;
        }

        return $int;
    }

    private function fisNoPrefixTemizle(mixed $deger): string
    {
        $prefix = trim((string) $deger);
        $prefix = preg_replace('/[^A-Za-z0-9\-_.]/', '', $prefix) ?: '';

        return $prefix !== '' ? mb_substr($prefix, 0, 24) : 'YB-SER';
    }
}
