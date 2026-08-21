<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\TeknikServis\TeknikServisArizaTanimi;
use App\Models\TeknikServis\TeknikServisCihazTanimi;
use App\Models\TeknikServis\TeknikServisDurumTanimi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Models\TeknikServis\TeknikServisMarkaTanimi;
use App\Services\TeknikServisGenelAyarServisi;
use App\Services\TenantContextService;
use App\TeknikServis\Enumlar\MusteriOnayDurumu;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisKanali;
use App\TeknikServis\Enumlar\ServisTipi;
use App\TeknikServis\Servisler\TeknikServisFisNumarasiServisi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ArizaliCihazHizliKaydiOlusturSayfasi extends Page
{
    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.arizali-cihaz-hizli-kaydi-olustur';

    protected static ?string $title = 'Servis kaydı oluştur';

    /** @var array<string,mixed> */
    public array $data = [];

    /** @var array<int,string> */
    public array $cariSecenekleri = [];

    /** @var array<int,string> */
    public array $cihazSecenekleri = [];

    /** @var array<int,string> */
    public array $markaSecenekleri = [];

    /** @var array<int,string> */
    public array $arizaSecenekleri = [];

    /** @var array<int,string> */
    public array $servisDurumuSecenekleri = [];

    public function mount(): void
    {
        if ((request()->boolean('detay') || request()->boolean('gorsel_detay')) && ! str_ends_with(trim(request()->path(), '/'), '/detay')) {
            $this->redirect(TeknikServisKaydiKaynagi::getUrl($this->detayliFormRouteName(), [
                'detay' => request()->boolean('detay') ? 1 : null,
                'gorsel_detay' => request()->boolean('gorsel_detay') ? 1 : null,
            ]));

            return;
        }

        $firmaId = $this->firmaId();

        $this->servisDurumuSecenekleri = $this->cachedTanimSecenekleri(TeknikServisDurumTanimi::class, 'durum');
        $this->cihazSecenekleri = $this->cachedTanimSecenekleri(TeknikServisCihazTanimi::class, 'cihaz');
        $this->markaSecenekleri = $this->cachedTanimSecenekleri(TeknikServisMarkaTanimi::class, 'marka');
        $this->arizaSecenekleri = $this->cachedTanimSecenekleri(TeknikServisArizaTanimi::class, 'ariza');
        $this->cariSecenekleri = Cache::remember(
            'teknik-servis:hizli-arizali-create:v3:cari:'.$firmaId,
            300,
            fn (): array => Cari::query()
                ->select(['id', 'ad', 'telefon', 'gsm'])
                ->where('firma_id', $firmaId)
                ->where('durum', 'aktif')
                ->orderBy('ad')
                ->limit(100)
                ->get()
                ->mapWithKeys(fn (Cari $cari): array => [
                    (int) $cari->getKey() => trim((string) $cari->ad.' '.($cari->telefon ?: $cari->gsm ?: '')),
                ])
                ->all()
        );

        $this->data = [
            'oncelik' => app(TeknikServisGenelAyarServisi::class)->varsayilanOncelik($firmaId),
            'servis_kanali' => app(TeknikServisGenelAyarServisi::class)->varsayilanServisKanali($firmaId),
            'fis_no' => '',
            'kabul_tarihi' => now()->format('Y-m-d\TH:i'),
            'servis_durumu_id' => app(TeknikServisGenelAyarServisi::class)->varsayilanServisDurumuId($firmaId) ?? array_key_first($this->servisDurumuSecenekleri),
            'cari_id' => array_key_first($this->cariSecenekleri),
            'musteri_tel' => '',
            'cihaz_id' => array_key_first($this->cihazSecenekleri),
            'marka_id' => array_key_first($this->markaSecenekleri),
            'model_no' => '',
            'seri_no' => '',
            'arizalar' => [],
            'musteri_sikayeti' => '',
            'musteriye_gorunen_not' => '',
        ];
    }

    public function create(): void
    {
        $validated = $this->validate([
            'data.oncelik' => ['required', 'string'],
            'data.servis_kanali' => ['required', 'string'],
            'data.fis_no' => ['nullable', 'string', 'max:64'],
            'data.kabul_tarihi' => ['required', 'date'],
            'data.servis_durumu_id' => ['required', 'integer', 'min:1'],
            'data.cari_id' => ['required', 'integer', 'min:1'],
            'data.musteri_tel' => ['nullable', 'string', 'max:32'],
            'data.cihaz_id' => ['nullable', 'integer'],
            'data.marka_id' => ['nullable', 'integer'],
            'data.model_no' => ['nullable', 'string', 'max:128'],
            'data.seri_no' => ['required', 'string', 'max:128'],
            'data.arizalar' => ['array'],
            'data.musteri_sikayeti' => ['required', 'string'],
            'data.musteriye_gorunen_not' => ['nullable', 'string'],
        ])['data'];

        $firmaId = $this->firmaId();
        $fisNoServisi = app(TeknikServisFisNumarasiServisi::class);
        $fisNo = trim((string) $validated['fis_no']);
        if ($fisNo === '' || $fisNoServisi->fisNoKullaniliyorMu($fisNo)) {
            $fisNo = $fisNoServisi->benzersizUret($firmaId);
        }

        $kayit = DB::transaction(function () use ($validated, $firmaId, $fisNo): TeknikServisKaydi {
            $arizalar = array_values(array_filter(array_map('intval', (array) ($validated['arizalar'] ?? []))));
            $musteriOnayDurumu = app(TeknikServisGenelAyarServisi::class)->varsayilanMusteriOnayDurumu($firmaId);

            $kayit = TeknikServisKaydi::query()->create([
                'firma_id' => $firmaId,
                'servis_tipi' => $this->servisTipi()->value,
                'oncelik' => (string) $validated['oncelik'],
                'servis_kanali' => (string) $validated['servis_kanali'],
                'cari_id' => (int) $validated['cari_id'],
                'cihaz_id' => (int) ($validated['cihaz_id'] ?? 0) ?: null,
                'marka_id' => (int) ($validated['marka_id'] ?? 0) ?: null,
                'ariza_id' => $arizalar[0] ?? null,
                'model_no' => trim((string) ($validated['model_no'] ?? '')) ?: null,
                'seri_no' => trim((string) $validated['seri_no']),
                'musteri_tel' => trim((string) ($validated['musteri_tel'] ?? '')) ?: null,
                'musteri_sikayeti' => trim((string) $validated['musteri_sikayeti']),
                'musteriye_gorunen_not' => trim((string) ($validated['musteriye_gorunen_not'] ?? '')) ?: null,
                'kabul_tarihi' => (string) $validated['kabul_tarihi'],
                'fis_no' => $fisNo,
                'servis_durumu_id' => (int) $validated['servis_durumu_id'],
                'toplam_tutar' => 0,
                'odenen_tutar' => 0,
                'odeme_durumu' => OdemeDurumu::Odenmedi->value,
                'musteri_onay_durumu' => $musteriOnayDurumu,
                'tahsilat_para_birimi' => 'TRY',
                'olusturan_id' => Auth::id(),
            ]);

            if ($arizalar !== []) {
                $kayit->arizalar()->syncWithPivotValues($arizalar, ['firma_id' => $firmaId]);
            }

            return $kayit;
        });

        Notification::make()->title('Servis kaydı oluşturuldu')->success()->send();

        $this->redirect(TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $kayit]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('detayliForm')
                ->label('Detaylı Form')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray')
                ->url(fn (): string => TeknikServisKaydiKaynagi::getUrl($this->detayliFormRouteName(), ['detay' => 1])),
        ];
    }

    public function getTitle(): string
    {
        return match ($this->servisTipi()) {
            ServisTipi::DisServis => 'Dış servis kaydı oluştur',
            ServisTipi::Bakim => 'Bakım kaydı oluştur',
            default => 'Arızalı cihaz kaydı oluştur',
        };
    }

    /**
     * @return array<int,string>
     */
    private function cachedTanimSecenekleri(string $modelSinifi, string $tip): array
    {
        return Cache::remember(
            'teknik-servis:hizli-arizali-create:v3:tanim:'.$tip,
            300,
            fn (): array => $this->tanimSecenekleri($modelSinifi)
        );
    }

    /**
     * @return array<int,string>
     */
    private function tanimSecenekleri(string $modelSinifi): array
    {
        return $modelSinifi::query()
            ->select(['id', 'ad'])
            ->whereNull('firma_id')
            ->where('aktif', true)
            ->orderBy('siralama')
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all();
    }

    private function firmaId(): int
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        return $firmaId > 0
            ? $firmaId
            : (int) \App\Models\Firma::query()->orderBy('id')->value('id');
    }

    private function servisTipi(): ServisTipi
    {
        $path = trim(request()->path(), '/');

        if (str_contains($path, '/olustur/dis-servis')) {
            return ServisTipi::DisServis;
        }

        if (str_contains($path, '/olustur/bakim')) {
            return ServisTipi::Bakim;
        }

        return ServisTipi::ArizaliCihaz;
    }

    private function detayliFormRouteName(): string
    {
        return match ($this->servisTipi()) {
            ServisTipi::DisServis => 'create_dis_servis_detail',
            ServisTipi::Bakim => 'create_bakim_detail',
            default => 'create_arizali_detail',
        };
    }
}
