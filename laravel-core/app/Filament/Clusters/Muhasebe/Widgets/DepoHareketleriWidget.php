<?php

namespace App\Filament\Clusters\Muhasebe\Widgets;

use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\StokHareketDurumu;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Services\TenantContextService;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DepoHareketleriWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        return $table
            ->heading('Depo hareketleri')
            ->description('Firmaya ait tüm depo stok giriş, çıkış, transfer ve sayım hareketlerini filtreleyin.')
            ->query(StokHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('durum', StokHareketDurumu::Aktif)
                ->with(['stokKarti:id,firma_id,kod,ad', 'depo:id,firma_id,kod,ad']))
            ->columns([
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('depo.ad')
                    ->label('Depo')
                    ->placeholder('Genel stok')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stokKarti.ad')
                    ->label('Stok')
                    ->description(fn (StokHareketi $record): ?string => $record->stokKarti?->kod)
                    ->searchable()
                    ->sortable()
                    ->limit(36),
                Tables\Columns\TextColumn::make('islem_turu')
                    ->label('Hareket tipi')
                    ->formatStateUsing(fn (?StokHareketIslemTuru $state): string => $state?->value ?? '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('miktar')
                    ->label('Miktar')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sonraki_miktar')
                    ->label('Sonraki stok')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('referans_tipi')
                    ->label('Kaynak')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('depo_id')
                    ->label('Depo')
                    ->options(fn (): array => [0 => 'Genel stok'] + Depo::query()
                        ->where('firma_id', $firmaId)
                        ->aktif()
                        ->orderBy('ad')
                        ->pluck('ad', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(array_key_exists('value', $data) && $data['value'] !== null, function (Builder $q) use ($data): Builder {
                            return (int) $data['value'] === 0
                                ? $q->whereNull('depo_id')
                                : $q->where('depo_id', (int) $data['value']);
                        })),
                Tables\Filters\SelectFilter::make('stok_id')
                    ->label('Stok')
                    ->options(fn (): array => StokKarti::query()
                        ->where('firma_id', $firmaId)
                        ->orderBy('ad')
                        ->limit(1000)
                        ->pluck('ad', 'id')
                        ->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('islem_turu')
                    ->label('Hareket tipi')
                    ->options(collect(StokHareketIslemTuru::cases())->mapWithKeys(
                        fn (StokHareketIslemTuru $tip): array => [$tip->value => $tip->value]
                    )),
                Tables\Filters\Filter::make('islem_tarihi')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['baslangic'] ?? null, fn (Builder $q, $d): Builder => $q->where('islem_tarihi', '>=', (string) $d.' 00:00:00'))
                        ->when($data['bitis'] ?? null, fn (Builder $q, $d): Builder => $q->where('islem_tarihi', '<=', (string) $d.' 23:59:59'))),
            ])
            ->defaultSort('islem_tarihi', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
