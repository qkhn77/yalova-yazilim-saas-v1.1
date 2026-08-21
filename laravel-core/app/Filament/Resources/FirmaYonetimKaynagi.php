<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirmaYonetimKaynagi\Pages;
use App\Filament\Resources\FirmaYonetimKaynagi\RelationManagers;
use App\Models\Firma;
use App\Models\Modul;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\User;
use App\Services\FirmaSilmeServisi;
use App\Support\DenetimYardimcisi;
use App\Support\RolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FirmaYonetimKaynagi extends Resource
{
    protected static ?string $model = Firma::class;

    protected static ?string $slug = 'sistem-firmalar';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Firmalar';
    }

    public static function getModelLabel(): string
    {
        return 'Firma';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Firmalar';
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        return static::getModel()::query()
            ->select([
                'id',
                'ad',
                'kisa_ad',
                'firma_kodu',
                'telefon',
                'eposta',
                'adres',
                'vergi_no',
                'durum',
            ])
            ->whereKey($key)
            ->first();
    }

    /**
     * Policy’deki firma.guncelle kiracıya açık kalır; bu SaaS ekranı yalnızca süper admin içindir.
     */
    protected static function sadeceSistemYoneticisi(): bool
    {
        $kullanici = auth()->user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function canViewAny(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function canView(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Firma bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('ad')
                        ->label('Firma adı')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kisa_ad')
                        ->label('Kısa ad')
                        ->maxLength(120),
                    Forms\Components\TextInput::make('firma_kodu')
                        ->label('Firma kodu')
                        ->maxLength(100)
                        ->helperText('Boş bırakılırsa otomatik üretilir.')
                        ->unique(Firma::class, 'firma_kodu', ignoreRecord: true),
                    Forms\Components\TextInput::make('telefon')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('eposta')
                        ->label('E-posta')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(Firma::class, 'eposta', ignoreRecord: true),
                    Forms\Components\Textarea::make('adres')
                        ->label('Adres')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('vergi_no')
                        ->label('Vergi no')
                        ->maxLength(50),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(Firma::durumSecenekleri())
                        ->required()
                        ->default(Firma::DURUM_BEKLEMEDE)
                        ->native(true),
                ])->columns(2),
            Forms\Components\Section::make('İlk kurulum (opsiyonel)')
                ->visibleOn('create')
                ->schema([
                    Forms\Components\Toggle::make('ilk_kurulum_aktif')
                        ->label('Kullanıcı, rol, plan ve modül bağla')
                        ->default(false)
                        ->visible(fn (): bool => SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi())
                        ->live(),
                    Forms\Components\TextInput::make('ilk_kullanici_eposta')
                        ->label('İlk kullanıcı e-posta')
                        ->email()
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                    Forms\Components\TextInput::make('ilk_kullanici_adi')
                        ->label('İlk kullanıcı adı')
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                    Forms\Components\TextInput::make('ilk_kullanici_sifre')
                        ->label('İlk kullanıcı şifre')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                    Forms\Components\Select::make('ilk_rol_id')
                        ->label('İlk rol')
                        ->options(fn (): array => SaaSemaYardimcisi::rollerTablosuVarMi()
                            ? Rol::query()->orderBy('ad')->get()
                                ->mapWithKeys(fn (Rol $r): array => [$r->id => $r->ad.' ('.$r->kod.')'])
                                ->all()
                            : [])
                        ->default(fn (): ?int => SaaSemaYardimcisi::rollerTablosuVarMi() ? RolYardimcisi::varsayilanFirmaYoneticisiRolId() : null)
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                    Forms\Components\Select::make('ilk_plan_id')
                        ->label('İlk plan')
                        ->options(fn (): array => SaaSemaYardimcisi::planlarTablosuVarMi() ? Plan::query()->where('aktif_mi', true)->orderBy('ad')->pluck('ad', 'id')->all() : [])
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                    Forms\Components\Select::make('ilk_modul_ids')
                        ->label('İlk modüller')
                        ->options(fn (): array => SaaSemaYardimcisi::modullerTablosuVarMi() ? Modul::query()->where('aktif_mi', true)->orderBy('ad')->pluck('ad', 'id')->all() : [])
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Forms\Get $get): bool => (bool) $get('ilk_kurulum_aktif')),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'ad',
                'firma_kodu',
                'telefon',
                'eposta',
                'durum',
                'created_at',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('ad')->label('Firma adı')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('firma_kodu')->label('Firma kodu')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('telefon')->label('Telefon')->searchable(),
                Tables\Columns\TextColumn::make('eposta')->label('E-posta')->searchable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->weight('semibold')
                    ->formatStateUsing(fn (?string $record): string => Firma::durumSecenekleri()[$record] ?? '—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options(Firma::durumSecenekleri()),
                Tables\Filters\Filter::make('created_at')
                    ->label('Oluşturulma')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['baslangic'] ?? null, fn (Builder $query, $data) => $query->whereDate('created_at', '>=', $data))
                            ->when($data['bitis'] ?? null, fn (Builder $query, $data) => $query->whereDate('created_at', '<=', $data));
                    }),
                Tables\Filters\Filter::make('firma_kodu')
                    ->label('Firma kodu')
                    ->form([
                        Forms\Components\TextInput::make('deger')->label('Kod içerir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $d = $data['deger'] ?? null;
                        if (! filled($d)) {
                            return $query;
                        }

                        return $query->where('firma_kodu', 'like', '%'.$d.'%');
                    }),
                Tables\Filters\Filter::make('firma_adi')
                    ->label('Firma adı')
                    ->form([
                        Forms\Components\TextInput::make('deger')->label('Ad içerir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $d = $data['deger'] ?? null;
                        if (! filled($d)) {
                            return $query;
                        }

                        return $query->where('ad', 'like', '%'.$d.'%');
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all'])
            ->actions([
                Tables\Actions\EditAction::make()->label('Düzenle'),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Firmayı sil')
                    ->modalDescription('Bu işlem geri alınamaz. Firmaya ait tüm veriler silinecektir.')
                    ->visible(fn (): bool => static::canDeleteAny())
                    ->action(function (Firma $record): void {
                        $kullanici = auth()->user();
                        if (! $kullanici instanceof User) {
                            return;
                        }

                        $sayaclar = app(FirmaSilmeServisi::class)->sil($record, $kullanici);
                        Notification::make()
                            ->title('Firma silindi')
                            ->body('Silinen kullanıcı: '.($sayaclar['silinen_kullanicilar'] ?? 0).', firma kullanıcı kaydı: '.($sayaclar['firma_kullanicilari'] ?? 0))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('onayla')
                        ->label('Onayla (aktif)')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Firma $record): bool => $record->durum !== Firma::DURUM_AKTIF)
                        ->action(function (Firma $record): void {
                            $record->update([
                                'durum' => Firma::DURUM_AKTIF,
                                'onaylandi_mi' => true,
                                'onay_tarihi' => now(),
                                'onaylayan_kullanici_id' => auth()->id(),
                            ]);
                            DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, [
                                'durum' => Firma::DURUM_AKTIF,
                                'onaylandi_mi' => true,
                            ]);
                        }),
                    Tables\Actions\Action::make('askiya_al')
                        ->label('Askıya al')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Firma $record): bool => $record->durum !== Firma::DURUM_ASKIDA)
                        ->action(function (Firma $record): void {
                            $record->update(['durum' => Firma::DURUM_ASKIDA]);
                            DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_ASKIDA]);
                        }),
                    Tables\Actions\Action::make('iptal_et')
                        ->label('İptal et')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Firma $record): bool => $record->durum !== Firma::DURUM_IPTAL_EDILDI)
                        ->action(function (Firma $record): void {
                            $record->update(['durum' => Firma::DURUM_IPTAL_EDILDI]);
                            DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_IPTAL_EDILDI]);
                        }),
                    Tables\Actions\Action::make('beklemede_yap')
                        ->label('Beklemede yap')
                        ->icon('heroicon-o-clock')
                        ->requiresConfirmation()
                        ->visible(fn (Firma $record): bool => $record->durum !== Firma::DURUM_BEKLEMEDE)
                        ->action(function (Firma $record): void {
                            $record->update(['durum' => Firma::DURUM_BEKLEMEDE]);
                            DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_BEKLEMEDE]);
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('toplu_sil')
                        ->label('Seçilenleri sil')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Seçilen firmaları sil')
                        ->modalDescription('Bu işlem geri alınamaz. Seçili firmalara ait veriler silinecektir.')
                        ->visible(fn (): bool => static::canDeleteAny())
                        ->action(function (EloquentCollection $records): void {
                            $kullanici = auth()->user();
                            if (! $kullanici instanceof User) {
                                return;
                            }

                            $servis = app(FirmaSilmeServisi::class);
                            $basarili = 0;
                            $hataMesajlari = [];
                            foreach ($records as $firma) {
                                if (! $firma instanceof Firma) {
                                    continue;
                                }
                                try {
                                    $servis->sil($firma, $kullanici);
                                    $basarili++;
                                } catch (ValidationException $e) {
                                    $ilk = collect($e->errors())->flatten()->first();
                                    $hataMesajlari[] = ($firma->ad ?? '#'.$firma->getKey()).': '.($ilk ?? $e->getMessage());
                                } catch (\Throwable $e) {
                                    $hataMesajlari[] = ($firma->ad ?? '#'.$firma->getKey()).': '.$e->getMessage();
                                }
                            }

                            if ($basarili > 0) {
                                Notification::make()
                                    ->title($basarili.' firma silindi')
                                    ->body($hataMesajlari !== [] ? 'Bazı kayıtlar atlandı: '.implode(' | ', array_slice($hataMesajlari, 0, 5)).(count($hataMesajlari) > 5 ? '…' : '') : null)
                                    ->success()
                                    ->send();
                            } elseif ($hataMesajlari !== []) {
                                Notification::make()
                                    ->title('Silme tamamlanamadı')
                                    ->body(implode(' | ', array_slice($hataMesajlari, 0, 3)))
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('toplu_onayla_aktif')
                        ->label('Onayla (aktif)')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            $kullaniciId = auth()->id();
                            foreach ($records as $record) {
                                if (! $record instanceof Firma) {
                                    continue;
                                }
                                $record->update([
                                    'durum' => Firma::DURUM_AKTIF,
                                    'onaylandi_mi' => true,
                                    'onay_tarihi' => now(),
                                    'onaylayan_kullanici_id' => $kullaniciId,
                                ]);
                                DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, [
                                    'durum' => Firma::DURUM_AKTIF,
                                    'onaylandi_mi' => true,
                                ]);
                            }
                            Notification::make()->title('Seçilen firmalar aktif yapıldı')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('toplu_askiya_al')
                        ->label('Askıya al')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof Firma) {
                                    continue;
                                }
                                $record->update(['durum' => Firma::DURUM_ASKIDA]);
                                DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_ASKIDA]);
                            }
                            Notification::make()->title('Seçilen firmalar askıya alındı')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('toplu_iptal_et')
                        ->label('İptal et')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof Firma) {
                                    continue;
                                }
                                $record->update(['durum' => Firma::DURUM_IPTAL_EDILDI]);
                                DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_IPTAL_EDILDI]);
                            }
                            Notification::make()->title('Seçilen firmalar iptal edildi')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('toplu_beklemede')
                        ->label('Beklemede yap')
                        ->icon('heroicon-o-clock')
                        ->requiresConfirmation()
                        ->action(function (EloquentCollection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof Firma) {
                                    continue;
                                }
                                $record->update(['durum' => Firma::DURUM_BEKLEMEDE]);
                                DenetimYardimcisi::kaydet('firma_durumu_degisti', Firma::class, (int) $record->id, (int) $record->id, null, ['durum' => Firma::DURUM_BEKLEMEDE]);
                            }
                            Notification::make()->title('Seçilen firmalar beklemede yapıldı')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        if (! SaaSemaYardimcisi::firmalarTablosuVarMi()) {
            return [];
        }

        $iliskiler = [];
        if (SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi()) {
            $iliskiler[] = RelationManagers\KullanicilarlaIliskiYoneticisi::class;
        }
        if (SaaSemaYardimcisi::firmaModulleriTablosuVarMi() && SaaSemaYardimcisi::modullerTablosuVarMi()) {
            $iliskiler[] = RelationManagers\ModullerleIliskiYoneticisi::class;
        }
        if (SaaSemaYardimcisi::firmaAbonelikleriTablosuVarMi() && SaaSemaYardimcisi::planlarTablosuVarMi()) {
            $iliskiler[] = RelationManagers\AboneliklerleIliskiYoneticisi::class;
        }

        return $iliskiler;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\FirmaListesi::route('/'),
            'create' => Pages\FirmaOlustur::route('/create'),
            'edit' => Pages\FirmaDuzenle::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function canEdit(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function canDelete(Model $kayit): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function canDeleteAny(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::firmalarTablosuVarMi();
    }

    public static function detayModu(): bool
    {
        // Firma düzenleme ekranı varsayılan olarak tam içerikle açılır.
        // Sadece ihtiyaç halinde ?hizli=1 ile durum alanına indirgenebilir.
        return ! request()->boolean('hizli');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }
}
