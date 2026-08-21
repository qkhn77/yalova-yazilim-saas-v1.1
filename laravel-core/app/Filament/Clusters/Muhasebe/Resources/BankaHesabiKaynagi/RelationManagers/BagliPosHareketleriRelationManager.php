<?php

namespace App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi\RelationManagers;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\PosHareketi;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Filament\Clusters\Muhasebe\Concerns\HasMovementDetails;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BagliPosHareketleriRelationManager extends RelationManager
{
    use HasMovementDetails;
    protected static string $relationship = 'bankaHareketleri';

    protected static ?string $title = 'Bağlı POS hareketleri';

    public function table(Table $table): Table
    {
        $bankaId = (int) ($this->getOwnerRecord()->getKey() ?? 0);

        return $table
            ->query(PosHareketi::query()
                ->whereHas('posHesabi', fn (Builder $query) => $query->where('banka_hesabi_id', $bankaId))
                ->select(['id', 'firma_id', 'finans_hareket_id', 'pos_hesap_id', 'tutar', 'para_birimi', 'brut_tutar', 'komisyon_tutari', 'slip_no', 'provizyon_no', 'durum'])
                ->with(['finansHareketi:id,firma_id,cari_id,tur,tarih,aciklama,referans_turu,referans_id,durum', 'finansHareketi.cari:id,firma_id,ad', 'posHesabi:id,ad']))
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('finansHareketi.tarih')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('posHesabi.ad')->label('POS')->searchable(),
                Tables\Columns\TextColumn::make('finansHareketi.tur')->label('Tür')->badge()->formatStateUsing(function ($state): string {
                    $tur = $state instanceof FinansHareketTuru ? $state : FinansHareketTuru::tryFrom((string) $state);

                    return match ($tur) {
                        FinansHareketTuru::Tahsilat => 'Tahsilat',
                        FinansHareketTuru::Odeme => 'Ödeme',
                        FinansHareketTuru::Virman => 'Virman',
                        FinansHareketTuru::Mahsup => 'Mahsup',
                        default => (string) ($tur?->value ?? $state ?? '—'),
                    };
                }),
                Tables\Columns\TextColumn::make('finansHareketi.cari.ad')->label('Cari')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tutar')->label('Tutar')->formatStateUsing(fn ($state, $record): string => number_format((float) ($state ?? 0), 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))->sortable(),
                Tables\Columns\TextColumn::make('komisyon_tutari')->label('Komisyon')->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 2, ',', '.'))->toggleable(),
                Tables\Columns\TextColumn::make('slip_no')->label('Slip no')->toggleable(),
                Tables\Columns\TextColumn::make('finansHareketi.aciklama')->label('Açıklama')->limit(45)->toggleable(),
            ], $this->movementDetailColumns()))
            ->filters([
                Tables\Filters\Filter::make('tarih_araligi')->label('Tarih aralığı')->form([
                    Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                    Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query->whereHas('finansHareketi', function (Builder $fQuery) use ($data): void {
                        $fQuery->when($data['baslangic'] ?? null, fn (Builder $q, $date) => $q->where('tarih', '>=', (string) $date.' 00:00:00'))
                            ->when($data['bitis'] ?? null, fn (Builder $q, $date) => $q->where('tarih', '<=', (string) $date.' 23:59:59'));
                    });
                }),
                Tables\Filters\SelectFilter::make('cari_id')->label('Cari')->options(function (): array {
                    $firmaId = (int) ($this->getOwnerRecord()->firma_id ?? 0);

                    return $firmaId > 0 ? Cari::query()->where('firma_id', $firmaId)->orderBy('ad')->pluck('ad', 'id')->all() : [];
                })->searchable()->query(function (Builder $query, array $data): Builder {
                    $cariId = (int) ($data['value'] ?? 0);

                    return $cariId > 0 ? $query->whereHas('finansHareketi', fn (Builder $q) => $q->where('cari_id', $cariId)) : $query;
                }),
            ])
            ->headerActions([])
            ->actions($this->movementDetailActions())
            ->bulkActions([])
            ->defaultSort('id', 'desc')
            ->paginated([10, 20, 50, 100]);
    }
}
