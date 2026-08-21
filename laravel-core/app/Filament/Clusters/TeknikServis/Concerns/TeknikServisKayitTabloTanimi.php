<?php

namespace App\Filament\Clusters\TeknikServis\Concerns;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariAcikSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariFiyatVerilenSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariTezgahtaSayfasi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages\TeknikServisKayitlariYeniSayfasi;
use App\Filament\Clusters\TeknikServis\Pages\TeknikServisDashboardSayfasi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Enumlar\OdemeDurumu;
use App\TeknikServis\Enumlar\Oncelik;
use App\TeknikServis\Enumlar\ServisTipi;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Livewire\Component as LivewireComponent;

/**
 * {@see TeknikServisKaydiKaynagi} tablo kolonlari - tum preset listeleri ayni yapiyi kullanir.
 */
final class TeknikServisKayitTabloTanimi
{
    public static function tabloyuUygula(Table $tablo): Table
    {
        return $tablo
            ->deferLoading()
            ->recordUrl(fn (TeknikServisKaydi $record): string => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $record]))
            ->modifyQueryUsing(fn (Builder $query): Builder => self::listeSorgusunuOptimizeEt($query))
            ->columns([
                Tables\Columns\TextColumn::make('kabul_tarihi')
                    ->label('Kabul tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cari_adi')
                    ->label('Cari')
                    ->searchable(['ts_cari.ad'])
                    ->sortable(['ts_cari.ad']),
                Tables\Columns\TextColumn::make('musteri_tel')
                    ->label('Tel')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fis_no')
                    ->label("Fi\u{015F} No")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cihaz_adi')
                    ->label('Cihaz')
                    ->placeholder('-')
                    ->sortable(['ts_cihaz.ad']),
                Tables\Columns\TextColumn::make('marka_adi')
                    ->label('Marka')
                    ->placeholder('-')
                    ->sortable(['ts_marka.ad']),
                Tables\Columns\TextColumn::make('servis_tipi')
                    ->label('Servis tipi')
                    ->formatStateUsing(function ($state): string {
                        $durum = $state instanceof ServisTipi ? $state : ServisTipi::tryFrom((string) $state);

                        return $durum instanceof ServisTipi ? match ($durum) {
                            ServisTipi::ArizaliCihaz => "Ar\u{0131}zal\u{0131} cihaz",
                            ServisTipi::DisServis => "D\u{0131}\u{015F} servis",
                            ServisTipi::Bakim => "Bak\u{0131}m",
                        } : '-';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('servis_durumu_adi')
                    ->label('Servis durumu')
                    ->visible(fn (LivewireComponent $livewire): bool => ! ($livewire instanceof TeknikServisKayitlariFiyatVerilenSayfasi))
                    ->sortable(['ts_durum.ad']),
                Tables\Columns\TextColumn::make('oncelik')
                    ->label("\u{00D6}ncelik")
                    ->formatStateUsing(function ($state): string {
                        $o = $state instanceof Oncelik ? $state : Oncelik::tryFrom((string) $state);

                        return $o instanceof Oncelik ? match ($o) {
                            Oncelik::Dusuk => "D\u{00FC}\u{015F}\u{00FC}k",
                            Oncelik::Normal => 'Normal',
                            Oncelik::Acil => 'Acil',
                        } : '-';
                    })
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('toplam_tutar')
                    ->label('Toplam')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible(fn (LivewireComponent $livewire): bool => ! self::sadeListeSayfasiMi($livewire) && ! self::acikListeSayfasiMi($livewire))
                    ->sortable(),
                Tables\Columns\TextColumn::make('odenen_tutar')
                    ->label("\u{00D6}denen")
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->visible(fn (LivewireComponent $livewire): bool => ! self::sadeListeSayfasiMi($livewire) && ! self::acikListeSayfasiMi($livewire))
                    ->sortable(),
                Tables\Columns\TextColumn::make('odeme_durumu')
                    ->label("\u{00D6}deme")
                    ->formatStateUsing(function ($state): string {
                        $d = $state instanceof OdemeDurumu ? $state : OdemeDurumu::tryFrom((string) $state);

                        return $d instanceof OdemeDurumu ? match ($d) {
                            OdemeDurumu::Odenmedi => "\u{00D6}denmedi",
                            OdemeDurumu::Kismi => "K\u{0131}smi",
                            OdemeDurumu::Odendi => "\u{00D6}dendi",
                            OdemeDurumu::Iade => "\u{0130}ade",
                            OdemeDurumu::Iptal => "\u{0130}ptal",
                        } : '-';
                    })
                    ->badge()
                    ->visible(fn (LivewireComponent $livewire): bool => ! self::sadeListeSayfasiMi($livewire) && ! self::acikListeSayfasiMi($livewire))
                    ->sortable(),
                Tables\Columns\TextColumn::make('teslim_tarihi')
                    ->label('Teslim')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->visible(fn (LivewireComponent $livewire): bool => ! self::sadeListeSayfasiMi($livewire) && ! self::acikListeSayfasiMi($livewire))
                    ->sortable(),
                Tables\Columns\TextColumn::make('teklif_tutari')
                    ->label('Teklif tutarı')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->placeholder('-')
                    ->visible(fn (LivewireComponent $livewire): bool => $livewire instanceof TeknikServisKayitlariFiyatVerilenSayfasi || $livewire instanceof TeknikServisKayitlariAcikSayfasi)
                    ->sortable(),
            ])
            ->defaultSort('kabul_tarihi', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function listeSorgusunuOptimizeEt(Builder $query): Builder
    {
        $tablo = (new TeknikServisKaydi)->getTable();

        return $query
            ->select([
                $tablo.'.id',
                $tablo.'.firma_id',
                $tablo.'.fis_no',
                $tablo.'.kabul_tarihi',
                $tablo.'.cari_id',
                $tablo.'.musteri_tel',
                $tablo.'.cihaz_id',
                $tablo.'.marka_id',
                $tablo.'.servis_tipi',
                $tablo.'.servis_durumu_id',
                $tablo.'.oncelik',
                $tablo.'.toplam_tutar',
                $tablo.'.odenen_tutar',
                $tablo.'.odeme_durumu',
                $tablo.'.teslim_tarihi',
                $tablo.'.teklif_tutari',
            ])
            ->addSelect([
                'ts_cari.ad as cari_adi',
                'ts_cihaz.ad as cihaz_adi',
                'ts_marka.ad as marka_adi',
                'ts_durum.ad as servis_durumu_adi',
            ])
            ->leftJoin('cariler as ts_cari', function (JoinClause $join) use ($tablo): void {
                $join->on('ts_cari.id', '=', $tablo.'.cari_id')
                    ->whereNull('ts_cari.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_cihazlar as ts_cihaz', function (JoinClause $join) use ($tablo): void {
                $join->on('ts_cihaz.id', '=', $tablo.'.cihaz_id')
                    ->whereNull('ts_cihaz.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_markalar as ts_marka', function (JoinClause $join) use ($tablo): void {
                $join->on('ts_marka.id', '=', $tablo.'.marka_id')
                    ->whereNull('ts_marka.deleted_at');
            })
            ->leftJoin('teknik_servis_tanim_servis_durumlari as ts_durum', function (JoinClause $join) use ($tablo): void {
                $join->on('ts_durum.id', '=', $tablo.'.servis_durumu_id')
                    ->whereNull('ts_durum.deleted_at');
            });
    }

    private static function sadeListeSayfasiMi(LivewireComponent $livewire): bool
    {
        return $livewire instanceof TeknikServisKayitlariYeniSayfasi
            || $livewire instanceof TeknikServisKayitlariTezgahtaSayfasi
            || $livewire instanceof TeknikServisKayitlariFiyatVerilenSayfasi
            || $livewire instanceof TeknikServisKayitlariAcikSayfasi
            || $livewire instanceof TeknikServisDashboardSayfasi;
    }

    private static function acikListeSayfasiMi(LivewireComponent $livewire): bool
    {
        return $livewire instanceof TeknikServisKayitlariAcikSayfasi
            || $livewire instanceof TeknikServisDashboardSayfasi;
    }
}
