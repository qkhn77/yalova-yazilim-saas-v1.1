<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Models\Yetki;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class YetkilerIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'yetkiler';

    protected static ?string $title = 'Rol yetkileri';

    /** @var array<string,string>|null */
    private static ?array $modulFiltresiSecenekleri = null;

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return SaaSemaYardimcisi::yetkilerTablosuVarMi() && SaaSemaYardimcisi::rolYetkileriTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('yetki_id')
                ->label('Yetki')
                ->options(function (): array {
                    $rol = $this->getOwnerRecord();
                    $secili = $rol->yetkiler()->pluck((new Yetki)->getQualifiedKeyName())->all();

                    return Yetki::query()
                        ->whereNotIn('id', $secili)
                        ->orderByRaw('COALESCE(modul_kodu, \'\')')
                        ->orderBy('kod')
                        ->get()
                        ->mapWithKeys(fn (Yetki $record): array => [
                            (int) $record->id => trim(($record->modul_kodu ?: 'sistem').' / '.$record->kod.' / '.$record->ad),
                        ])
                        ->all();
                })
                ->searchable()
                ->required(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function eklenebilirModuller(): array
    {
        $rol = $this->getOwnerRecord();
        $secili = $rol->yetkiler()->pluck((new Yetki)->getQualifiedKeyName())->all();

        return Yetki::query()
            ->whereNotIn('id', $secili)
            ->selectRaw("COALESCE(NULLIF(modul_kodu, ''), 'sistem') as modul")
            ->distinct()
            ->orderBy('modul')
            ->pluck('modul', 'modul')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function eklenebilirYetkiler(?string $modul = null): array
    {
        $rol = $this->getOwnerRecord();
        $secili = $rol->yetkiler()->pluck((new Yetki)->getQualifiedKeyName())->all();

        $sorgu = Yetki::query()
            ->whereNotIn('id', $secili);

        if (filled($modul) && $modul !== 'tum_moduller') {
            if ($modul === 'sistem') {
                $sorgu->where(function (Builder $query): void {
                    $query->whereNull('modul_kodu')->orWhere('modul_kodu', '');
                });
            } else {
                $sorgu->where('modul_kodu', $modul);
            }
        }

        return $sorgu
            ->orderByRaw("COALESCE(modul_kodu, '')")
            ->orderBy('kod')
            ->get()
            ->mapWithKeys(fn (Yetki $record): array => [
                (int) $record->id => trim(($record->modul_kodu ?: 'sistem').' / '.$record->kod.' / '.$record->ad),
            ])
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'yetkiler.id',
                    'yetkiler.modul_kodu',
                    'yetkiler.kod',
                    'yetkiler.ad',
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('modul_kodu')
                    ->label('Modül')
                    ->placeholder('sistem')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kod')->label('Kod')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad')->label('Açıklama'),
            ])
            ->defaultSort('modul_kodu')
            ->filters([
                Tables\Filters\SelectFilter::make('modul_kodu')
                    ->label('Modül')
                    ->options(fn (): array => $this->modulFiltresiSecenekleri())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('modul_kodu', (string) $data['value'])
                    )),
            ])
            ->headerActions([
                Tables\Actions\Action::make('toplu_yetki_ekle')
                    ->label('Toplu yetki ekle')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('modul')
                            ->label('Modül')
                            ->options(fn (): array => ['tum_moduller' => 'Tüm modüller'] + $this->eklenebilirModuller())
                            ->default('tum_moduller')
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('yetki_idleri')
                            ->label('Yetkiler')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (Forms\Get $get): array => $this->eklenebilirYetkiler((string) ($get('modul') ?? 'tum_moduller')))
                            ->required()
                            ->helperText('Tek seferde birden fazla yetki seçip ekleyebilirsiniz.'),
                    ])
                    ->action(function (array $data): void {
                        $rol = $this->getOwnerRecord();
                        $yetkiIdleri = collect((array) ($data['yetki_idleri'] ?? []))
                            ->map(fn ($id): int => (int) $id)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->unique()
                            ->values()
                            ->all();

                        if ($yetkiIdleri === []) {
                            throw ValidationException::withMessages([
                                'yetki_idleri' => 'En az bir yetki seçmelisiniz.',
                            ]);
                        }

                        $gecerliIdler = Yetki::query()
                            ->whereIn('id', $yetkiIdleri)
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        if ($gecerliIdler === []) {
                            throw ValidationException::withMessages([
                                'yetki_idleri' => 'Seçilen yetkiler geçersiz.',
                            ]);
                        }

                        $rol->yetkiler()->syncWithoutDetaching($gecerliIdler);

                        Notification::make()
                            ->title('Yetkiler eklendi')
                            ->body(count($gecerliIdler).' yetki role eklendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\CreateAction::make()
                    ->label('Yetki ekle')
                    ->using(function (array $data): Model {
                        $rol = $this->getOwnerRecord();
                        $yetkiId = (int) ($data['yetki_id'] ?? 0);

                        if (! Yetki::query()->whereKey($yetkiId)->exists()) {
                            throw ValidationException::withMessages([
                                'yetki_id' => 'Seçilen yetki geçersiz.',
                            ]);
                        }

                        if ($rol->yetkiler()->wherePivot('yetki_id', $yetkiId)->exists()) {
                            throw ValidationException::withMessages([
                                'yetki_id' => 'Bu yetki rol üzerinde zaten var.',
                            ]);
                        }

                        $rol->yetkiler()->attach($yetkiId);

                        return Yetki::query()->findOrFail($yetkiId);
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Kaldır')
                    ->action(function (Model $record): void {
                        $rol = $this->getOwnerRecord();
                        $rol->yetkiler()->detach((int) $record->getKey());
                }),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<string,string>
     */
    private function modulFiltresiSecenekleri(): array
    {
        return self::$modulFiltresiSecenekleri ??= Yetki::query()
            ->whereNotNull('modul_kodu')
            ->where('modul_kodu', '!=', '')
            ->select('modul_kodu')
            ->distinct()
            ->orderBy('modul_kodu')
            ->pluck('modul_kodu', 'modul_kodu')
            ->all();
    }
}
