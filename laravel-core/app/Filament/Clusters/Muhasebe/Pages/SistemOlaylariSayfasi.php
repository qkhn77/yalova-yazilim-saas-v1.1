<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\SistemOlayi;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class SistemOlaylariSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Sistem Olaylari';

    protected static ?string $slug = 'sistem-olaylari';

    protected static ?string $navigationLabel = 'Sistem Olaylari';

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 95;

    protected static string $view = 'filament.clusters.muhasebe.pages.sistem-olaylari-sayfasi';

    public function getHeading(): string|Htmlable
    {
        return 'Sistem Olaylari';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::MUHASEBE_GORUNTULE;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SistemOlayi::query()
                    ->select([
                        'id',
                        'created_at',
                        'seviye',
                        'tip',
                        'mesaj',
                        'firma_id',
                    ])
                    ->with('firma:id,ad')
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('seviye')
                    ->label('Seviye')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tip')->label('Tip')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('mesaj')->label('Mesaj')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('firma.ad')->label('Firma')->placeholder('-')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('seviye')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                    ]),
                Tables\Filters\SelectFilter::make('tip')
                    ->options(fn (): array => SistemOlayi::query()->withoutGlobalScopes()->select('tip')->distinct()->orderBy('tip')->pluck('tip', 'tip')->all()),
                Tables\Filters\SelectFilter::make('firma_id')
                    ->label('Firma')
                    ->relationship('firma', 'ad')
                    ->searchable(),
                Tables\Filters\Filter::make('tarih')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Baslangic'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitis'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['baslangic'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date.' 00:00:00'))
                            ->when($data['bitis'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', $date.' 23:59:59'));
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }
}
