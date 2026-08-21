<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelMaasDonemiKaynagi\RelationManagers;

use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Models\Muhasebe\BankaHesabi;
use App\Models\Muhasebe\KasaHesabi;
use App\Models\Personel\PersonelMaasHareketi;
use App\Models\Personel\PersonelMaasOdemeKaydi;
use App\Services\PersonelTakip\PersonelFinansHareketServisi;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MaasHareketleriRelationManager extends RelationManager
{
    protected static string $relationship = 'hareketler';

    protected static ?string $title = 'Maaş Hareketleri';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::MAAS_GORUNTULE);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn ($query) => $query->with(['personel:id,ad_soyad', 'odemeler:id,maas_hareketi_id,tutar,finans_hareketi_id']))
            ->columns([
                Tables\Columns\TextColumn::make('personel.ad_soyad')
                    ->label('Personel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('brut_tutar')
                    ->label('Brüt')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fazla_mesai_tutari')
                    ->label('Fazla mesai')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('avans_kesintisi')
                    ->label('Avans kesintisi')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_tutar')
                    ->label('Net')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('isveren_toplam_maliyeti')
                    ->label('İşveren maliyeti')
                    ->state(fn (PersonelMaasHareketi $record): float => round(
                        (float) $record->brut_tutar
                        + (float) $record->fazla_mesai_tutari
                        + (float) $record->prim_tutari
                        + (float) $record->ek_odeme_tutari
                        + (float) $record->sgk_isveren_tutari
                        + (float) $record->issizlik_isveren_tutari
                        + (float) $record->diger_maliyet_tutari,
                        2,
                    ))
                    ->money('TRY')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('odenen_tutar')
                    ->label('Ödenen')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kalan_tutar')
                    ->label('Kalan')
                    ->money('TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('_finans')
                    ->label('Finans')
                    ->state(fn (PersonelMaasHareketi $record): string => $this->finansOzeti($record))
                    ->badge(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('maliyet_kalemlerini_duzenle')
                    ->label('Maliyet kalemleri')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->visible(fn (): bool => PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::MAAS_HESAPLA))
                    ->fillForm(fn (PersonelMaasHareketi $record): array => [
                        'sgk_isveren_tutari' => $record->sgk_isveren_tutari,
                        'issizlik_isveren_tutari' => $record->issizlik_isveren_tutari,
                        'gelir_vergisi_tutari' => $record->gelir_vergisi_tutari,
                        'damga_vergisi_tutari' => $record->damga_vergisi_tutari,
                        'diger_maliyet_tutari' => $record->diger_maliyet_tutari,
                        'maliyet_notu' => $record->maliyet_notu,
                    ])
                    ->form(fn (): array => $this->maliyetKalemleriFormu())
                    ->action(function (PersonelMaasHareketi $record, array $data): void {
                        $record->fill([
                            'sgk_isveren_tutari' => $data['sgk_isveren_tutari'] ?? 0,
                            'issizlik_isveren_tutari' => $data['issizlik_isveren_tutari'] ?? 0,
                            'gelir_vergisi_tutari' => $data['gelir_vergisi_tutari'] ?? 0,
                            'damga_vergisi_tutari' => $data['damga_vergisi_tutari'] ?? 0,
                            'diger_maliyet_tutari' => $data['diger_maliyet_tutari'] ?? 0,
                            'maliyet_notu' => $data['maliyet_notu'] ?? null,
                        ]);
                        $record->save();

                        Notification::make()
                            ->title('Personel maliyet kalemleri güncellendi')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('odeme_ekle')
                    ->label('Ödeme Ekle')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PersonelMaasHareketi $record): bool => $this->odemeYetkisiVarMi()
                        && $record->durum === 'onaylandi'
                        && (float) $record->kalan_tutar > 0)
                    ->fillForm(fn (PersonelMaasHareketi $record): array => [
                        'tarih' => now()->toDateString(),
                        'tutar' => round((float) $record->kalan_tutar, 2),
                        'para_birimi' => (string) ($record->donem?->para_birimi ?: 'TRY'),
                        'odeme_kanali' => 'kasa',
                        'finansa_isle' => true,
                    ])
                    ->form(fn (): array => $this->odemeFormu())
                    ->action(function (PersonelMaasHareketi $record, array $data): void {
                        try {
                            $finansMetni = DB::transaction(function () use ($record, $data): ?string {
                                $odeme = PersonelMaasOdemeKaydi::query()->create([
                                    'firma_id' => $record->firma_id,
                                    'maas_hareketi_id' => $record->id,
                                    'tarih' => $data['tarih'] ?? now()->toDateString(),
                                    'tutar' => $data['tutar'] ?? 0,
                                    'para_birimi' => $data['para_birimi'] ?? 'TRY',
                                    'odeme_kanali' => $data['odeme_kanali'] ?? 'kasa',
                                    'kasa_hesap_id' => $data['kasa_hesap_id'] ?? null,
                                    'banka_hesap_id' => $data['banka_hesap_id'] ?? null,
                                    'aciklama' => $data['aciklama'] ?? null,
                                ]);

                                if (! (bool) ($data['finansa_isle'] ?? false)) {
                                    return null;
                                }

                                $finans = app(PersonelFinansHareketServisi::class)->maasOdemesiniFinansaIsle($odeme);

                                return 'Finans hareketi #'.$finans->id;
                            });

                            Notification::make()
                                ->title('Maaş ödemesi kaydedildi')
                                ->body($finansMetni)
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Maaş ödemesi kaydedilemedi')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('odeme_iptal')
                    ->label('Ödemeyi iptal et')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form(function (PersonelMaasHareketi $record): array {
                        $secenekler = $record->odemeler()
                            ->whereNotNull('finans_hareketi_id')
                            ->get(['id', 'tarih', 'tutar', 'finans_hareketi_id'])
                            ->mapWithKeys(fn (PersonelMaasOdemeKaydi $odeme): array => [
                                $odeme->id => '#'.$odeme->id.' · '.number_format((float) $odeme->tutar, 2, ',', '.').' · Finans #'.$odeme->finans_hareketi_id,
                            ])->all();

                        return [Forms\Components\Select::make('odeme_id')->label('İptal edilecek ödeme')->options($secenekler)->required()->searchable()];
                    })
                    ->visible(fn (PersonelMaasHareketi $record): bool => $this->odemeYetkisiVarMi()
                        && $record->odemeler()->whereNotNull('finans_hareketi_id')->exists())
                    ->action(function (PersonelMaasHareketi $record, array $data): void {
                        $odeme = $record->odemeler()->whereKey((int) ($data['odeme_id'] ?? 0))->firstOrFail();
                        app(PersonelFinansHareketServisi::class)->maasOdemesiniIptalEt($odeme, 'Personel maaş ödemesi iptali');
                        Notification::make()->title('Maaş ödemesi iptal edildi')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('id');
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function odemeFormu(): array
    {
        return [
            Forms\Components\DatePicker::make('tarih')
                ->label('Tarih')
                ->required(),
            Forms\Components\TextInput::make('tutar')
                ->label('Tutar')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('para_birimi')
                ->label('Para birimi')
                ->default('TRY')
                ->maxLength(3)
                ->required(),
            Forms\Components\Select::make('odeme_kanali')
                ->label('Ödeme kanalı')
                ->options([
                    'kasa' => 'Kasa',
                    'banka' => 'Banka',
                ])
                ->required(),
            Forms\Components\Select::make('kasa_hesap_id')
                ->label('Kasa hesabı')
                ->options(fn (): array => KasaHesabi::query()
                    ->where('firma_id', (int) $this->getOwnerRecord()->firma_id)
                    ->orderBy('ad')
                    ->pluck('ad', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('banka_hesap_id')
                ->label('Banka hesabı')
                ->options(fn (): array => BankaHesabi::query()
                    ->where('firma_id', (int) $this->getOwnerRecord()->firma_id)
                    ->orderBy('ad')
                    ->pluck('ad', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            Forms\Components\Toggle::make('finansa_isle')
                ->label('Finans hareketi oluştur')
                ->default(true),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->columnSpanFull(),
        ];
    }

    private function odemeYetkisiVarMi(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::MAAS_ODEME_YAP);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private function maliyetKalemleriFormu(): array
    {
        return [
            Forms\Components\Placeholder::make('bilgi')
                ->content('Tutarları onaylı bordrodan doğrulayarak girin. Sistem yasal SGK veya vergi oranı tahmini yapmaz.'),
            Forms\Components\TextInput::make('sgk_isveren_tutari')
                ->label('SGK işveren payı')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('issizlik_isveren_tutari')
                ->label('İşsizlik işveren payı')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('gelir_vergisi_tutari')
                ->label('Gelir vergisi')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('damga_vergisi_tutari')
                ->label('Damga vergisi')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\TextInput::make('diger_maliyet_tutari')
                ->label('Diğer işveren maliyeti')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Forms\Components\Textarea::make('maliyet_notu')
                ->label('Maliyet notu')
                ->helperText('Bordro dosyası, dönem veya hesaplama açıklaması ekleyebilirsiniz.')
                ->columnSpanFull(),
        ];
    }

    private function finansOzeti(PersonelMaasHareketi $record): string
    {
        $toplam = $record->odemeler->count();
        $islenen = $record->odemeler->whereNotNull('finans_hareketi_id')->count();

        return $toplam === 0 ? '-' : $islenen.'/'.$toplam;
    }
}
