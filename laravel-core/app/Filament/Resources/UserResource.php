<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\KullaniciRolYardimcisi;
use App\Support\KullaniciTablosuYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    /** @var array<int, bool> */
    private static array $yoneticiYetkiCache = [];

    private static ?bool $deletedAtKolonuVarMi = null;

    protected static ?string $slug = 'sistem-kullanicilar';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Kullanıcılar (users)';
    }

    public static function getModelLabel(): string
    {
        return 'Kullanıcı';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Kullanıcılar';
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $kolonlar = [
            'id',
            'name',
            'ad_soyad',
            'kullanici_adi',
            'email',
            'super_admin_mi',
            'aktif_mi',
            'created_at',
        ];

        if (self::usersDeletedAtKolonuVarMi()) {
            $kolonlar[] = 'deleted_at';
        }

        return static::getModel()::query()
            ->select($kolonlar)
            ->whereKey($key)
            ->first();
    }

    public static function canViewAny(): bool
    {
        $kullanici = Auth::user();

        if (! $kullanici instanceof User) {
            return false;
        }

        return self::$yoneticiYetkiCache[(int) $kullanici->id] ??= KullaniciRolYardimcisi::superAdminVeyaIsAdmin($kullanici);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Ad (name)')
                    ->maxLength(255),
                Forms\Components\TextInput::make('ad_soyad')
                    ->label('Ad soyad')
                    ->maxLength(255),
                Forms\Components\TextInput::make('kullanici_adi')
                    ->label('Kullanıcı adı')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-posta')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Şifre')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                Forms\Components\Toggle::make('super_admin_mi')
                    ->label('Süper yönetici')
                    ->default(false),
                Forms\Components\Toggle::make('aktif_mi')
                    ->label('Kullanıcı aktif')
                    ->default(true)
                    ->helperText('Pasif kullanıcı panele giriş yapamaz.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $kolonlar = [
                    'id',
                    'name',
                    'kullanici_adi',
                    'email',
                    'super_admin_mi',
                    'aktif_mi',
                    'created_at',
                ];

                if (self::usersDeletedAtKolonuVarMi()) {
                    $kolonlar[] = 'deleted_at';
                }

                return $query
                    ->select($kolonlar)
                    ->withCount('firmaKullanicilari')
                    ->with(['firmaKullanicilari.firma:id,ad,firma_kodu']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ad')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kullanici_adi')
                    ->label('Kullanıcı adı')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kayitli_firmalar')
                    ->label('Kayıtlı olduğu firmalar')
                    ->state(function (User $record): string {
                        return $record->firmaKullanicilari
                            ->map(function ($firmaKullanici): string {
                                $firma = $firmaKullanici->firma;
                                if (! $firma) {
                                    return '';
                                }

                                $kod = trim((string) ($firma->firma_kodu ?? ''));
                                $ad = trim((string) ($firma->ad ?? ''));

                                return $kod !== '' && $ad !== '' ? $ad.' ('.$kod.')' : ($ad !== '' ? $ad : $kod);
                            })
                            ->filter()
                            ->unique()
                            ->implode(', ');
                    })
                    ->placeholder('Firma kaydı yok')
                    ->wrap(),
                Tables\Columns\BadgeColumn::make('kullanici_turu')
                    ->label('Kullanıcı Türü')
                    ->getStateUsing(function (User $record): string {
                        $superAdminMi = (bool) ($record->super_admin_mi ?? false) || (bool) ($record->is_admin ?? false);
                        if ($superAdminMi) {
                            return 'Super Admin';
                        }

                        $firmaSayisi = (int) ($record->firma_kullanicilari_count ?? 0);

                        return $firmaSayisi > 0 ? 'Firma' : 'Abone';
                    })
                    ->colors([
                        'danger' => 'Super Admin',
                        'info' => 'Firma',
                        'success' => 'Abone',
                    ]),
                Tables\Columns\IconColumn::make('super_admin_mi')
                    ->label('Süper admin')
                    ->boolean(),
                Tables\Columns\BadgeColumn::make('aktif_mi')
                    ->label('Durum')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Pasif')
                    ->colors(['success' => true, 'danger' => false]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('aktif_pasif')
                    ->label(fn (User $record): string => (bool) $record->aktif_mi ? 'Pasif yap' : 'Aktif yap')
                    ->icon(fn (User $record): string => (bool) $record->aktif_mi ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (User $record): string => (bool) $record->aktif_mi ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        if ((int) $record->id === (int) Auth::id() && (bool) $record->aktif_mi) {
                            return;
                        }

                        $record->update(['aktif_mi' => ! (bool) $record->aktif_mi]);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    private static function usersDeletedAtKolonuVarMi(): bool
    {
        return self::$deletedAtKolonuVarMi ??= KullaniciTablosuYardimcisi::usersDeletedAtKolonuVarMi();
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return filled(request()->route('record')) && ! static::detayModu();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
