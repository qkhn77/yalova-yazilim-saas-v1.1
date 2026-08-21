<?php

namespace App\Services;

use App\Models\User;

class SidebarService
{
    /** @var array<string, bool> */
    protected array $menuGorunurlukCache = [];

    /** @var array<string, bool> */
    protected array $bolumGorunurlukCache = [];

    /**
     * Özel sidebar ana bölümleri: `moduller.kod` ve `yetkiler.kod` ile eşleşmeli.
     * Sistem alanı (ör. kullanici) için modül aboneliği kontrol edilmez; yine de yetki gerekir.
     *
     * @return array<string, array{modul: string, yetki: string}>
     */
    public static function sidebarBolumHaritasi(): array
    {
        return [
            'muhasebe' => ['modul' => 'muhasebe', 'yetki' => 'muhasebe.goruntule'],
            'masraf_takip' => ['modul' => 'masraf_takip', 'yetki' => 'masraf_takip.goruntule'],
            'teklif_yonetimi' => ['modul' => 'teklif_yonetimi', 'yetki' => 'teklif_yonetimi.goruntule'],
            'barkodlu_satis' => ['modul' => 'barkodlu_satis', 'yetki' => 'barkodlu_satis.goruntule'],
            'teknik_servis' => ['modul' => 'teknik_servis', 'yetki' => 'teknik_servis.goruntule'],
            'restoran' => ['modul' => 'restoran', 'yetki' => 'restoran.goruntule'],
            'personel_takip' => ['modul' => 'personel_takip', 'yetki' => 'personel.goruntule'],
            'e_ticaret' => ['modul' => 'e_ticaret', 'yetki' => 'e_ticaret.goruntule'],
            'web' => ['modul' => 'web', 'yetki' => 'web.goruntule'],
            'sekreter' => ['modul' => 'sekreter', 'yetki' => 'sekreter.goruntule'],
            'ayarlar' => ['modul' => 'kullanici', 'yetki' => 'kullanici.goruntule'],
        ];
    }

    /**
     * Ana sidebar bölümü (Muhasebe, Teknik Servis, Web, Ayarlar) görünür mü?
     */
    public function sidebarBolumGorunurMu(?User $kullanici, ?int $firmaId, string $bolumAnahtari): bool
    {
        $cacheKey = implode('|', [
            (int) ($kullanici?->id ?? 0),
            (int) ($firmaId ?? 0),
            $bolumAnahtari,
        ]);

        if (array_key_exists($cacheKey, $this->bolumGorunurlukCache)) {
            return $this->bolumGorunurlukCache[$cacheKey];
        }

        $satir = static::sidebarBolumHaritasi()[$bolumAnahtari] ?? null;
        if ($satir === null) {
            return $this->bolumGorunurlukCache[$cacheKey] = false;
        }

        return $this->bolumGorunurlukCache[$cacheKey] = $this->menuGorunurMu($kullanici, $firmaId, $satir['modul'], $satir['yetki']);
    }

    public function __construct(
        protected ModulErisimService $modulErisimService,
        protected YetkiService $yetkiService
    ) {
    }

    public function menuGorunurMu(
        ?User $kullanici,
        ?int $firmaId,
        ?string $modulKodu,
        ?string $yetkiKodu
    ): bool {
        $cacheKey = implode('|', [
            (int) ($kullanici?->id ?? 0),
            (int) ($firmaId ?? 0),
            (string) $modulKodu,
            (string) $yetkiKodu,
        ]);

        if (array_key_exists($cacheKey, $this->menuGorunurlukCache)) {
            return $this->menuGorunurlukCache[$cacheKey];
        }

        if (! $kullanici) {
            return $this->menuGorunurlukCache[$cacheKey] = false;
        }

        if ($this->superAdminMi($kullanici)) {
            return $this->menuGorunurlukCache[$cacheKey] = true;
        }

        if (! $firmaId) {
            return $this->menuGorunurlukCache[$cacheKey] = false;
        }

        if (! $this->sistemAlaniMi($modulKodu)) {
            if (! $modulKodu || ! $this->modulErisimService->modulErisilebilirMi($firmaId, $modulKodu)) {
                return $this->menuGorunurlukCache[$cacheKey] = false;
            }
        }

        // Super admin dışındaki kullanıcılar için yetki kodu tanımsızsa güvenli şekilde gizle.
        if (! $yetkiKodu) {
            return $this->menuGorunurlukCache[$cacheKey] = false;
        }

        return $this->menuGorunurlukCache[$cacheKey] = $this->yetkiService->yetkiVarMi($kullanici, $firmaId, $yetkiKodu);
    }

    public function sistemAlaniMi(?string $modulKodu): bool
    {
        if ($modulKodu === null || $modulKodu === '') {
            return true;
        }

        return in_array($modulKodu, ['firma', 'kullanici', 'modul', 'panel', 'dashboard'], true);
    }

    protected function superAdminMi(User $kullanici): bool
    {
        return (bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false);
    }
}

