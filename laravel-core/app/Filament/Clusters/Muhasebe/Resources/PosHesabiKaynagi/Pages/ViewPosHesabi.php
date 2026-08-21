<?php

namespace App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\PosHesabiKaynagi;
use App\Models\Muhasebe\PosHareketi;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\PosTipi;
use App\Muhasebe\Enumlar\SaglayiciTipi;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPosHesabi extends ViewRecord
{
    protected static string $resource = PosHesabiKaynagi::class;

    protected static string $view = 'filament.clusters.muhasebe.resources.pos-hesabi-kaynagi.pages.view-pos-hesabi';

    protected static ?string $title = 'POS detayı';

    private ?string $aktifBakiyeMetniCache = null;

    public function aktifBakiyeMetni(): string
    {
        if ($this->aktifBakiyeMetniCache !== null) {
            return $this->aktifBakiyeMetniCache;
        }

        $tutar = PosHareketi::query()
            ->where('pos_hesap_id', $this->record->getKey())
            ->where('durum', \App\Muhasebe\Enumlar\HareketDurumu::Aktif)
            ->sum('tutar');

        return $this->aktifBakiyeMetniCache = number_format((float) $tutar, 2, ',', '.').' '.strtoupper((string) ($this->record->para_birimi ?? 'TRY'));
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! PosHesabiKaynagi::detayModu()) {
            return;
        }

        $this->record->loadMissing(['firma:id,ad', 'bankaHesabi:id,ad']);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        if (! PosHesabiKaynagi::detayModu()) {
            return $infolist
                ->schema([
                    TextEntry::make('ad')->label('Ad'),
                    TextEntry::make('kod')->label('Kod'),
                    TextEntry::make('pos_tipi')
                        ->label('POS tipi')
                        ->formatStateUsing(fn (?PosTipi $state) => $state?->etiket() ?? '—'),
                    TextEntry::make('saglayici_tipi')
                        ->label('Sağlayıcı tipi')
                        ->formatStateUsing(fn (?SaglayiciTipi $state) => $state?->etiket() ?? '—'),
                    TextEntry::make('durum')
                        ->label('Durum')
                        ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                            HesapDurumu::Aktif => 'Aktif',
                            HesapDurumu::Pasif => 'Pasif',
                            default => '—',
                        }),
                ])
                ->columns(['default' => 1, 'md' => 3]);
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
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('POS türü')
                    ->schema([
                        TextEntry::make('pos_tipi')
                            ->label('POS tipi')
                            ->formatStateUsing(fn (?PosTipi $state) => $state?->etiket() ?? '—'),
                    ])
                    ->columnSpan(1),

                Section::make('Sağlayıcı')
                    ->schema([
                        TextEntry::make('saglayici_tipi')
                            ->label('Sağlayıcı tipi')
                            ->formatStateUsing(fn (?SaglayiciTipi $state) => $state?->etiket() ?? '—'),
                        TextEntry::make('bankaHesabi.ad')->label('Banka hesabı'),
                        TextEntry::make('banka_adi')->label('Banka adı'),
                        TextEntry::make('saglayici_adi')->label('Sağlayıcı adı'),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Bağlantı')
                    ->schema([
                        TextEntry::make('terminal_no')->label('Terminal no'),
                        TextEntry::make('uye_isyeri_no')->label('Üye işyeri no'),
                        TextEntry::make('magaza_kodu')->label('Mağaza kodu'),
                        TextEntry::make('sanal_pos_no')->label('Sanal POS no'),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Finans')
                    ->schema([
                        TextEntry::make('para_birimi')->label('Para birimi'),
                        TextEntry::make('komisyon_orani')->label('Komisyon oranı (%)'),
                        TextEntry::make('sabit_komisyon_tutari')->label('Sabit komisyon tutarı')->money('TRY'),
                        TextEntry::make('bloke_gun_sayisi')->label('Bloke gün sayısı'),
                        TextEntry::make('valor_gun_sayisi')->label('Valör gün sayısı'),
                        IconEntry::make('erken_odeme_destegi_var_mi')->label('Erken ödeme desteği')->boolean(),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Taksit')
                    ->schema([
                        IconEntry::make('taksit_destegi_var_mi')->label('Taksit desteği')->boolean(),
                        TextEntry::make('maksimum_taksit_sayisi')->label('Maksimum taksit sayısı'),
                        IconEntry::make('tek_cekim_destegi_var_mi')->label('Tek çekim desteği')->boolean(),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Yönetim')
                    ->schema([
                        IconEntry::make('varsayilan_mi')->label('Varsayılan')->boolean(),
                        TextEntry::make('aciklama')->label('Açıklama')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Oluşturulma')->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')->label('Güncellenme')->dateTime('d.m.Y H:i'),
                    ])
                    ->columnSpan(1),
            ])
            ->columns(['default' => 1, 'md' => 4]);
    }

    protected function getHeaderActions(): array
    {
        $detayModu = PosHesabiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizli_gorunum' : 'detaylar')
                ->label($detayModu ? 'Hizli Gorunum' : 'Detaylar')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? PosHesabiKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                Actions\EditAction::make()->label('Düzenle'),
            ] : []),
        ];
    }
}
