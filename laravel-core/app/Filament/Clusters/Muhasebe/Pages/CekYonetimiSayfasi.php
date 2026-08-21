<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Cek;
use App\Models\Muhasebe\CekHareketi;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\CekDurumu;
use App\Muhasebe\Enumlar\CekHareketDurumu;
use App\Muhasebe\Enumlar\CekTuru;
use App\Muhasebe\Servisler\CekServisi;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CekYonetimiSayfasi extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Çek';

    protected static ?string $slug = 'finans/cek';

    protected static string $view = 'filament.clusters.muhasebe.pages.cek-yonetimi';

    public function getHeading(): string|Htmlable
    {
        return 'Çek Takibi';
    }

    public function getSubheading(): ?string
    {
        return 'Çek giriş ve çıkışları cari finans hareketleriyle bağlantılıdır; kasa ve banka hareketi oluşturulmaz.';
    }

    public function getHeader(): ?View
    {
        return view('filament.clusters.muhasebe.pages.cek-yonetimi-header');
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::FINANS_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    /** @return array<int,array{etiket:string,adet:int,aciklama:string,icon:string}> */
    public function cekOzetleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        $bugun = today()->toDateString();
        $aktifDurumlar = [CekDurumu::Portfoyde->value, CekDurumu::Verildi->value];
        $ozet = Cek::query()
            ->where('firma_id', $firmaId)
            ->selectRaw(
                'COUNT(*) as toplam, '
                .'SUM(CASE WHEN durum = ? THEN 1 ELSE 0 END) as portfoyde, '
                .'SUM(CASE WHEN durum = ? THEN 1 ELSE 0 END) as verilen, '
                .'SUM(CASE WHEN durum IN (?, ?) AND vade_tarihi < ? THEN 1 ELSE 0 END) as vadesi_gecen, '
                .'SUM(CASE WHEN durum IN (?, ?) AND vade_tarihi = ? THEN 1 ELSE 0 END) as bugun',
                [
                    CekDurumu::Portfoyde->value,
                    CekDurumu::Verildi->value,
                    ...$aktifDurumlar,
                    $bugun,
                    ...$aktifDurumlar,
                    $bugun,
                ],
            )
            ->first();

        return [
            ['etiket' => 'Toplam çek', 'adet' => (int) ($ozet?->toplam ?? 0), 'aciklama' => 'Kayıtlı tüm çekler', 'icon' => 'heroicon-m-document-text'],
            ['etiket' => 'Portföyde', 'adet' => (int) ($ozet?->portfoyde ?? 0), 'aciklama' => 'Aktif portföyde bulunanlar', 'icon' => 'heroicon-m-briefcase'],
            ['etiket' => 'Vadesi geçen', 'adet' => (int) ($ozet?->vadesi_gecen ?? 0), 'aciklama' => 'Vadesi geçmiş aktif çekler', 'icon' => 'heroicon-m-exclamation-triangle'],
            ['etiket' => 'Bugün vadeli', 'adet' => (int) ($ozet?->bugun ?? 0), 'aciklama' => 'Bugün vadesi gelenler', 'icon' => 'heroicon-m-calendar-days'],
            ['etiket' => 'Verilen', 'adet' => (int) ($ozet?->verilen ?? 0), 'aciklama' => 'Karşı tarafa verilenler', 'icon' => 'heroicon-m-arrow-up-tray'],
        ];
    }

    /** @return array<int, Actions\Action> */
    protected function getHeaderActions(): array
    {
        $yazmaYetkisi = fn (): bool => MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_OLUSTUR);

        return [
            Actions\Action::make('cekGiris')
                ->label('Çek girişi')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible($yazmaYetkisi)
                ->form(fn (): array => $this->cekGirisFormu())
                ->action(function (array $data): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    app(CekServisi::class)->girisKaydet($firmaId, $data);
                    Notification::make()->title('Çek girişi kaydedildi')->success()->send();
                    $this->resetTable();
                }),
            Actions\Action::make('cekCikisi')
                ->label('Çek çıkışı')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->visible($yazmaYetkisi)
                ->form(fn (): array => $this->cekCikisiFormu())
                ->action(function (array $data): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    app(CekServisi::class)->cikisKaydet($firmaId, $data);
                    Notification::make()->title('Çek çıkışı kaydedildi')->success()->send();
                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Cek::query()->with([
                'girisHareketi.cari:id,ad,kod',
                'cikisHareketi.cari:id,ad,kod',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('turu')
                    ->label('Tür')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof CekTuru ? ($state === CekTuru::Alinan ? 'Alınan' : 'Verilen') : (string) $state)
                    ->color(fn ($state): string => $state === CekTuru::Alinan || $state === CekTuru::Alinan->value ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('cek_no')
                    ->label('Çek no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('girisHareketi.cari.ad')
                    ->label('Giriş carisi')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cikisHareketi.cari.ad')
                    ->label('Çıkış carisi')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('on_gorsel_yolu')
                    ->label('Ön yüz')
                    ->disk('public')
                    ->square()
                    ->size(42)
                    ->url(fn (Cek $record): ?string => $record->on_gorsel_yolu ? Storage::disk('public')->url($record->on_gorsel_yolu) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('arka_gorsel_yolu')
                    ->label('Arka yüz')
                    ->disk('public')
                    ->square()
                    ->size(42)
                    ->url(fn (Cek $record): ?string => $record->arka_gorsel_yolu ? Storage::disk('public')->url($record->arka_gorsel_yolu) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, Cek $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state instanceof CekDurumu ? $state : CekDurumu::tryFrom((string) $state)) {
                        CekDurumu::Portfoyde => 'Portföyde',
                        CekDurumu::Verildi => 'Verildi',
                        CekDurumu::Iptal => 'İptal',
                        default => '—',
                    })
                    ->color(fn ($state): string => match ($state instanceof CekDurumu ? $state : CekDurumu::tryFrom((string) $state)) {
                        CekDurumu::Portfoyde => 'info',
                        CekDurumu::Verildi => 'success',
                        CekDurumu::Iptal => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('turu')
                    ->label('Tür')
                    ->options([
                        CekTuru::Alinan->value => 'Alınan',
                        CekTuru::Verilen->value => 'Verilen',
                    ]),
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        CekDurumu::Portfoyde->value => 'Portföyde',
                        CekDurumu::Verildi->value => 'Verildi',
                        CekDurumu::Iptal->value => 'İptal',
                    ]),
                Tables\Filters\Filter::make('vade_tarihi')
                    ->label('Vade aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('vade_tarihi', '>=', $tarih))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $tarih): Builder => $q->whereDate('vade_tarihi', '<=', $tarih))),
            ])
            ->actions([
                Tables\Actions\Action::make('iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Cek $record): bool => $record->durum !== CekDurumu::Iptal
                        && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_SIL))
                    ->action(function (Cek $record): void {
                        app(CekServisi::class)->iptalEt($record);
                        Notification::make()->title('Çek hareketi ters kayıtla iptal edildi')->success()->send();
                    }),
                Tables\Actions\Action::make('iptal_ve_duzelt')
                    ->label('İptal et ve düzelt')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('tutar')->label('Yeni tutar')->numeric()->required()->minValue(0.01)->step('0.01'),
                        Forms\Components\DateTimePicker::make('islem_tarihi')->label('Yeni işlem tarihi')->native(false)->seconds(false)->required()->default(now()),
                        Forms\Components\Textarea::make('aciklama')->label('Düzeltme açıklaması')->rows(2)->maxLength(500),
                    ])
                    ->visible(fn (Cek $record): bool => $record->durum !== CekDurumu::Iptal
                        && CekHareketi::query()->where('cek_id', $record->getKey())->where('durum', CekHareketDurumu::Aktif->value)->exists()
                        && MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(MuhasebeYetkiSablonlari::FINANS_GUNCELLE))
                    ->action(function (Cek $record, array $data): void {
                        app(CekServisi::class)->hareketIptalEtVeDuzelt($record, $data);
                        Notification::make()->title('Çek hareketi iptal edildi, düzeltilmiş kayıt oluşturuldu')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Çek kaydı yok')
            ->emptyStateDescription('Henüz çek giriş veya çıkış kaydı bulunmuyor.')
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function cekGirisFormu(): array
    {
        return [
            Forms\Components\Select::make('cari_id')
                ->label('Çeki veren cari')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->createOptionForm($this->hizliCariFormu())
                ->createOptionUsing(fn (array $data): int => $this->hizliCariOlustur($data))
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    $cari = Cari::query()->whereKey((int) $state)->first();
                    if ($cari) {
                        $set('para_birimi', strtoupper((string) ($cari->para_birimi ?: 'TRY')));
                    }
                }),
            Forms\Components\TextInput::make('cek_no')->label('Çek no')->required()->maxLength(80),
            Forms\Components\TextInput::make('banka_adi')->label('Banka')->maxLength(160),
            Forms\Components\TextInput::make('sube_adi')->label('Şube')->maxLength(160),
            Forms\Components\TextInput::make('tutar')->label('Tutar')->numeric()->required()->minValue(0.01)->step('0.01'),
            Forms\Components\Select::make('para_birimi')->label('Para birimi')->options(fn (): array => $this->paraBirimiSecenekleri())->required()->default('TRY'),
            Forms\Components\DatePicker::make('keside_tarihi')->label('Keşide tarihi')->native(false),
            Forms\Components\DatePicker::make('vade_tarihi')->label('Vade tarihi')->native(false)->required(),
            Forms\Components\DateTimePicker::make('islem_tarihi')->label('Giriş tarihi')->native(false)->seconds(false)->required()->default(now()),
            Forms\Components\FileUpload::make('on_gorsel_yolu')
                ->label('Çek ön yüz görseli')
                ->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()
                ->disk('public')
                ->directory(fn (): string => 'muhasebe/cekler/'.(int) (app(TenantContextService::class)->aktifFirmaId() ?? 0))
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('arka_gorsel_yolu')
                ->label('Çek arka yüz görseli')
                ->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()
                ->disk('public')
                ->directory(fn (): string => 'muhasebe/cekler/'.(int) (app(TenantContextService::class)->aktifFirmaId() ?? 0))
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int, Forms\Components\Component> */
    private function cekCikisiFormu(): array
    {
        return [
            Forms\Components\Radio::make('kaynak')
                ->label('Çek kaynağı')
                ->options(['kendi' => 'İşletmenin kendi çeki', 'portfoy' => 'Portföydeki alınan çek'])
                ->default('kendi')->required()->inline()->live(),
            Forms\Components\Select::make('cek_id')
                ->label('Portföy çeki')
                ->options(fn (): array => $this->portfoyCekSecenekleri())
                ->searchable()
                ->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'portfoy')
                ->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'portfoy'),
            Forms\Components\Select::make('cari_id')
                ->label('Çekin verildiği cari')
                ->required()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi($value))
                ->createOptionForm($this->hizliCariFormu())
                ->createOptionUsing(fn (array $data): int => $this->hizliCariOlustur($data)),
            Forms\Components\TextInput::make('cek_no')->label('Çek no')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(80),
            Forms\Components\TextInput::make('banka_adi')->label('Banka')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(160),
            Forms\Components\TextInput::make('sube_adi')->label('Şube')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->maxLength(160),
            Forms\Components\TextInput::make('tutar')->label('Tutar')->numeric()->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->minValue(0.01)->step('0.01'),
            Forms\Components\Select::make('para_birimi')->label('Para birimi')->options(fn (): array => $this->paraBirimiSecenekleri())->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->default('TRY'),
            Forms\Components\DatePicker::make('keside_tarihi')->label('Keşide tarihi')->native(false)->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi'),
            Forms\Components\DatePicker::make('vade_tarihi')->label('Vade tarihi')->native(false)->required(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi'),
            Forms\Components\DateTimePicker::make('islem_tarihi')->label('Çıkış tarihi')->native(false)->seconds(false)->required()->default(now()),
            Forms\Components\FileUpload::make('on_gorsel_yolu')
                ->label('Çek ön yüz görseli')
                ->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()
                ->disk('public')
                ->directory(fn (): string => 'muhasebe/cekler/'.(int) (app(TenantContextService::class)->aktifFirmaId() ?? 0))
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('arka_gorsel_yolu')
                ->label('Çek arka yüz görseli')
                ->helperText('İsteğe bağlı. JPG, PNG veya WebP; en fazla 5 MB.')
                ->image()
                ->disk('public')
                ->directory(fn (): string => 'muhasebe/cekler/'.(int) (app(TenantContextService::class)->aktifFirmaId() ?? 0))
                ->visibility('public')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->imagePreviewHeight('150')
                ->visible(fn (Get $get): bool => ($get('kaynak') ?? 'kendi') === 'kendi')
                ->columnSpanFull(),
            Forms\Components\Textarea::make('aciklama')->label('Açıklama')->rows(2)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @return array<int|string, string> */
    private function paraBirimiSecenekleri(): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return ['TRY' => 'TRY'];
        }

        $secenekler = ParaBirimi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get(['kod', 'ad'])
            ->mapWithKeys(fn (ParaBirimi $para): array => [strtoupper((string) $para->kod) => strtoupper((string) $para->kod).($para->ad ? ' — '.$para->ad : '')])
            ->all();

        return $secenekler !== [] ? $secenekler : ['TRY' => 'TRY'];
    }

    /** @return array<int, Forms\Components\Component> */
    private function hizliCariFormu(): array
    {
        return [
            Forms\Components\TextInput::make('ad')
                ->label('Ad / ünvan')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('tur')
                ->label('Cari türü')
                ->options(collect(CariTuru::cases())->mapWithKeys(fn (CariTuru $tur): array => [$tur->value => $tur->etiket()])->all())
                ->required()
                ->default(CariTuru::Musteri->value),
            Forms\Components\Select::make('para_birimi')
                ->label('Para birimi')
                ->options(fn (): array => $this->paraBirimiSecenekleri())
                ->required()
                ->default('TRY'),
        ];
    }

    private function hizliCariOlustur(array $data): int
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            throw ValidationException::withMessages(['cari_id' => 'Aktif firma bulunamadı.']);
        }

        $ad = trim((string) ($data['ad'] ?? ''));
        if ($ad === '') {
            throw ValidationException::withMessages(['ad' => 'Ad / ünvan zorunludur.']);
        }

        $tur = CariTuru::tryFrom((string) ($data['tur'] ?? ''));
        if (! $tur) {
            throw ValidationException::withMessages(['tur' => 'Geçerli bir cari türü seçin.']);
        }

        $paraBirimi = strtoupper(trim((string) ($data['para_birimi'] ?? '')));
        if ($paraBirimi === '' || ! array_key_exists($paraBirimi, $this->paraBirimiSecenekleri())) {
            throw ValidationException::withMessages(['para_birimi' => 'Geçerli bir para birimi seçin.']);
        }

        $cari = new Cari();
        $cari->firma_id = $firmaId;
        $cari->ad = $ad;
        $cari->tur = $tur;
        $cari->para_birimi = $paraBirimi;
        $cari->durum = CariDurumu::Aktif;
        // Cari::creating mevcut firma içi kod üretimini otomatik olarak çalıştırır.
        $cari->save();

        return (int) $cari->getKey();
    }

    /** @return array<int, string> */
    private function cariAramaSonuclari(string $arama): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }
        $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($arama)).'%';

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->where('durum', CariDurumu::Aktif)
            ->where(fn (Builder $q): Builder => $q->where('ad', 'like', $aranan)->orWhere('kod', 'like', $aranan))
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $cari): array => [$cari->id => ($cari->kod ? $cari->kod.' — ' : '').$cari->ad])
            ->all();
    }

    private function cariEtiketi(mixed $id): ?string
    {
        $cari = Cari::query()->whereKey((int) $id)->first(['id', 'ad', 'kod']);

        return $cari ? (($cari->kod ? $cari->kod.' — ' : '').$cari->ad) : null;
    }

    /** @return array<int|string, string> */
    private function portfoyCekSecenekleri(): array
    {
        return Cek::query()
            ->where('turu', CekTuru::Alinan->value)
            ->where('durum', CekDurumu::Portfoyde->value)
            ->whereDoesntHave('cikisHareketi', fn (Builder $query): Builder => $query->where('durum', CekHareketDurumu::Aktif->value))
            ->orderBy('vade_tarihi')
            ->limit(100)
            ->get(['id', 'cek_no', 'tutar', 'para_birimi', 'vade_tarihi'])
            ->mapWithKeys(fn (Cek $cek): array => [$cek->id => $cek->cek_no.' — '.number_format((float) $cek->tutar, 2, ',', '.').' '.strtoupper((string) $cek->para_birimi).' — '.optional($cek->vade_tarihi)->format('d.m.Y')])
            ->all();
    }
}
