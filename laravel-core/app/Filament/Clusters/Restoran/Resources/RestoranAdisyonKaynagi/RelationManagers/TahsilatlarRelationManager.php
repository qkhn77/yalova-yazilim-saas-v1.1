<?php

namespace App\Filament\Clusters\Restoran\Resources\RestoranAdisyonKaynagi\RelationManagers;

use App\Filament\Clusters\Restoran\Kaynaklar\RestoranFilamentErisimYardimcisi;
use App\Models\Restoran\RestoranAdisyonTahsilati;
use App\Services\Restoran\RestoranTahsilatServisi;
use App\Support\Restoran\RestoranYetkiSablonlari;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TahsilatlarRelationManager extends RelationManager
{
    protected static string $relationship = 'tahsilatlar';

    protected static ?string $title = 'Tahsilatlar';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['finansHareketi:id,tarih,tutar,para_birimi,durum', 'kasaHesabi:id,ad', 'bankaHesabi:id,ad', 'posHesabi:id,ad']))
            ->columns([
                Tables\Columns\TextColumn::make('tahsilat_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('odeme_kanali')
                    ->label('Kanal')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'kasa' => 'Kasa',
                        'banka' => 'Banka',
                        'pos' => 'POS',
                        default => (string) ($state ?: '-'),
                    }),
                Tables\Columns\TextColumn::make('hesap')
                    ->label('Hesap')
                    ->state(fn (RestoranAdisyonTahsilati $record): string => static::hesapEtiketi($record)),
                Tables\Columns\TextColumn::make('tutar')
                    ->label('Tutar')
                    ->formatStateUsing(fn ($state, RestoranAdisyonTahsilati $record): string => number_format((float) ($state ?? 0), 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY')))
                    ->sortable(),
                Tables\Columns\TextColumn::make('finans_hareketi_id')
                    ->label('Finans')
                    ->formatStateUsing(fn ($state): string => $state ? '#'.$state : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
                Tables\Columns\TextColumn::make('notlar')
                    ->label('Not')
                    ->limit(45)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('iptal_et')
                    ->label('İptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (RestoranAdisyonTahsilati $record): bool => $record->durum === RestoranAdisyonTahsilati::DURUM_AKTIF
                        && RestoranFilamentErisimYardimcisi::restoranYetkisiVarMi(RestoranYetkiSablonlari::ADISYON_TAHSILAT))
                    ->form([
                        Forms\Components\Textarea::make('iptal_notu')
                            ->label('İptal notu')
                            ->maxLength(1000),
                    ])
                    ->action(function (RestoranAdisyonTahsilati $record, array $data): void {
                        app(RestoranTahsilatServisi::class)->tahsilatIptalEt(
                            $record,
                            filled($data['iptal_notu'] ?? null) ? (string) $data['iptal_notu'] : null
                        );

                        Notification::make()
                            ->title('Tahsilat iptal edildi')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('tahsilat_at', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    private static function hesapEtiketi(RestoranAdisyonTahsilati $record): string
    {
        return match ((string) $record->odeme_kanali) {
            'kasa' => (string) ($record->kasaHesabi?->ad ?: '-'),
            'banka' => (string) ($record->bankaHesabi?->ad ?: '-'),
            'pos' => (string) ($record->posHesabi?->ad ?: '-'),
            default => '-',
        };
    }
}
