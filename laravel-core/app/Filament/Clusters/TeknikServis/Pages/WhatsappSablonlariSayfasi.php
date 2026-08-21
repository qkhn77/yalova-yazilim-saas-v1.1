<?php

namespace App\Filament\Clusters\TeknikServis\Pages;

use App\Filament\Clusters\TeknikServis as TeknikServisCluster;
use App\Filament\Clusters\TeknikServis\Kaynaklar\TeknikServisAyarSayfaErisimleri;
use App\Models\Firma;
use App\Models\TeknikServis\TeknikServisMesajSablonu;
use App\Services\TenantContextService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class WhatsappSablonlariSayfasi extends Page
{
    use TeknikServisAyarSayfaErisimleri;

    protected static ?string $cluster = TeknikServisCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = 'Whatsapp sablonlari';

    protected static ?string $navigationLabel = 'Whatsapp sablonlari';

    protected static ?string $navigationGroup = 'Ayarlar ve sablonlar';

    protected static ?int $navigationSort = 44;

    protected static ?string $slug = 'sablonlar/whatsapp-sablonlari';

    protected static string $view = 'filament.clusters.teknik-servis.pages.whatsapp-sablonlari-sayfasi';

    public ?int $duzenlenenSablonId = null;

    public bool $duzenleyiciAcik = false;

    /** @var array<string,mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->formuKapat();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Whatsapp sablonlari';
    }

    public function getSubheading(): ?string
    {
        return 'Whatsapp mesaj sablonlarinizi burada olusturup yonetebilirsiniz. Degiskenler: {cihaz}, {bakim_tarihi}.';
    }

    /**
     * @return array<int, TeknikServisMesajSablonu>
     */
    public function sablonlar(): array
    {
        return TeknikServisMesajSablonu::query()
            ->select(['id', 'firma_id', 'kanal', 'kod', 'ad', 'aktif', 'siralama', 'updated_at'])
            ->where('kanal', 'whatsapp')
            ->orderBy('siralama')
            ->orderBy('ad')
            ->limit(100)
            ->get()
            ->all();
    }

    public function yeni(bool $bildirimGonder = true): void
    {
        $this->duzenlenenSablonId = null;
        $this->duzenleyiciAcik = true;
        $this->data = [
            'ad' => 'Termal Macun Bakim',
            'kod' => 'termal_macun_bakim',
            'mesaj' => $this->varsayilanTermalMacunMetni(),
            'siralama' => 10,
            'aktif' => true,
        ];

        if ($bildirimGonder) {
            Notification::make()
                ->title('Yeni sablon hazir.')
                ->success()
                ->send();
        }
    }

    public function duzenle(int $id): void
    {
        $sablon = TeknikServisMesajSablonu::query()
            ->where('kanal', 'whatsapp')
            ->whereKey($id)
            ->firstOrFail();

        $this->duzenlenenSablonId = (int) $sablon->id;
        $this->duzenleyiciAcik = true;
        $this->data = [
            'ad' => (string) $sablon->ad,
            'kod' => (string) $sablon->kod,
            'mesaj' => (string) $sablon->mesaj,
            'siralama' => (int) ($sablon->siralama ?? 10),
            'aktif' => (bool) $sablon->aktif,
        ];
    }

    public function kaydet(): void
    {
        $validated = validator($this->data, [
            'ad' => ['required', 'string', 'max:191'],
            'kod' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'mesaj' => ['required', 'string'],
            'siralama' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'kod.regex' => 'Kod yalnizca kucuk harf, rakam, tire ve alt cizgi icerebilir.',
        ])->validate();

        $kodVar = TeknikServisMesajSablonu::query()
            ->where('kanal', 'whatsapp')
            ->where('kod', (string) $validated['kod'])
            ->when($this->duzenlenenSablonId, fn ($query) => $query->where('id', '!=', (int) $this->duzenlenenSablonId))
            ->exists();

        if ($kodVar) {
            Notification::make()->title('Bu kod zaten kullaniliyor.')->danger()->send();

            return;
        }

        $sablon = TeknikServisMesajSablonu::query()->updateOrCreate(
            [
                'id' => $this->duzenlenenSablonId,
                'firma_id' => $this->aktifFirmaId(),
            ],
            [
                'kanal' => 'whatsapp',
                'kod' => (string) $validated['kod'],
                'ad' => (string) $validated['ad'],
                'mesaj' => (string) $validated['mesaj'],
                'siralama' => (int) ($validated['siralama'] ?? 10),
                'aktif' => (bool) ($validated['aktif'] ?? false),
            ]
        );

        $this->duzenlenenSablonId = (int) $sablon->id;

        Notification::make()
            ->title('Whatsapp sablonu kaydedildi.')
            ->success()
            ->send();
    }

    public function aktifDegistir(int $id): void
    {
        $sablon = TeknikServisMesajSablonu::query()
            ->where('kanal', 'whatsapp')
            ->whereKey($id)
            ->firstOrFail();

        $sablon->forceFill(['aktif' => ! (bool) $sablon->aktif])->save();
    }

    public function sil(int $id): void
    {
        $sablon = TeknikServisMesajSablonu::query()
            ->where('kanal', 'whatsapp')
            ->whereKey($id)
            ->firstOrFail();

        $sablon->delete();

        if ($this->duzenlenenSablonId === $id) {
            $this->formuKapat();
        }

        Notification::make()
            ->title('Whatsapp sablonu silindi.')
            ->success()
            ->send();
    }

    public function formuKapat(): void
    {
        $this->duzenlenenSablonId = null;
        $this->duzenleyiciAcik = false;
        $this->data = [
            'ad' => '',
            'kod' => '',
            'mesaj' => '',
            'siralama' => 10,
            'aktif' => true,
        ];
    }

    private function aktifFirmaId(): int
    {
        $firmaId = (int) app(TenantContextService::class)->aktifFirmaId();

        if ($firmaId > 0) {
            return $firmaId;
        }

        return (int) Firma::query()->orderBy('id')->value('id');
    }

    private function varsayilanTermalMacunMetni(): string
    {
        return implode("\n\n", [
            'Merhaba Sayin Musterimiz,',
            'Cihazinizin guvenli, hizli ve sorunsuz calismasini surdurebilmesi icin termal macun yenileme ve fan temizligi periyodik bakim zamaniniz gelmistir.',
            'Bu bakim, cihaz sagligini dogrudan koruyan kritik bir teknik islemdir. Zamaninda yapilmadiginda isi yonetimi bozulabilir; bu durum performans dususu, ani kapanma, donma ve ilerleyen surecte maliyetli donanim arizalarina yol acabilir. Duzenli bakim uygulandiginda ise sogutma dengesi korunur, sistem kararliligi artar ve parca omru guvence altina alinir.',
            "Bakim bilgisi:\n- Cihaz: {cihaz}\n- Planlanan bakim tarihi: {bakim_tarihi}",
            "Teknik servis surecimiz standart prosedurlerle, kontrollu ve guvenli sekilde yurutulmektedir:\n- Sogutma sistemi detayli olarak temizlenir.\n- Eski termal macun profesyonel yontemle yenilenir.\n- Isi degerleri kontrol edilerek cihaz test edilir.\n- Cihaziniz performans ve stabilite acisindan guvenli durumda teslim edilir.",
            'Amacimiz, ariza olustuktan sonra mudahale etmek degil; ariza riskini onceden ortadan kaldirmaktir. Bu bakim, cihaz performansini korumak ve beklenmedik sorunlari onlemek icin en dogru adimdir.',
            'Uygun oldugunuz tarih ve saat bilgisini paylasmaniz halinde bakim planlamanizi memnuniyetle olusturalim.',
            "Saygilarimizla,\nYalova Bilgisayar Teknik Servis\n0 (226) 352 07 24",
        ]);
    }
}
