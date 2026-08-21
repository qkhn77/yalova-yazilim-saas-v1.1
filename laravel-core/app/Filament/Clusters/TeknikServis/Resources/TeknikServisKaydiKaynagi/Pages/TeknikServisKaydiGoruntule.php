<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Concerns\TeknikServisKayitFormSchema;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Filament\TeknikServisAlacakOzetAksiyonu;
use App\TeknikServis\Filament\TeknikServisAlacakPlanAksiyonu;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class TeknikServisKaydiGoruntule extends ViewRecord
{
    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected static ?string $title = 'Servis kaydı';

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.teknik-servis-kaydi-goruntule';

    protected function resolveRecord(int | string $key): Model
    {
        $record = parent::resolveRecord($key);

        if ($record instanceof TeknikServisKaydi) {
            if ($this->detayModu()) {
                $record->loadMissing([
                    'servisDurumu:id,ad',
                    'cari:id,ad,para_birimi',
                    'cihaz:id,ad',
                    'marka:id,ad',
                    'ariza:id,ad',
                    'arizalar:id,ad',
                    'aksesuarlar:id,ad',
                ]);
            }
        }

        return $record;
    }

    protected function fillForm(): void
    {
        if ($this->detayModu()) {
            parent::fillForm();
        }
    }

    public function infolist(Infolist $infolist): Infolist
    {
        if (! $this->detayModu()) {
            return $infolist->schema([]);
        }

        return $infolist
            ->schema([
                Section::make('Servis bilgileri')
                    ->schema([
                        TextEntry::make('fis_no')->label('Fiş no'),
                        TextEntry::make('servis_tipi')
                            ->label('Servis tipi')
                            ->formatStateUsing(fn ($state): string => $this->enumMetni($state)),
                        TextEntry::make('servisDurumu.ad')->label('Durum'),
                        TextEntry::make('oncelik')
                            ->label('Öncelik')
                            ->formatStateUsing(fn ($state): string => $this->enumMetni($state)),
                        TextEntry::make('servis_kanali')
                            ->label('Servis kanalı')
                            ->formatStateUsing(fn ($state): string => $this->enumMetni($state)),
                        TextEntry::make('kabul_tarihi')->label('Kabul tarihi')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3]),

                Section::make('Müşteri ve cihaz')
                    ->schema([
                        TextEntry::make('cari.ad')->label('Cari'),
                        TextEntry::make('musteri_ad_soyad')->label('Müşteri'),
                        TextEntry::make('musteri_tel')->label('Telefon'),
                        TextEntry::make('cihaz.ad')->label('Cihaz'),
                        TextEntry::make('marka.ad')->label('Marka'),
                        TextEntry::make('model_no')->label('Model'),
                        TextEntry::make('seri_no')->label('Seri no'),
                        TextEntry::make('_arizalar')
                            ->label('Arızalar')
                            ->state(fn (TeknikServisKaydi $record): string => $this->arizalarMetni($record))
                            ->columnSpanFull(),
                        TextEntry::make('_aksesuarlar')
                            ->label('Aksesuarlar')
                            ->state(fn (TeknikServisKaydi $record): string => $this->aksesuarlarMetni($record))
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3]),

                Section::make('Notlar')
                    ->schema([
                        TextEntry::make('musteri_sikayeti')->label('Müşteri şikayeti')->columnSpanFull(),
                        TextEntry::make('yapilan_islemler')->label('Yapılan işlemler')->columnSpanFull(),
                        TextEntry::make('musteriye_gorunen_not')->label('Müşteriye görünen not')->columnSpanFull(),
                        TextEntry::make('ic_servis_notu')->label('İç servis notu')->columnSpanFull(),
                    ]),

                Section::make('Toplam ve teslim')
                    ->schema([
                        TextEntry::make('toplam_tutar')->label('Toplam')->money(fn (TeknikServisKaydi $record): string => $this->paraBirimi($record)),
                        TextEntry::make('odenen_tutar')->label('Ödenen')->money(fn (TeknikServisKaydi $record): string => $this->paraBirimi($record)),
                        TextEntry::make('odeme_durumu')
                            ->label('Ödeme durumu')
                            ->formatStateUsing(fn ($state): string => $this->enumMetni($state)),
                        TextEntry::make('musteri_onay_durumu')
                            ->label('Müşteri onayı')
                            ->formatStateUsing(fn ($state): string => $this->enumMetni($state)),
                        TextEntry::make('teslim_tarihi')->label('Teslim tarihi')->dateTime('d.m.Y H:i'),
                        TextEntry::make('teslim_alan_ad_soyad')->label('Teslim alan'),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $detayModu = $this->detayModu();

        if (! $detayModu) {
            return [];
        }

        return [
            Actions\Action::make($detayModu ? 'hizliGorunum' : 'detayliGorunum')
                ->label($detayModu ? 'Hızlı Görünüm' : 'Detayları Göster')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn (): string => $detayModu
                    ? TeknikServisKaydiKaynagi::getUrl('view', ['record' => (int) $this->record->getKey()])
                    : request()->fullUrlWithQuery(['detay' => 1])),
            ...($detayModu ? [
                TeknikServisAlacakOzetAksiyonu::olustur(fn () => $this->record),
                TeknikServisAlacakPlanAksiyonu::olustur(fn () => $this->record),
                Actions\EditAction::make(),
            ] : [
                Actions\Action::make('duzenle')
                    ->label('Duzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (): string => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()])),
            ]),
        ];
    }

    private function detayModu(): bool
    {
        return request()->boolean('detay');
    }

    private function enumMetni(mixed $state): string
    {
        if (is_object($state) && method_exists($state, 'etiket')) {
            return (string) $state->etiket();
        }

        if (is_object($state) && property_exists($state, 'value')) {
            return (string) $state->value;
        }

        return filled($state) ? (string) $state : '—';
    }

    private function paraBirimi(TeknikServisKaydi $record): string
    {
        return strtoupper((string) ($record->tahsilat_para_birimi ?: $record->cari?->para_birimi ?: 'TRY'));
    }

    private function arizalarMetni(TeknikServisKaydi $record): string
    {
        $adlar = $record->relationLoaded('arizalar')
            ? $record->arizalar->pluck('ad')->filter()->values()
            : collect();

        if ($adlar->isEmpty() && $record->ariza?->ad) {
            $adlar->push((string) $record->ariza->ad);
        }

        return $adlar->isEmpty() ? '—' : $adlar->implode(', ');
    }

    private function aksesuarlarMetni(TeknikServisKaydi $record): string
    {
        if (! $record->relationLoaded('aksesuarlar') || $record->aksesuarlar->isEmpty()) {
            return '—';
        }

        return $record->aksesuarlar
            ->map(function ($aksesuar): string {
                $adet = (int) ($aksesuar->pivot?->adet ?? 0);
                $ek = $adet > 1 ? ' x'.$adet : '';

                return trim((string) $aksesuar->ad.$ek);
            })
            ->filter()
            ->values()
            ->implode(', ') ?: '—';
    }
}
