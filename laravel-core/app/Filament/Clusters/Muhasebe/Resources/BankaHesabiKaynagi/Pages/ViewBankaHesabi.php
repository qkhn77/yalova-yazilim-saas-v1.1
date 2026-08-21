<?php

namespace App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\BankaHesabiKaynagi;
use App\Models\Muhasebe\BankaHareketi;
use App\Models\Muhasebe\BankaHesabi;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Enumlar\HesapDurumu;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBankaHesabi extends ViewRecord
{
    protected static string $resource = BankaHesabiKaynagi::class;

    protected static ?string $title = 'Banka detayi';

    protected static string $view = 'filament.clusters.muhasebe.resources.banka-hesabi-kaynagi.pages.view-banka-hesabi';

    private ?string $aktifBakiyeMetniCache = null;

    public function infolist(Infolist $infolist): Infolist
    {
        if (! BankaHesabiKaynagi::detayModu()) {
            return $infolist
                ->schema([
                    TextEntry::make('ad')->label('Ad'),
                    TextEntry::make('durum')
                        ->label('Durum')
                        ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                            HesapDurumu::Aktif => 'Aktif',
                            HesapDurumu::Pasif => 'Pasif',
                            default => '-',
                        }),
                ])
                ->columns(['default' => 1, 'md' => 2]);
        }

        return $infolist
            ->schema([
                Section::make('Temel bilgiler')
                    ->schema([
                        TextEntry::make('firma.ad')->label('Firma'),
                        TextEntry::make('ad')->label('Ad'),
                        TextEntry::make('hesap_sahibi_unvan')->label('Hesap sahibi / şirket adı'),
                        TextEntry::make('kod')->label('Kod'),
                        TextEntry::make('durum')
                            ->label('Durum')
                            ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                                HesapDurumu::Aktif => 'Aktif',
                                HesapDurumu::Pasif => 'Pasif',
                                default => '-',
                            }),
                        TextEntry::make('banka_adi')->label('Banka'),
                        TextEntry::make('sube')->label('Sube'),
                        TextEntry::make('hesap_no')->label('Hesap no'),
                        TextEntry::make('iban')->label('IBAN'),
                        TextEntry::make('para_birimi')->label('Para birimi'),
                        TextEntry::make('bakiye_aktif')
                            ->label('Bakiye (aktif hareketler)')
                            ->getStateUsing(fn (BankaHesabi $record): string => $this->aktifBakiyeMetni($record)),
                        TextEntry::make('aciklama')->label('Aciklama')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Olusturulma')->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')->label('Guncellenme')->dateTime('d.m.Y H:i'),
                    ])->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'md' => 4]);
    }

    protected function getHeaderActions(): array
    {
        $detayModu = BankaHesabiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_gorunum' : 'detaylar')
                ->label($detayModu ? 'Hizli Gorunum' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? BankaHesabiKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\EditAction::make()->label('Duzenle'),
            ] : []),
        ];
    }

    public function aktifBakiyeMetni(BankaHesabi $record): string
    {
        if ($this->aktifBakiyeMetniCache !== null) {
            return $this->aktifBakiyeMetniCache;
        }

        $t = (float) BankaHareketi::query()
            ->where('banka_hesap_id', $record->getKey())
            ->where('durum', HareketDurumu::Aktif)
            ->sum('tutar');

        return $this->aktifBakiyeMetniCache = number_format($t, 2, ',', '.').' '.(string) ($record->para_birimi ?? '');
    }
}
