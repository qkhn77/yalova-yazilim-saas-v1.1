<?php

namespace App\Filament\Resources\PlanYonetimKaynagi\RelationManagers;

use App\Models\Modul;
use App\Models\PlanModulu;
use App\Support\DenetimYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class PlanModulleriIliskisi extends RelationManager
{
    protected static string $relationship = 'planModulleri';

    protected static ?string $title = 'Plan modülleri';

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return SaaSemaYardimcisi::planModulleriTablosuVarMi() && SaaSemaYardimcisi::modullerTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('modul_id')
                ->label('Modül')
                ->options(function (): array {
                    $planId = (int) $this->getOwnerRecord()->getKey();
                    $data = PlanModulu::query()
                        ->where('plan_id', $planId)
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
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'plan_id',
                    'modul_id',
                ])
                ->with('modul:id,ad,kod'))
            ->columns([
                Tables\Columns\TextColumn::make('modul.ad')->label('Modül')->searchable(),
                Tables\Columns\TextColumn::make('modul.kod')->label('Kod')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Modül bağla')
                    ->using(function (array $data): PlanModulu {
                        $owner = $this->getOwnerRecord();
                        $query = PlanModulu::query()
                            ->where('plan_id', (int) $owner->getKey())
                            ->where('modul_id', (int) $data['modul_id'])
                            ->exists();
                        if ($query) {
                            throw ValidationException::withMessages([
                                'modul_id' => 'Bu modül bu planda zaten bağlı.',
                            ]);
                        }

                        $record = new PlanModulu($data);
                        $this->getRelationship()->save($record);
                        $record->refresh();
                        DenetimYardimcisi::kaydet(
                            'plan_modulu_degisti',
                            PlanModulu::class,
                            (int) $record->getKey(),
                            null,
                            null,
                            ['plan_id' => $record->plan_id, 'modul_id' => $record->modul_id]
                        );

                        return $record;
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Kaldır')
                    ->using(function (Model $record): bool {
                        /** @var PlanModulu $record */
                        $ozet = ['plan_id' => $record->plan_id, 'modul_id' => $record->modul_id];
                        $anahtar = (int) $record->getKey();
                        $silindi = (bool) $record->delete();
                        if ($silindi) {
                            DenetimYardimcisi::kaydet(
                                'plan_modulu_kaldirildi',
                                PlanModulu::class,
                                $anahtar,
                                null,
                                null,
                                $ozet
                            );
                        }

                        return $silindi;
                    }),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
