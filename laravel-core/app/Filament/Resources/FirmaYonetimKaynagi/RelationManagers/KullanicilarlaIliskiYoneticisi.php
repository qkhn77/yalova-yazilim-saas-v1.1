<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\RelationManagers;

use App\Models\Firma;
use App\Models\FirmaKullanici;
use App\Models\Rol;
use App\Models\User;
use App\Support\DenetimYardimcisi;
use App\Support\RolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class KullanicilarlaIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'firmaKullanicilari';

    protected static ?string $title = 'Firma kullanıcıları';

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return SaaSemaYardimcisi::firmaKullanicilariTablosuVarMi() && SaaSemaYardimcisi::rollerTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->label('E-posta')
                ->email()
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('kullanici_adi')
                ->label('Kullanıcı adı')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('ad_soyad')
                ->label('Ad soyad')
                ->maxLength(255),
            Forms\Components\TextInput::make('password')
                ->label('Şifre')
                ->password()
                ->revealable()
                ->minLength(6)
                ->maxLength(255)
                ->helperText('Yeni kullanıcı için zorunlu, mevcut kullanıcı için opsiyonel.'),
            Forms\Components\Select::make('rol_id')
                ->label('Rol')
                ->options(fn (): array => Rol::query()->orderBy('ad')->get()
                    ->mapWithKeys(fn (Rol $r): array => [$r->id => $r->ad.' ('.$r->kod.')'])
                    ->all())
                ->default(fn (): ?int => RolYardimcisi::varsayilanFirmaYoneticisiRolId())
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options([
                    'aktif' => 'Aktif',
                    'pasif' => 'Pasif',
                ])
                ->required()
                ->default('aktif'),
            Forms\Components\Select::make('onay_durumu')
                ->label('Onay durumu')
                ->options([
                    'aktif' => 'Aktif',
                    'beklemede' => 'Beklemede',
                ])
                ->required()
                ->default('aktif')
                ->visible(fn (): bool => SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()),
            Forms\Components\Toggle::make('varsayilan_firma_mi')
                ->label('Varsayılan firma')
                ->default(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'kullanici:id,kullanici_adi,email',
                    'rol:id,ad',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('kullanici.kullanici_adi')->label('Kullanıcı adı')->searchable(),
                Tables\Columns\TextColumn::make('kullanici.email')->label('E-posta')->searchable(),
                Tables\Columns\TextColumn::make('rol.ad')->label('Rol')->placeholder('—'),
                Tables\Columns\TextColumn::make('durum')->label('Durum')->badge(),
                Tables\Columns\TextColumn::make('onay_durumu')
                    ->label('Onay')
                    ->badge()
                    ->placeholder('aktif')
                    ->visible(fn (): bool => SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()),
                Tables\Columns\IconColumn::make('varsayilan_firma_mi')->label('Varsayılan')->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Kullanıcı ekle')
                    ->using(function (array $data): Model {
                        $email = strtolower(trim((string) ($data['email'] ?? '')));
                        $kullaniciAdi = trim((string) ($data['kullanici_adi'] ?? ''));
                        if ($email === '' || $kullaniciAdi === '') {
                            throw ValidationException::withMessages([
                                'email' => 'E-posta ve kullanıcı adı zorunludur.',
                            ]);
                        }
                        if (! empty($data['password']) && mb_strlen((string) $data['password']) < 6) {
                            throw ValidationException::withMessages([
                                'password' => 'Şifre en az 6 karakter olmalıdır.',
                            ]);
                        }
                        if (! empty($data['rol_id']) && ! Rol::query()->whereKey((int) $data['rol_id'])->exists()) {
                            throw ValidationException::withMessages([
                                'rol_id' => 'Seçilen rol geçersiz.',
                            ]);
                        }

                        /** @var Firma $record */
                        $record = $this->getOwnerRecord();

                        $query = User::query()->withoutGlobalScopes()->where('email', $email)->first();
                        if ($query) {
                            $kullaniciAdiCakisiyor = User::query()
                                ->withoutGlobalScopes()
                                ->where('kullanici_adi', $kullaniciAdi)
                                ->whereKeyNot((int) $query->getKey())
                                ->exists();
                            if ($kullaniciAdiCakisiyor) {
                                throw ValidationException::withMessages([
                                    'kullanici_adi' => 'Bu kullanıcı adı sistemde başka bir kullanıcı tarafından kullanılıyor.',
                                ]);
                            }

                            if ((bool) ($query->super_admin_mi ?? false)) {
                                throw ValidationException::withMessages([
                                    'email' => 'Sistem yöneticisi bu ekrandan firmaya bağlanamaz.',
                                ]);
                            }

                            if (! empty($data['password'])) {
                                $query->password = Hash::make((string) $data['password']);
                            }
                            $query->kullanici_adi = $kullaniciAdi;
                            $query->ad_soyad = (string) ($data['ad_soyad'] ?? $query->ad_soyad);
                            $query->name = (string) ($data['ad_soyad'] ?? $kullaniciAdi);
                            $query->save();
                            $recordUser = $query;
                        } else {
                            if (empty($data['password'])) {
                                throw ValidationException::withMessages([
                                    'password' => 'Yeni kullanıcı için şifre zorunludur.',
                                ]);
                            }
                            $kullaniciAdiCakisiyor = User::query()
                                ->withoutGlobalScopes()
                                ->where('kullanici_adi', $kullaniciAdi)
                                ->exists();
                            if ($kullaniciAdiCakisiyor) {
                                throw ValidationException::withMessages([
                                    'kullanici_adi' => 'Bu kullanıcı adı sistemde başka bir kullanıcı tarafından kullanılıyor.',
                                ]);
                            }

                            $recordUser = User::query()->create([
                                'email' => $email,
                                'kullanici_adi' => $kullaniciAdi,
                                'ad_soyad' => $data['ad_soyad'] ?? null,
                                'name' => $data['ad_soyad'] ?? $kullaniciAdi,
                                'password' => Hash::make((string) $data['password']),
                            ]);
                        }

                        $query = FirmaKullanici::query()
                            ->withoutGlobalScopes()
                            ->where('firma_id', (int) $record->getKey())
                            ->whereHas('kullanici', fn (Builder $query) => $query->where('kullanici_adi', $kullaniciAdi))
                            ->where('kullanici_id', '!=', (int) $recordUser->getKey())
                            ->whereNull('deleted_at')
                            ->exists();
                        if ($query) {
                            throw ValidationException::withMessages([
                                'kullanici_adi' => 'Bu firma için bu kullanıcı adı zaten kullanılıyor.',
                            ]);
                        }

                        $query = FirmaKullanici::query()
                            ->withoutGlobalScopes()
                            ->where('firma_id', (int) $record->getKey())
                            ->where('kullanici_id', (int) $recordUser->getKey())
                            ->whereNull('deleted_at')
                            ->exists();
                        if ($query) {
                            throw ValidationException::withMessages([
                                'email' => 'Bu kullanıcı bu firmaya zaten bağlı.',
                            ]);
                        }

                        if ((bool) ($data['varsayilan_firma_mi'] ?? false)) {
                            FirmaKullanici::query()
                                ->withoutGlobalScopes()
                                ->where('kullanici_id', (int) $recordUser->getKey())
                                ->update(['varsayilan_firma_mi' => false]);
                        }

                        $payload = [
                            'firma_id' => (int) $record->getKey(),
                            'kullanici_id' => (int) $recordUser->getKey(),
                            'rol_id' => $data['rol_id'] ?? null,
                            'durum' => $data['durum'] ?? 'aktif',
                            'varsayilan_firma_mi' => (bool) ($data['varsayilan_firma_mi'] ?? false),
                        ];
                        if (SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()) {
                            $payload['onay_durumu'] = $data['onay_durumu'] ?? 'aktif';
                        }
                        $record = FirmaKullanici::query()->create($payload);

                        DenetimYardimcisi::kaydet(
                            'firma_kullanicisi_eklendi',
                            FirmaKullanici::class,
                            (int) $record->getKey(),
                            (int) $record->firma_id,
                            null,
                            $record->only(['kullanici_id', 'rol_id', 'durum', 'onay_durumu', 'varsayilan_firma_mi'])
                        );

                        return $record;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->fillForm(function (FirmaKullanici $record): array {
                        $veri = $record->toArray();
                        $kullanici = $record->kullanici;
                        if ($kullanici) {
                            $veri['email'] = $kullanici->email;
                            $veri['kullanici_adi'] = $kullanici->kullanici_adi;
                            $veri['ad_soyad'] = $kullanici->ad_soyad ?? $kullanici->name;
                        }
                        $veri['password'] = null;

                        return $veri;
                    })
                    ->using(function (array $data, Model $record): void {
                        /** @var FirmaKullanici $record */
                        $table = $record->kullanici;
                        if ($table) {
                            $email = strtolower(trim((string) ($data['email'] ?? '')));
                            if ($email === '') {
                                throw ValidationException::withMessages([
                                    'email' => 'E-posta zorunludur.',
                                ]);
                            }
                            if (strtolower((string) $table->email) !== $email) {
                                $emailCakisiyor = User::query()
                                    ->withoutGlobalScopes()
                                    ->where('email', $email)
                                    ->whereKeyNot((int) $table->getKey())
                                    ->exists();
                                if ($emailCakisiyor) {
                                    throw ValidationException::withMessages([
                                        'email' => 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.',
                                    ]);
                                }
                                $table->email = $email;
                            }

                            $kullaniciAdi = trim((string) ($data['kullanici_adi'] ?? $table->kullanici_adi));
                            $sistemGeneliCakisiyor = User::query()
                                ->withoutGlobalScopes()
                                ->where('kullanici_adi', $kullaniciAdi)
                                ->whereKeyNot((int) $table->getKey())
                                ->exists();
                            if ($sistemGeneliCakisiyor) {
                                throw ValidationException::withMessages([
                                    'kullanici_adi' => 'Bu kullanıcı adı sistemde başka bir kullanıcı tarafından kullanılıyor.',
                                ]);
                            }
                            $recordExists = FirmaKullanici::query()
                                ->withoutGlobalScopes()
                                ->where('firma_id', (int) $record->firma_id)
                                ->whereHas('kullanici', fn (Builder $query) => $query->where('kullanici_adi', $kullaniciAdi))
                                ->where('kullanici_id', '!=', (int) $table->getKey())
                                ->whereNull('deleted_at')
                                ->exists();
                            if ($recordExists) {
                                throw ValidationException::withMessages([
                                    'kullanici_adi' => 'Bu firma için bu kullanıcı adı zaten kullanılıyor.',
                                ]);
                            }

                            $table->kullanici_adi = $kullaniciAdi;
                            $table->ad_soyad = (string) ($data['ad_soyad'] ?? $table->ad_soyad);
                            $table->name = (string) ($data['ad_soyad'] ?? $table->kullanici_adi);
                            if (! empty($data['password'])) {
                                if (mb_strlen((string) $data['password']) < 6) {
                                    throw ValidationException::withMessages([
                                        'password' => 'Şifre en az 6 karakter olmalıdır.',
                                    ]);
                                }
                                $table->password = Hash::make((string) $data['password']);
                            }
                            $table->save();
                        }

                        if ((bool) ($data['varsayilan_firma_mi'] ?? false) && $table) {
                            FirmaKullanici::query()
                                ->withoutGlobalScopes()
                                ->where('kullanici_id', (int) $table->getKey())
                                ->whereKeyNot((int) $record->getKey())
                                ->update(['varsayilan_firma_mi' => false]);
                        }

                        $payload = [
                            'rol_id' => $data['rol_id'] ?? $record->rol_id,
                            'durum' => $data['durum'] ?? $record->durum,
                            'varsayilan_firma_mi' => (bool) ($data['varsayilan_firma_mi'] ?? false),
                        ];
                        if (SaaSemaYardimcisi::firmaKullanicilariOnayDurumuKolonuVarMi()) {
                            $payload['onay_durumu'] = $data['onay_durumu'] ?? $record->onay_durumu;
                        }
                        $record->update($payload);

                        DenetimYardimcisi::kaydet(
                            'firma_kullanicisi_guncellendi',
                            FirmaKullanici::class,
                            (int) $record->getKey(),
                            (int) $record->firma_id,
                            null,
                            $record->only(['durum', 'onay_durumu', 'rol_id', 'varsayilan_firma_mi'])
                        );
                }),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
