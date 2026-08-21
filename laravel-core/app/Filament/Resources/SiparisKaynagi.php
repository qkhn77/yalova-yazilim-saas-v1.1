<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiparisKaynagi\Pages;
use App\Filament\Support\HasTenantVisibility;
use App\Models\Ecommerce\EcommerceKargoYontemi;
use App\Models\Ecommerce\Odeme;
use App\Models\Ecommerce\Siparis;
use App\Modules\Urun\Servisler\SiparisDurumGecisServisi;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Support\EcommerceOdemeTanimlari;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SiparisKaynagi extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = Siparis::class;

    protected static ?string $slug = 'siparisler';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Web';

    protected static ?string $navigationLabel = 'Siparişler';

    protected static ?string $modelLabel = 'Sipariş';

    protected static ?string $pluralModelLabel = 'Siparişler';

    protected static ?string $modulKodu = 'urunler';

    protected static ?string $goruntuleYetkiKodu = 'urun.goruntule';

    protected static ?string $guncelleYetkiKodu = 'urun.guncelle';

    public static function getEloquentQuery(): Builder
    {
        // Liste için yalnızca son ödeme bilgisi gerekli; kalemler/odemeler eager-load etmek performans riskidir.
        $q = parent::getEloquentQuery()->with(['sonOdeme']);

        $fid = app(TenantContextService::class)->aktifFirmaId();
        if ($fid) {
            $q->where('firma_id', $fid);
        }

        return $q;
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (static::hizliDuzenlemeModu()) {
            $q = Siparis::query()
                ->select([
                    'id',
                    'firma_id',
                    'durum',
                    'iptal_nedeni',
                    'kargo_firmasi',
                    'takip_no',
                    'operasyon_notu',
                    'stok_dusuldu_mi',
                ])
                ->whereKey($key);

            $fid = app(TenantContextService::class)->aktifFirmaId();
            if ($fid) {
                $q->where('firma_id', $fid);
            }

            return $q->first();
        }

        if (static::hizliGorunumModu()) {
            $q = Siparis::query()
                ->select([
                    'id',
                    'firma_id',
                    'siparis_no',
                    'musteri_ad_soyad',
                    'durum',
                    'para_birimi',
                    'genel_toplam',
                    'created_at',
                ])
                ->whereKey($key);

            $fid = app(TenantContextService::class)->aktifFirmaId();
            if ($fid) {
                $q->where('firma_id', $fid);
            }

            return $q->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        if (static::hizliDuzenlemeModu()) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options(function (?Siparis $record): array {
                        if (! $record instanceof Siparis) {
                            return Siparis::durumEtiketleri();
                        }

                        return app(SiparisDurumGecisServisi::class)->durumSecimOpsiyonlari((string) $record->durum);
                    })
                    ->required()
                    ->disabled(fn (?Siparis $record): bool => Siparis::iptalEdildiDurumMu($record?->durum))
                    ->native(),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Durum')
                ->schema([
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(function (?Siparis $record): array {
                            if (! $record instanceof Siparis) {
                                return Siparis::durumEtiketleri();
                            }

                            return app(SiparisDurumGecisServisi::class)->durumSecimOpsiyonlari((string) $record->durum);
                        })
                        ->required()
                        ->disabled(fn (?Siparis $record): bool => Siparis::iptalEdildiDurumMu($record?->durum)),
                    Forms\Components\Textarea::make('iptal_nedeni')
                        ->label('İptal nedeni')
                        ->rows(2)
                        ->visible(fn (Forms\Get $get, ?Siparis $record): bool => in_array((string) $get('durum'), [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL], true)
                            || Siparis::iptalEdildiDurumMu($record?->durum))
                        ->dehydrated(fn (Forms\Get $get, ?Siparis $record): bool => in_array((string) $get('durum'), [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL], true)
                            || Siparis::iptalEdildiDurumMu($record?->durum)),
                ]),
            Forms\Components\Section::make('Ödeme bilgisi')
                ->schema([
                    Forms\Components\Placeholder::make('odeme_yontemi_ad_gosterim')
                        ->label('Ödeme yöntemi')
                        ->content(fn (?Siparis $record): string => (string) ($record?->odeme_yontemi_ad ?: '—')),
                    Forms\Components\Placeholder::make('odeme_provider_gosterim')
                        ->label('Provider')
                        ->content(fn (?Siparis $record): string => EcommerceOdemeTanimlari::saglayicilar()[$record?->odeme_provider ?? ''] ?? (string) ($record?->odeme_provider ?: '—')),
                    Forms\Components\Placeholder::make('havale_banka_adi_gosterim')
                        ->label('Banka')
                        ->content(fn (?Siparis $record): string => (string) ($record?->havale_banka_adi ?: '—'))
                        ->visible(fn (?Siparis $record): bool => ($record?->odeme_provider === Odeme::PROVIDER_HAVALE_EFT)
                            || ($record?->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR)),
                    Forms\Components\Placeholder::make('havale_hesap_sahibi_gosterim')
                        ->label('Hesap sahibi / şirket')
                        ->content(fn (?Siparis $record): string => (string) ($record?->havale_hesap_sahibi ?: '—'))
                        ->visible(fn (?Siparis $record): bool => ($record?->odeme_provider === Odeme::PROVIDER_HAVALE_EFT)
                            || ($record?->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR)),
                    Forms\Components\Placeholder::make('havale_iban_gosterim')
                        ->label('IBAN')
                        ->content(fn (?Siparis $record): string => (string) ($record?->havale_iban ?: '—'))
                        ->visible(fn (?Siparis $record): bool => ($record?->odeme_provider === Odeme::PROVIDER_HAVALE_EFT)
                            || ($record?->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR)),
                    Forms\Components\Placeholder::make('havale_aciklama_notu_gosterim')
                        ->label('Ödeme notu / açıklama')
                        ->content(fn (?Siparis $record): string => (string) ($record?->havale_aciklama_notu ?: '—'))
                        ->columnSpanFull()
                        ->visible(fn (?Siparis $record): bool => ($record?->odeme_provider === Odeme::PROVIDER_HAVALE_EFT)
                            || ($record?->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR)),
                ])
                ->columns(2),
            Forms\Components\Section::make('Kargo / teslimat')
                ->schema([
                    Forms\Components\Select::make('kargo_yontemi_id')
                        ->label('Kargo yöntemi')
                        ->options(fn (?Siparis $record): array => EcommerceKargoYontemi::query()
                            ->where('firma_id', (int) ($record?->firma_id ?: app(TenantContextService::class)->aktifFirmaId()))
                            ->orderBy('sira')
                            ->orderBy('ad')
                            ->pluck('ad', 'id')
                            ->all())
                        ->searchable(),
                    Forms\Components\TextInput::make('kargo_firmasi')->label('Kargo firması')->maxLength(120),
                    Forms\Components\TextInput::make('kargo_ucreti')->label('Kargo ücreti')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('takip_no')->label('Takip no')->maxLength(120),
                    Forms\Components\DatePicker::make('kargo_tarihi')->label('Kargo tarihi'),
                    Forms\Components\DatePicker::make('teslim_tarihi')->label('Teslim tarihi'),
                    Forms\Components\TextInput::make('teslimat_ulke')->label('Ülke')->maxLength(2),
                    Forms\Components\TextInput::make('teslimat_il')->label('İl / eyalet')->maxLength(120),
                    Forms\Components\TextInput::make('teslimat_ilce')->label('İlçe / bölge')->maxLength(120),
                    Forms\Components\TextInput::make('teslimat_posta_kodu')->label('Posta kodu')->maxLength(20),
                ])
                ->columns(3),
            Forms\Components\Section::make('Notlar')
                ->schema([
                    Forms\Components\Textarea::make('musteri_notu')->label('Müşteri notu')->rows(2),
                    Forms\Components\Textarea::make('operasyon_notu')->label('Operasyon notu')->rows(2),
                    Forms\Components\Textarea::make('ic_not')->label('İç not')->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siparis_no')
                    ->label('Sipariş no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('musteri_ad_soyad')
                    ->label('Müşteri')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('musteri_telefon')
                    ->label('Telefon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('musteri_email')
                    ->label('E-posta')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('genel_toplam')
                    ->label('Toplam')
                    ->money(fn (Siparis $r): string => $r->para_birimi ?: 'TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('odeme_yontemi_ad')
                    ->label('Ödeme yöntemi')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kargo_firmasi')
                    ->label('Kargo')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('operasyon_ozeti')
                    ->label('Operasyon')
                    ->badge()
                    ->getStateUsing(fn (Siparis $r): string => static::operasyonOzeti($r))
                    ->color(fn (Siparis $r): string => static::operasyonRengi($r))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('muhasebe_entegrasyon_durumu')
                    ->label('Muhasebe')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'tamamlandi' => 'Aktarıldı',
                        'hata' => 'Hata',
                        'bekliyor' => 'Bekliyor',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'tamamlandi' => 'success',
                        'hata' => 'danger',
                        'bekliyor' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para birimi')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (?string $state): string => Siparis::durumRengi($state))
                    ->formatStateUsing(fn (?string $state): string => Siparis::durumEtiketleri()[$state ?? ''] ?? ($state ?? '—'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('son_odeme_durumu')
                    ->label('Ödeme / son ödeme')
                    ->getStateUsing(function (Siparis $r): string {
                        $o = $r->relationLoaded('sonOdeme') ? $r->sonOdeme : $r->odemeler()->orderByDesc('id')->first();
                        if (! $o) {
                            return '—';
                        }

                        return Odeme::durumEtiketleri()[$o->durum] ?? $o->durum;
                    }),
                Tables\Columns\IconColumn::make('stok_dusuldu_mi')
                    ->label('Stok düşüldü')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(Siparis::durumEtiketleri())
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if (! is_string($v) || $v === '') {
                            return $query;
                        }

                        if ($v === Siparis::DURUM_ONAY_BEKLIYOR) {
                            return $query->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR]);
                        }

                        if ($v === Siparis::DURUM_ONAYLANDI_YENI) {
                            return $query->whereIn('durum', [Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR, Siparis::DURUM_BEKLEMEDE]);
                        }

                        if ($v === Siparis::DURUM_GONDERILDI) {
                            return $query->whereIn('durum', [Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI]);
                        }

                        if ($v === Siparis::DURUM_TESLIM_EDILDI) {
                            return $query->whereIn('durum', [Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI]);
                        }

                        if ($v === Siparis::DURUM_IPTAL_EDILDI) {
                            return $query->whereIn('durum', [Siparis::DURUM_IPTAL_EDILDI, Siparis::DURUM_IPTAL]);
                        }

                        return $query->where('durum', $v);
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('basla')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['basla'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['bitis'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
                Tables\Filters\SelectFilter::make('son_odeme_durumu')
                    ->label('Ödeme durumu')
                    ->options(Odeme::durumEtiketleri())
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if ($v === null || $v === '') {
                            return $query;
                        }

                        return $query->whereHas(
                            'sonOdeme',
                            fn (Builder $oq) => $oq->where('durum', $v),
                        );
                    }),
                Tables\Filters\SelectFilter::make('operasyon')
                    ->label('Operasyon filtresi')
                    ->options([
                        'onay_bekleyen' => 'Onay bekleyen',
                        'eft_onayi_bekleyen' => 'EFT onayı bekleyen',
                        'kargoya_hazir' => 'Kargoya hazır',
                        'takip_no_eksik' => 'Takip no eksik',
                        'kargo_gecikmis' => 'Kargo gecikmiş',
                        'teslim_edildi' => 'Teslim edildi',
                        'iptal_iade_talebi' => 'İptal / iade talebi',
                        'muhasebe_bekleyen' => 'Muhasebe aktarımı bekleyen',
                        'muhasebe_hata' => 'Muhasebe aktarım hatası',
                        'not_var' => 'Operasyon notu olan',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if (! is_string($v) || $v === '') {
                            return $query;
                        }

                        return match ($v) {
                            'onay_bekleyen' => $query->whereIn('durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR]),
                            'eft_onayi_bekleyen' => $query->where('durum', Siparis::DURUM_EFT_ONAYI_BEKLIYOR),
                            'kargoya_hazir' => $query
                                ->whereIn('durum', [Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR, Siparis::DURUM_BEKLEMEDE])
                                ->where(function (Builder $q): void {
                                    $q->whereNull('takip_no')->orWhere('takip_no', '');
                                }),
                            'takip_no_eksik' => $query
                                ->whereIn('durum', [Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI])
                                ->where(function (Builder $q): void {
                                    $q->whereNull('takip_no')->orWhere('takip_no', '');
                                }),
                            'kargo_gecikmis' => $query
                                ->whereIn('durum', [Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR, Siparis::DURUM_BEKLEMEDE])
                                ->where('created_at', '<=', now()->subDays(2)),
                            'teslim_edildi' => $query->whereIn('durum', [Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI]),
                            'iptal_iade_talebi' => $query->whereIn('durum', [Siparis::DURUM_IPTAL_TALEBI, Siparis::DURUM_IADE_TALEBI]),
                            'muhasebe_bekleyen' => $query
                                ->whereIn('durum', [Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR, Siparis::DURUM_BEKLEMEDE, Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI, Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI])
                                ->where(function (Builder $q): void {
                                    $q->whereNull('proforma_fatura_id')
                                        ->orWhereNull('tahsilat_finans_hareketi_id')
                                        ->orWhereNull('muhasebe_entegrasyon_durumu')
                                        ->orWhere('muhasebe_entegrasyon_durumu', '!=', 'tamamlandi');
                                }),
                            'muhasebe_hata' => $query->where('muhasebe_entegrasyon_durumu', 'hata'),
                            'not_var' => $query->where(function (Builder $q): void {
                                $q->whereNotNull('operasyon_notu')->where('operasyon_notu', '!=', '');
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->label('Düzenle'),
                Tables\Actions\Action::make('eft_onayla')
                    ->label('EFT Onayla')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Siparis $r): bool => $r->durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR)
                    ->requiresConfirmation()
                    ->modalHeading('EFT ödemesini onayla')
                    ->modalDescription('Bu işlem siparişi onaylar, ödeme kaydını başarılıya çeker ve sipariş sürecini başlatır.')
                    ->action(function (Siparis $r) {
                        try {
                            app(SiparisOdemeServisi::class)->adminManuelOdemeOnayla($r);
                            Notification::make()->title('EFT ödemesi onaylandı')->success()->send();

                            return redirect()->to(static::getUrl('edit', ['record' => $r->fresh()]));
                        } catch (ValidationException $e) {
                            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                            Notification::make()->title((string) $msg)->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('durum_degistir')
                    ->label('Durum değiştir')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn (Siparis $r): string => static::getUrl('edit', ['record' => $r])),
                Tables\Actions\Action::make('siparis_iptal')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Siparis $r): bool => ! Siparis::iptalEdildiDurumMu($r->durum)
                        && ! Siparis::teslimEdildiDurumMu($r->durum))
                    ->form([
                        Forms\Components\Textarea::make('iptal_nedeni')
                            ->label('İptal nedeni')
                            ->rows(3),
                    ])
                    ->action(function (Siparis $r, array $data) {
                        try {
                            app(SiparisOdemeServisi::class)
                                ->siparisIptalEt($r, $data['iptal_nedeni'] ?? null);
                            Notification::make()->title('Sipariş iptal edildi')->success()->send();

                            // Operatörün "iptal oldu mu?" sorusunu azaltmak için detay ekranına yönlendiriyoruz.
                            return redirect()->to(static::getUrl('view', ['record' => $r]));
                        } catch (ValidationException $e) {
                            $msg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                            Notification::make()->title((string) $msg)->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    private static function operasyonOzeti(Siparis $siparis): string
    {
        $durum = (string) $siparis->durum;
        $takipNo = trim((string) ($siparis->takip_no ?? ''));

        if (in_array($durum, [Siparis::DURUM_IPTAL_TALEBI, Siparis::DURUM_IADE_TALEBI], true)) {
            return 'Talep var';
        }

        if (in_array($durum, [Siparis::DURUM_GONDERILDI, Siparis::DURUM_KARGOLANDI], true) && $takipNo === '') {
            return 'Takip no eksik';
        }

        if (in_array($durum, [Siparis::DURUM_ONAYLANDI_YENI, Siparis::DURUM_ODENDI, Siparis::DURUM_HAZIRLANIYOR, Siparis::DURUM_BEKLEMEDE], true)) {
            if ($siparis->created_at && $siparis->created_at->lte(now()->subDays(2))) {
                return 'Kargo gecikmiş';
            }

            return 'Kargoya hazır';
        }

        if ($durum === Siparis::DURUM_EFT_ONAYI_BEKLIYOR) {
            return 'EFT onayı';
        }

        if (in_array($durum, [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR], true)) {
            return 'Onay bekliyor';
        }

        if (in_array($durum, [Siparis::DURUM_TESLIM_EDILDI, Siparis::DURUM_TAMAMLANDI], true)) {
            return 'Tamamlandı';
        }

        return 'Normal';
    }

    private static function operasyonRengi(Siparis $siparis): string
    {
        return match (static::operasyonOzeti($siparis)) {
            'Talep var', 'Takip no eksik', 'Kargo gecikmiş' => 'danger',
            'EFT onayı', 'Onay bekliyor', 'Kargoya hazır' => 'warning',
            'Tamamlandı' => 'success',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiparisler::route('/'),
            'failed' => Pages\ListBasarisizSiparisler::route('/basarisiz'),
            'view' => Pages\ViewSiparis::route('/{record}'),
            'edit' => Pages\EditSiparis::route('/{record}/duzenle'),
        ];
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.edit') && ! static::detayModu();
    }

    public static function hizliGorunumModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');

        return str_ends_with($routeName, '.view') && ! static::detayModu();
    }

    public static function basarisizOdemeSiparisSorgusuUygula(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('durum', Siparis::DURUM_BASARISIZ_ODEME)
                ->orWhereHas('sonOdeme', fn (Builder $odeme) => $odeme->where('durum', Odeme::DURUM_BASARISIZ));
        });
    }

    public static function basarisizOdemeDisiSiparisSorgusuUygula(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('durum', '!=', Siparis::DURUM_BASARISIZ_ODEME)
                ->whereDoesntHave('sonOdeme', fn (Builder $odeme) => $odeme->where('durum', Odeme::DURUM_BASARISIZ));
        });
    }
}
