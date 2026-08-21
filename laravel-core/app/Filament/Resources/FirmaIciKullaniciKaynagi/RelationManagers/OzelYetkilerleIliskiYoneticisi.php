<?php

namespace App\Filament\Resources\FirmaIciKullaniciKaynagi\RelationManagers;

use App\Models\FirmaKullanici;
use App\Models\KullaniciYetki;
use App\Models\User;
use App\Services\YetkiService;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OzelYetkilerleIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'ozelYetkiler';

    protected static ?string $title = 'Kullanıcı rol ayarları';

    private static ?bool $goruntulenebilirMiCache = null;

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return self::$goruntulenebilirMiCache ??= SaaSemaYardimcisi::kullaniciYetkileriTablosuVarMi()
            && SaaSemaYardimcisi::yetkilerTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('yetki_id')
                ->label('Yetki')
                ->options(function (): array {
                    $record = auth()->user();
                    /** @var FirmaKullanici $table */
                    $table = $this->getOwnerRecord();
                    if (! $record instanceof User) {
                        return [];
                    }

                    return app(YetkiService::class)
                        ->atanabilirYetkiKayitlari($record, (int) $table->firma_id)
                        ->mapWithKeys(fn ($record) => [$record->id => $record->kod.' — '.$record->ad])
                        ->all();
                })
                ->required()
                ->searchable(),
            Forms\Components\Select::make('izin_tipi')
                ->label('İzin tipi')
                ->options([
                    'ver' => 'Ver',
                    'reddet' => 'Reddet',
                ])
                ->rules([Rule::in(['ver', 'reddet'])])
                ->required()
                ->default('ver')
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'kullanici_id',
                    'yetki_id',
                    'izin_tipi',
                ])
                ->with('yetki:id,kod,ad'))
            ->columns([
                Tables\Columns\TextColumn::make('yetki.kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('yetki.ad')->label('Açıklama'),
                Tables\Columns\TextColumn::make('izin_tipi')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (?string $record): string => match ($record) {
                        'ver' => 'Ver',
                        'reddet' => 'Reddet',
                        default => (string) $record,
                    }),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Satır ekle')
                    ->using(function (array $data): Model {
                        /** @var FirmaKullanici $table */
                        $table = $this->getOwnerRecord();

                        if (! in_array((string) ($data['izin_tipi'] ?? ''), ['ver', 'reddet'], true)) {
                            throw ValidationException::withMessages([
                                'izin_tipi' => 'İzin tipi yalnızca ver/reddet olabilir.',
                            ]);
                        }

                        $record = KullaniciYetki::query()
                            ->where('firma_id', (int) $table->firma_id)
                            ->where('kullanici_id', (int) $table->kullanici_id)
                            ->where('yetki_id', (int) $data['yetki_id'])
                            ->exists();
                        if ($record) {
                            throw ValidationException::withMessages([
                                'yetki_id' => 'Bu yetki satırı zaten mevcut.',
                            ]);
                        }

                        return KullaniciYetki::query()->create([
                            'firma_id' => (int) $table->firma_id,
                            'kullanici_id' => (int) $table->kullanici_id,
                            'yetki_id' => (int) $data['yetki_id'],
                            'izin_tipi' => (string) $data['izin_tipi'],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->using(function (array $data, Model $record): void {
                        if (! in_array((string) ($data['izin_tipi'] ?? ''), ['ver', 'reddet'], true)) {
                            throw ValidationException::withMessages([
                                'izin_tipi' => 'İzin tipi yalnızca ver/reddet olabilir.',
                            ]);
                        }

                        /** @var KullaniciYetki $record */
                        $record->update([
                            'izin_tipi' => (string) $data['izin_tipi'],
                        ]);
                    }),
                Tables\Actions\DeleteAction::make()->label('Sil'),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
