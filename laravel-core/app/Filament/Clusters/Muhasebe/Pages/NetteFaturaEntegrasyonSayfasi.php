<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Models\Muhasebe\NetteFaturaGelenBelge;
use App\Models\Muhasebe\NetteFaturaGonderimi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Services\EBelgeHazirlikKontrolServisi;
use App\Services\NetteFaturaAyarServisi;
use App\Services\NetteFaturaIstemcisi;
use App\Services\NetteFaturaMobilApiIstemcisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class NetteFaturaEntegrasyonSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'NetteFatura Entegrasyonu';

    protected static ?string $slug = 'entegrasyonlar/nette-fatura';

    protected static string $view = 'filament.clusters.muhasebe.pages.nette-fatura-entegrasyon-sayfasi';

    /** @var array<string,mixed> */
    public array $data = [];

    public function getHeading(): string|Htmlable
    {
        return 'NetteFatura Entegrasyonu';
    }

    public function getSubheading(): ?string
    {
        return 'E-fatura/e-arşiv servis bağlantısı, kimlik bilgileri ve gönderim logları.';
    }

    public static function canAccess(): bool
    {
        return MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FATURA_GORUNTULE);
    }

    public function mount(): void
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $this->form->fill(app(NetteFaturaAyarServisi::class)->ayarlariGetir($firmaId));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Servis bağlantısı')
                    ->schema([
                        Forms\Components\Toggle::make('nette_fatura_aktif_mi')
                            ->label('Entegrasyon aktif')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Toggle::make('nette_fatura_test_modu')
                            ->label('Test modu')
                            ->helperText('Canlı gönderime geçmeden önce açık tutulmalı.')
                            ->default(true)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_service_url')
                            ->label('Servis URL')
                            ->url()
                            ->maxLength(512)
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_wsdl_url')
                            ->label('WSDL URL')
                            ->url()
                            ->maxLength(512)
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_mobile_api_url')
                            ->label('Mobil API URL')
                            ->url()
                            ->maxLength(512)
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_company_id')
                            ->label('NetteFatura CompanyId')
                            ->numeric()
                            ->helperText('Boş/0 ise API girişinden dönen ilk firma kullanılmaya çalışılır.')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_zaman_asimi_saniye')
                            ->label('Zaman aşımı (sn)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(120)
                            ->default(20)
                            ->required()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Kimlik bilgileri')
                    ->schema([
                        Forms\Components\TextInput::make('nette_fatura_kullanici_adi')
                            ->label('Kullanıcı adı')
                            ->maxLength(255)
                            ->autocomplete(false)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_sifre')
                            ->label('Şifre')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->autocomplete(false)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Hazırlık kontrolü')
                    ->schema([
                        Forms\Components\TextInput::make('nette_fatura_gonderici_unvan')
                            ->label('Gönderici unvan')
                            ->maxLength(255)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_vergi_no')
                            ->label('Gönderici vergi/T.C. no')
                            ->maxLength(32)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_vergi_dairesi')
                            ->label('Gönderici vergi dairesi')
                            ->maxLength(128)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_etiket')
                            ->label('GB etiketi')
                            ->maxLength(255)
                            ->helperText('NetteFatura tarafında özel gönderici birim/etiket istenirse doldurun.')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Textarea::make('nette_fatura_gonderici_adres')
                            ->label('Gönderici adres')
                            ->rows(2)
                            ->columnSpanFull()
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_il')
                            ->label('Gönderici il')
                            ->maxLength(64)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_ilce')
                            ->label('Gönderici ilçe')
                            ->maxLength(64)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_ulke')
                            ->label('Gönderici ülke')
                            ->maxLength(64)
                            ->default('Türkiye')
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_eposta')
                            ->label('Gönderici e-posta')
                            ->email()
                            ->maxLength(191)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\TextInput::make('nette_fatura_gonderici_telefon')
                            ->label('Gönderici telefon')
                            ->tel()
                            ->maxLength(64)
                            ->disabled(fn (): bool => ! $this->ayarGuncelleYetkisiVarMi()),
                        Forms\Components\Placeholder::make('firma_e_belge_uyarilari')
                            ->label('')
                            ->dehydrated(false)
                            ->content(fn (): HtmlString => $this->firmaUyariHtml())
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('hazirlik')
                            ->content(new HtmlString(implode('<br>', [
                                '1) PHP soap eklentisi zorunlu değil; bağlantı ve gönderimler curl/XML katmanından yürütülebilir.',
                                '2) Fatura tutarları içeride 8 basamak tutulur; e-belge XML üretiminde gösterim kuralları ayrıca uygulanır.',
                                '3) Gönderimden önce firma/cari vergi bilgileri ve adres alanları tamamlanmalıdır.',
                                '4) Her servis çağrısı nette_fatura_gonderimleri tablosuna loglanır.',
                            ]))),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                NetteFaturaGonderimi::query()
                    ->when($this->aktifFirmaId(), fn (Builder $q, int $firmaId): Builder => $q->where('firma_id', $firmaId))
                    ->select([
                        'id',
                        'firma_id',
                        'fatura_id',
                        'islem_tipi',
                        'durum',
                        'endpoint',
                        'response_message',
                        'error_message',
                        'created_at',
                    ])
                    ->with('fatura:id,fatura_no,belge_no')
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('islem_tipi')->label('İşlem')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'basarili' => 'success',
                        'gonderiliyor', 'hazirlandi' => 'info',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('fatura.fatura_no')->label('Fatura')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('endpoint')->label('Endpoint')->limit(52)->tooltip(fn (?string $state): ?string => $state)->toggleable(),
                Tables\Columns\TextColumn::make('response_message')->label('Yanıt')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('error_message')->label('Hata')->limit(60)->wrap()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function kaydet(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        app(NetteFaturaAyarServisi::class)->kaydetAyarlar($firmaId, $this->form->getState());

        Notification::make()
            ->title('NetteFatura ayarları kaydedildi')
            ->success()
            ->send();
    }

    public function baglantiTesti(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $this->kaydet();
        $sonuc = app(NetteFaturaIstemcisi::class)->baglantiTesti($firmaId);

        Notification::make()
            ->title($sonuc['basarili'] ? 'Bağlantı başarılı' : 'Bağlantı başarısız')
            ->body(trim($sonuc['mesaj'].' '.($sonuc['sure_ms'] !== null ? '('.$sonuc['sure_ms'].' ms)' : '')))
            ->color($sonuc['basarili'] ? 'success' : 'danger')
            ->send();

        $this->resetTable();
    }

    public function ayarGuncelleYetkisiVarMi(): bool
    {
        return MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FATURA_GUNCELLE);
    }

    public function gelenBelgeleriCek(): void
    {
        if (! $this->ayarGuncelleYetkisiVarMi()) {
            abort(403);
        }

        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            abort(403);
        }

        $this->kaydet();

        try {
            $sonuc = app(NetteFaturaMobilApiIstemcisi::class)->gelenBelgeleriSenkronizeEt($firmaId);

            Notification::make()
                ->title('Gelen e-belgeler çekildi')
                ->body('Toplam '.$sonuc['toplam'].' belge. E-Fatura: '.$sonuc['e_fatura'].', E-Arşiv: '.$sonuc['e_arsiv'].'. CompanyId: '.$sonuc['company_id'].'.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gelen e-belgeler çekilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->resetTable();
    }

    private function aktifFirmaId(): ?int
    {
        return app(TenantContextService::class)->aktifFirmaId();
    }

    private function firmaUyariHtml(): HtmlString
    {
        $uyarilar = app(EBelgeHazirlikKontrolServisi::class)->firmaUyarilari($this->data);
        if ($uyarilar === []) {
            return new HtmlString('<div class="text-sm text-success-600 dark:text-success-400">Gönderici bilgileri e-belge için hazır görünüyor.</div>');
        }

        $liste = collect($uyarilar)
            ->map(fn (string $uyari): string => '<li>'.e($uyari).'</li>')
            ->implode('');

        return new HtmlString('<div class="rounded-md border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300"><div class="font-medium">E-belge gönderimi öncesi tamamlanması önerilen firma alanları:</div><ul class="mt-1 list-disc space-y-1 ps-5">'.$liste.'</ul></div>');
    }
    public function gelenBelgelerHtml(): HtmlString
    {
        $firmaId = $this->aktifFirmaId();
        if (! $firmaId) {
            return new HtmlString('');
        }

        $sonMobilLog = NetteFaturaGonderimi::query()
            ->where('firma_id', $firmaId)
            ->whereIn('islem_tipi', ['mobileLogin', 'GetIncomingEInvoiceList', 'GetEArchiveInvoiceList'])
            ->latest('id')
            ->first(['id', 'islem_tipi', 'durum', 'response_message', 'error_message', 'response_meta', 'created_at']);

        $durumHtml = '';
        if ($sonMobilLog) {
            $meta = is_array($sonMobilLog->response_meta) ? $sonMobilLog->response_meta : [];
            $http = $meta['http_kodu'] ?? null;
            $renk = $sonMobilLog->durum === 'basarili'
                ? 'border-success-200 bg-success-50 text-success-700 dark:border-success-800 dark:bg-success-950/40 dark:text-success-300'
                : 'border-danger-200 bg-danger-50 text-danger-700 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300';
            $mesaj = trim((string) ($sonMobilLog->error_message ?: $sonMobilLog->response_message ?: '-'));
            $durumHtml = '<div class="mb-3 rounded-md border px-4 py-3 text-sm '.$renk.'">'
                .'<div class="font-medium">Son gelen e-belge senkron durumu: '.e((string) $sonMobilLog->durum).'</div>'
                .'<div class="mt-1">İşlem: '.e((string) $sonMobilLog->islem_tipi).($http ? ' · HTTP '.e((string) $http) : '').'</div>'
                .'<div class="mt-1">'.e($mesaj).'</div>'
                .'</div>';
        }

        $belgeler = NetteFaturaGelenBelge::query()
            ->where('firma_id', $firmaId)
            ->latest('invoice_date')
            ->latest('id')
            ->limit(15)
            ->get();

        if ($belgeler->isEmpty()) {
            return new HtmlString($durumHtml.'<div class="rounded-md border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">Henüz çekilmiş gelen e-belge yok.</div>');
        }

        $rows = $belgeler->map(function (NetteFaturaGelenBelge $belge): string {
            $tur = $belge->belge_turu === 'e_arsiv' ? 'E-Arşiv' : 'E-Fatura';
            $tarih = $belge->invoice_date?->format('d.m.Y') ?? '-';
            $tutar = number_format((float) $belge->total_amount, 2, ',', '.').' '.(string) $belge->currency_code;

            return '<tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">'
                .'<td class="px-3 py-2 font-medium">'.e($tur).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($belge->invoice_number ?? '-')).'</td>'
                .'<td class="px-3 py-2">'.e($tarih).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($belge->company_name ?? '-')).'</td>'
                .'<td class="px-3 py-2 text-right">'.e($tutar).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($belge->status ?? '-')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($belge->report_status ?? '-')).'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString($durumHtml.'<div class="overflow-hidden rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">'
            .'<div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">Son çekilen gelen e-belgeler</div>'
            .'<div class="overflow-x-auto"><table class="min-w-full text-left text-sm">'
            .'<thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-950 dark:text-gray-400"><tr>'
            .'<th class="px-3 py-2">Tür</th><th class="px-3 py-2">No</th><th class="px-3 py-2">Tarih</th><th class="px-3 py-2">Firma</th><th class="px-3 py-2 text-right">Tutar</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">ReportStatus</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div></div>');
    }
}
