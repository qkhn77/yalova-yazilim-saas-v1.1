<?php

namespace App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\KasaHesabiKaynagi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Muhasebe\KasaHareketi;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\HesapDurumu;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewKasaHesabi extends ViewRecord
{
    protected static string $resource = KasaHesabiKaynagi::class;

    protected static ?string $title = 'Kasa detayı';

    protected static string $view = 'filament.clusters.muhasebe.resources.kasa-hesabi-kaynagi.pages.view-kasa-hesabi';

    private ?string $aktifBakiyeMetniCache = null;

    public function infolist(Infolist $infolist): Infolist
    {
        if (! KasaHesabiKaynagi::detayModu()) {
            return $infolist
                ->columns(2)
                ->schema([
                    TextEntry::make('ad')->label('Ad'),
                    TextEntry::make('kod')->label('Kod'),
                    TextEntry::make('durum')
                        ->label('Durum')
                        ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                            HesapDurumu::Aktif => 'Aktif',
                            HesapDurumu::Pasif => 'Pasif',
                            default => '—',
                        }),
                    TextEntry::make('para_birimi')->label('Para birimi'),
                ]);
        }

        return $infolist
            ->schema([
                Section::make('Temel bilgiler')
                    ->schema([
                        TextEntry::make('firma.ad')->label('Firma'),
                        TextEntry::make('ad')->label('Ad'),
                        TextEntry::make('kod')->label('Kod'),
                        TextEntry::make('durum')
                            ->label('Durum')
                            ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                                HesapDurumu::Aktif => 'Aktif',
                                HesapDurumu::Pasif => 'Pasif',
                                default => '—',
                            }),
                        TextEntry::make('para_birimi')->label('Para birimi'),
                        TextEntry::make('bakiye_aktif')
                            ->label('Bakiye (aktif hareketler)')
                            ->getStateUsing(fn (KasaHesabi $record): string => $this->aktifBakiyeMetni($record)),
                        TextEntry::make('sorumlu')->label('Sorumlu'),
                        TextEntry::make('aciklama')->label('Açıklama')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')->label('Güncellenme')->dateTime('d.m.Y H:i'),
                    ])->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'md' => 4]);
    }

    protected function getHeaderActions(): array
    {
        $detayModu = KasaHesabiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_gorunum' : 'detaylar')
                ->label($detayModu ? 'Hızlı Görünüm' : 'Detayları Göster')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? KasaHesabiKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            Actions\EditAction::make()->label('Düzenle'),
        ];
    }

    public function aktifBakiyeMetni(KasaHesabi $record): string
    {
        if ($this->aktifBakiyeMetniCache !== null) {
            return $this->aktifBakiyeMetniCache;
        }

        $t = array_key_exists('aktif_bakiye', $record->getAttributes())
            ? (float) ($record->aktif_bakiye ?? 0)
            : (float) KasaHareketi::query()
                ->where('kasa_hesap_id', $record->getKey())
                ->where('durum', HareketDurumu::Aktif)
                ->sum('tutar');

        return $this->aktifBakiyeMetniCache = number_format($t, 2, ',', '.').' '.(string) ($record->para_birimi ?? '');
    }
}
