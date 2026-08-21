<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\RelationManagers;

use App\Models\FirmaModulu;
use App\Models\Modul;
use App\Services\ModulErisimService;
use App\Support\DenetimYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ModullerleIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'firmaModulleri';

    protected static ?string $title = 'Firma modülleri';

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return SaaSemaYardimcisi::firmaModulleriTablosuVarMi() && SaaSemaYardimcisi::modullerTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('modul_id')
                ->label('Modül')
                ->options(function (): array {
                    $firmaId = (int) $this->getOwnerRecord()->getKey();
                    $data = FirmaModulu::query()
                        ->where('firma_id', $firmaId)
                        ->pluck('modul_id');

                    return Modul::query()
                        ->where('aktif_mi', true)
                        ->whereNotIn('id', $data)
                        ->orderBy('ad')
                        ->pluck('ad', 'id')
                        ->all();
                })
                ->required()
                ->searchable(),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options([
                    'aktif' => 'Aktif',
                    'salt_okunur' => 'Salt okunur',
                    'kapali' => 'Kapalı',
                ])
                ->required()
                ->default('aktif')
                ->native(false),
            Forms\Components\DatePicker::make('baslangic_tarihi')->label('Başlangıç'),
            Forms\Components\DatePicker::make('bitis_tarihi')->label('Bitiş'),
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
                    'modul_id',
                    'durum',
                    'baslangic_tarihi',
                    'bitis_tarihi',
                ])
                ->with('modul:id,ad,kod'))
            ->columns([
                Tables\Columns\TextColumn::make('modul.ad')->label('Modül')->searchable(),
                Tables\Columns\TextColumn::make('modul.kod')->label('Kod')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    // Not: Closure'da `$record` adı Filament'te satır modelini enjekte eder; hücre değeri `$state`'tir.
                    ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                        'aktif' => 'Aktif',
                        'salt_okunur' => 'Salt okunur',
                        'kapali' => 'Kapalı',
                        default => filled($state) ? (string) $state : '—',
                    })
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'aktif' => 'success',
                        'salt_okunur' => 'warning',
                        'kapali' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('baslangic_tarihi')->label('Başlangıç')->date('d.m.Y'),
                Tables\Columns\TextColumn::make('bitis_tarihi')->label('Bitiş')->date('d.m.Y'),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\Action::make('topluModulEkle')
                    ->label('Toplu modül ekle')
                    ->icon('heroicon-o-squares-plus')
                    ->form([
                        Forms\Components\Select::make('modul_ids')
                            ->label('Modüller')
                            ->options(function (): array {
                                $firmaId = (int) $this->getOwnerRecord()->getKey();
                                $bagliModuller = FirmaModulu::query()
                                    ->where('firma_id', $firmaId)
                                    ->pluck('modul_id');

                                return Modul::query()
                                    ->where('aktif_mi', true)
                                    ->whereNotIn('id', $bagliModuller)
                                    ->orderBy('ad')
                                    ->pluck('ad', 'id')
                                    ->all();
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Birden fazla modülü aynı ayarlarla firmaya bağlayabilirsiniz.'),
                        Forms\Components\Select::make('durum')
                            ->label('Durum')
                            ->options([
                                'aktif' => 'Aktif',
                                'salt_okunur' => 'Salt okunur',
                                'kapali' => 'Kapalı',
                            ])
                            ->required()
                            ->default('aktif'),
                        Forms\Components\DatePicker::make('baslangic_tarihi')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis_tarihi')->label('Bitiş'),
                    ])
                    ->action(function (array $data): void {
                        $firmaId = (int) $this->getOwnerRecord()->getKey();
                        $modulIds = collect($data['modul_ids'] ?? [])
                            ->map(static fn (mixed $id): int => (int) $id)
                            ->filter()
                            ->unique()
                            ->values();

                        foreach ($modulIds as $modulId) {
                            $record = FirmaModulu::query()->firstOrCreate(
                                ['firma_id' => $firmaId, 'modul_id' => $modulId],
                                [
                                    'durum' => $data['durum'],
                                    'baslangic_tarihi' => $data['baslangic_tarihi'] ?? null,
                                    'bitis_tarihi' => $data['bitis_tarihi'] ?? null,
                                ],
                            );

                            if ($record->wasRecentlyCreated) {
                                ModulErisimService::firmaModuluCacheTemizle($firmaId);
                                DenetimYardimcisi::kaydet(
                                    'firma_modulu_degisti',
                                    FirmaModulu::class,
                                    (int) $record->getKey(),
                                    $firmaId,
                                    null,
                                    $record->only(['modul_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi'])
                                );
                            }
                        }
                    })
                    ->successNotificationTitle('Modüller firmaya eklendi'),
                Tables\Actions\CreateAction::make()
                    ->label('Modül ekle')
                    ->using(function (array $data): FirmaModulu {
                        $owner = $this->getOwnerRecord();
                        $query = FirmaModulu::query()
                            ->where('firma_id', (int) $owner->getKey())
                            ->where('modul_id', (int) $data['modul_id'])
                            ->exists();
                        if ($query) {
                            throw ValidationException::withMessages([
                                'modul_id' => 'Bu modül firmaya zaten bağlı.',
                            ]);
                        }

                        $record = new FirmaModulu($data);
                        $this->getRelationship()->save($record);
                        $record->refresh();
                        ModulErisimService::firmaModuluCacheTemizle((int) $record->firma_id);
                        DenetimYardimcisi::kaydet(
                            'firma_modulu_degisti',
                            FirmaModulu::class,
                            (int) $record->getKey(),
                            (int) $record->firma_id,
                            null,
                            $record->only(['modul_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi'])
                        );

                        return $record;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->form([
                        Forms\Components\Select::make('durum')
                            ->label('Durum')
                            ->options([
                                'aktif' => 'Aktif',
                                'salt_okunur' => 'Salt okunur',
                                'kapali' => 'Kapalı',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('baslangic_tarihi')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis_tarihi')->label('Bitiş'),
                    ])
                    ->using(function (array $data, Model $record): void {
                        /** @var FirmaModulu $record */
                        $record->update($data);
                        $record->refresh();
                        ModulErisimService::firmaModuluCacheTemizle((int) $record->firma_id);
                        DenetimYardimcisi::kaydet(
                            'firma_modulu_degisti',
                            FirmaModulu::class,
                            (int) $record->getKey(),
                            (int) $record->firma_id,
                            null,
                            $record->only(['durum', 'baslangic_tarihi', 'bitis_tarihi'])
                        );
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Kaldır')
                    ->requiresConfirmation()
                    ->modalHeading('Modül bağlantısını kaldır')
                    ->modalDescription('Bu firma ile modül arasındaki bağlantı silinecek. Modül tanımı sistemden kalkmaz.')
                    ->using(function (Model $record): bool {
                        /** @var FirmaModulu $record */
                        $ozet = $record->only(['modul_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi']);
                        $firmaId = (int) $record->firma_id;
                        $anahtar = (int) $record->getKey();
                        $silindi = (bool) $record->delete();
                        if ($silindi) {
                            ModulErisimService::firmaModuluCacheTemizle($firmaId);
                            DenetimYardimcisi::kaydet(
                                'firma_modulu_kaldirildi',
                                FirmaModulu::class,
                                $anahtar,
                                $firmaId,
                                $ozet,
                                null
                            );
                        }

                        return $silindi;
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('topluModulKaldir')
                    ->label('Seçilenleri kaldır')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Seçili modül bağlantılarını kaldır')
                    ->modalDescription('Seçtiğiniz modüller bu firmadan kaldırılacak. Modül tanımları sistemden silinmez.')
                    ->action(function (Collection $records): void {
                        foreach ($records as $record) {
                            /** @var FirmaModulu $record */
                            $ozet = $record->only(['modul_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi']);
                            $firmaId = (int) $record->firma_id;
                            $anahtar = (int) $record->getKey();

                            if ($record->delete()) {
                                ModulErisimService::firmaModuluCacheTemizle($firmaId);
                                DenetimYardimcisi::kaydet(
                                    'firma_modulu_kaldirildi',
                                    FirmaModulu::class,
                                    $anahtar,
                                    $firmaId,
                                    $ozet,
                                    null
                                );
                            }
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
