<?php

namespace App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\RelationManagers;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Filament\Clusters\Muhasebe\Concerns\HasMovementDetails;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KasaHareketleriRelationManager extends RelationManager
{
    use HasMovementDetails;
    protected static string $relationship = 'kasaHareketleri';

    protected static ?string $title = 'Kasa hareketleri';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'kasa_hesap_id',
                    'finans_hareket_id',
                    'tutar',
                    'para_birimi',
                    'durum',
                ])
                ->with([
                    'finansHareketi:id,firma_id,cari_id,tur,tarih,aciklama,referans_turu,referans_id,durum',
                    'finansHareketi.cari:id,firma_id,ad',
                ]))
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('finansHareketi.tarih')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('finansHareketi.tur')
                    ->label('Tur')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $tur = $state instanceof FinansHareketTuru ? $state : FinansHareketTuru::tryFrom((string) $state);

                        return match ($tur) {
                            FinansHareketTuru::Tahsilat => 'Tahsilat',
                            FinansHareketTuru::Odeme => 'Odeme',
                            FinansHareketTuru::Virman => 'Virman',
                            FinansHareketTuru::Mahsup => 'Mahsup',
                            default => (string) ($tur?->value ?? $state ?? '-'),
                        };
                    })
                    ->color(function ($state): string {
                        $tur = $state instanceof FinansHareketTuru ? $state : FinansHareketTuru::tryFrom((string) $state);

                        return match ($tur) {
                            FinansHareketTuru::Tahsilat => 'success',
                            FinansHareketTuru::Odeme => 'warning',
                            FinansHareketTuru::Virman => 'info',
                            FinansHareketTuru::Mahsup => 'gray',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('finansHareketi.cari.ad')
                    ->label('Cari')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, $record): string => number_format((float) ($state ?? 0), 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('finansHareketi.aciklama')
                    ->label('Aciklama')
                    ->limit(45)
                    ->toggleable(),
            ], $this->movementDetailColumns()))
            ->filters([
                Tables\Filters\Filter::make('tarih_araligi')
                    ->label('Tarih araligi')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Baslangic'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->whereHas('finansHareketi', function (Builder $fQuery) use ($data): void {
                            $fQuery
                                ->when($data['baslangic'] ?? null, fn (Builder $q, $date) => $q->where('tarih', '>=', (string) $date.' 00:00:00'))
                                ->when($data['bitis'] ?? null, fn (Builder $q, $date) => $q->where('tarih', '<=', (string) $date.' 23:59:59'));
                        });
                    }),
                Tables\Filters\SelectFilter::make('cari_id')
                    ->label('Cari')
                    ->options(function (): array {
                        $owner = $this->getOwnerRecord();
                        $firmaId = (int) ($owner->firma_id ?? 0);
                        if ($firmaId < 1) {
                            return [];
                        }

                        return Cari::query()
                            ->where('firma_id', $firmaId)
                            ->orderBy('ad')
                            ->pluck('ad', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $cariId = (int) ($data['value'] ?? 0);
                        if ($cariId < 1) {
                            return $query;
                        }

                        return $query->whereHas('finansHareketi', fn (Builder $fQuery) => $fQuery->where('cari_id', $cariId));
                    }),
            ])
            ->headerActions([])
            ->actions($this->movementDetailActions())
            ->bulkActions([])
            ->defaultSort('id', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
