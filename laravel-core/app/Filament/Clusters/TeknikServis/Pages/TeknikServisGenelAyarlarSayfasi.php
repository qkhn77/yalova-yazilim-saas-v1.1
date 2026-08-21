<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisAyarSayfaErisimleri;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Services\TeknikServisGenelAyarServisi;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisKanali;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TeknikServisGenelAyarlarSayfasi extends Page
{
    use TeknikServisAyarSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Genel ayarlar';

    protected static ?string $navigationLabel = 'Genel ayarlar';

    protected static ?string $navigationGroup = 'Ayarlar ve şablonlar';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'genel-ayarlar';

    protected static string $view = 'filament.clusters.teknik-servis.pages.genel-ayarlar';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $fisNoOrnegi = '-';

    private ?int $aktifFirmaIdCache = null;

    /** @var array<int, string>|null */
    private ?array $servisDurumuSecenekleriCache = null;

    public function mount(TeknikServisGenelAyarServisi $ayarlar): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $this->data = $ayarlar->ayarlariGetir($firmaId);
        $this->fisNoOrnegi = $ayarlar->fisNoFormatOrnegi($firmaId);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Genel ayarlar';
    }

    public function getSubheading(): ?string
    {
        return 'Teknik servis kayıt akışı, fiş numarası, garanti/bakım ve muhasebe davranışlarını yönetin.';
    }

    public function kaydet(TeknikServisGenelAyarServisi $ayarlar): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $veri = $this->validate($this->dogrulamaKurallari())['data'];

        $ayarlar->kaydetAyarlar($firmaId, $veri);
        Cache::forget($this->servisDurumuCacheKey($firmaId));

        $this->data = $ayarlar->ayarlariGetir($firmaId);
        $this->fisNoOrnegi = $ayarlar->fisNoFormatOrnegi($firmaId);
        $this->servisDurumuSecenekleriCache = null;

        Notification::make()
            ->title('Teknik servis genel ayarları kaydedildi.')
            ->success()
            ->send();
    }

    public function varsayilanlaraDon(TeknikServisGenelAyarServisi $ayarlar): void
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId <= 0) {
            abort(403);
        }

        $ayarlar->kaydetAyarlar($firmaId, [
            'teknik_servis_varsayilan_servis_durumu_id' => null,
            'teknik_servis_varsayilan_oncelik' => Oncelik::Normal->value,
            'teknik_servis_varsayilan_servis_kanali' => ServisKanali::Magaza->value,
            'teknik_servis_varsayilan_musteri_onay_durumu' => MusteriOnayDurumu::Beklemede->value,
            'teknik_servis_fis_no_prefix' => 'YB-SER',
            'teknik_servis_varsayilan_bakim_periyot_ay' => 6,
            'teknik_servis_varsayilan_garanti_ay' => 0,
            'teknik_servis_bekleyen_fatura_senkron_aktif_mi' => true,
            'teknik_servis_teslimde_faturayi_onayla_mi' => true,
        ]);

        Cache::forget($this->servisDurumuCacheKey($firmaId));
        $this->data = $ayarlar->ayarlariGetir($firmaId);
        $this->fisNoOrnegi = $ayarlar->fisNoFormatOrnegi($firmaId);
        $this->servisDurumuSecenekleriCache = null;

        Notification::make()
            ->title('Teknik servis ayarları varsayılana döndürüldü.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('varsayilanlaraDon')
                ->label('Varsayılanlara dön')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Ayarlar varsayılana döndürülsün mü?')
                ->modalDescription('Bu işlem kayıt varsayılanlarını, fiş prefixini ve muhasebe toggle değerlerini sistem varsayılanlarına alır.')
                ->action('varsayilanlaraDon'),
        ];
    }

    private function aktifFirmaId(): int
    {
        if ($this->aktifFirmaIdCache !== null) {
            return $this->aktifFirmaIdCache;
        }

        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        if ($firmaId > 0) {
            return $this->aktifFirmaIdCache = $firmaId;
        }

        return $this->aktifFirmaIdCache = (int) Firma::query()->orderBy('id')->value('id');
    }

    /**
     * @return array<int, string>
     */
    public function servisDurumuSecenekleri(): array
    {
        $firmaId = $this->aktifFirmaId();

        if ($this->servisDurumuSecenekleriCache !== null) {
            return $this->servisDurumuSecenekleriCache;
        }

        return $this->servisDurumuSecenekleriCache = Cache::remember(
            $this->servisDurumuCacheKey($firmaId),
            300,
            fn (): array => TeknikServisDurumTanimi::query()
                ->withoutGlobalScopes()
                ->where('aktif', true)
                ->where(function (Builder $query) use ($firmaId): void {
                    $query->whereNull('firma_id')
                        ->orWhere('firma_id', $firmaId);
                })
                ->orderByRaw('firma_id is not null')
                ->orderBy('siralama')
                ->orderBy('ad')
                ->get(['id', 'ad', 'kod', 'firma_id'])
                ->mapWithKeys(function (TeknikServisDurumTanimi $durum): array {
                    $etiket = (string) $durum->ad;
                    if (filled($durum->kod)) {
                        $etiket .= ' ('.$durum->kod.')';
                    }
                    if ($durum->firma_id === null) {
                        $etiket .= ' - Genel';
                    }

                    return [(int) $durum->getKey() => $etiket];
                })
                ->all()
        );
    }

    /**
     * @return array<string, string>
     */
    public function oncelikSecenekleri(): array
    {
        return [
            Oncelik::Dusuk->value => 'Düşük',
            Oncelik::Normal->value => 'Normal',
            Oncelik::Acil->value => 'Acil',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function servisKanaliSecenekleri(): array
    {
        return [
            ServisKanali::Magaza->value => 'Mağaza',
            ServisKanali::Telefon->value => 'Telefon',
            ServisKanali::Whatsapp->value => 'WhatsApp',
            ServisKanali::Web->value => 'Web',
            ServisKanali::Saha->value => 'Saha',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function musteriOnaySecenekleri(): array
    {
        return [
            MusteriOnayDurumu::Beklemede->value => 'Beklemede',
            MusteriOnayDurumu::Onaylandi->value => 'Onaylandı',
            MusteriOnayDurumu::Reddedildi->value => 'Reddedildi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function ayarOzeti(): array
    {
        $data = $this->data ?? [];
        $durumId = (int) ($data['teknik_servis_varsayilan_servis_durumu_id'] ?? 0);
        $durumlar = $this->servisDurumuSecenekleri();

        return [
            'Servis durumu' => $durumId > 0 ? ($durumlar[$durumId] ?? 'Seçili durum') : 'Sistem varsayılanı',
            'Öncelik' => $this->oncelikSecenekleri()[(string) ($data['teknik_servis_varsayilan_oncelik'] ?? '')] ?? 'Normal',
            'Servis kanalı' => $this->servisKanaliSecenekleri()[(string) ($data['teknik_servis_varsayilan_servis_kanali'] ?? '')] ?? 'Mağaza',
            'Fiş örneği' => $this->fisNoOrnegi,
            'Bakım periyodu' => ((int) ($data['teknik_servis_varsayilan_bakim_periyot_ay'] ?? 6)).' ay',
            'Garanti' => ((int) ($data['teknik_servis_varsayilan_garanti_ay'] ?? 0)) > 0
                ? ((int) $data['teknik_servis_varsayilan_garanti_ay']).' ay'
                : 'Otomatik değil',
        ];
    }

    private function servisDurumuCacheKey(int $firmaId): string
    {
        return 'teknik-servis:genel-ayarlar:durum-secenekleri:v1:firma:'.$firmaId;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function dogrulamaKurallari(): array
    {
        return [
            'data.teknik_servis_varsayilan_servis_durumu_id' => ['nullable', 'integer', Rule::in(array_keys($this->servisDurumuSecenekleri()))],
            'data.teknik_servis_varsayilan_oncelik' => ['required', 'string', Rule::in(array_keys($this->oncelikSecenekleri()))],
            'data.teknik_servis_varsayilan_servis_kanali' => ['required', 'string', Rule::in(array_keys($this->servisKanaliSecenekleri()))],
            'data.teknik_servis_varsayilan_musteri_onay_durumu' => ['required', 'string', Rule::in(array_keys($this->musteriOnaySecenekleri()))],
            'data.teknik_servis_fis_no_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9\-_.]+$/'],
            'data.teknik_servis_varsayilan_bakim_periyot_ay' => ['required', 'integer', 'min:1', 'max:120'],
            'data.teknik_servis_varsayilan_garanti_ay' => ['required', 'integer', 'min:0', 'max:120'],
            'data.teknik_servis_bekleyen_fatura_senkron_aktif_mi' => ['nullable', 'boolean'],
            'data.teknik_servis_teslimde_faturayi_onayla_mi' => ['nullable', 'boolean'],
        ];
    }
}
