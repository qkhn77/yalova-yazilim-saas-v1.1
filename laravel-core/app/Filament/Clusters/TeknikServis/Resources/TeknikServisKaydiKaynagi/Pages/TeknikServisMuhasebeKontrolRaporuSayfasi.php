<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKaydiKaynagi;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeIslemTipi;
use App\TeknikServis\Enumlar\TeknikServisMuhasebeSenkronDurumu;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class TeknikServisMuhasebeKontrolRaporuSayfasi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = TeknikServisKaydiKaynagi::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Muhasebe kontrolü';

    protected static string $view = 'filament.clusters.teknik-servis.resources.teknik-servis-kaydi-kaynagi.pages.muhasebe-kontrol-raporu';

    public function getTitle(): string|Htmlable
    {
        return 'Muhasebe kontrolü';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Muhasebe kontrolü';
    }

    public function getSubheading(): ?string
    {
        return 'Fatura, tahsilat ve teknik servis bağlantılarında müdahale gerektirebilecek kayıtlar.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('servisListesi')
                ->label('Servis kayıtları')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(TeknikServisKaydiKaynagi::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::sorunluKayitSorgusu())
            ->columns([
                Tables\Columns\TextColumn::make('fis_no')
                    ->label('Fiş no')
                    ->searchable()
                    ->sortable()
                    ->url(fn (TeknikServisKaydi $record): string => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('servisDurumu.ad')
                    ->label('Servis durumu')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('muhasebe_sorunlari')
                    ->label('Kontrol sonucu')
                    ->state(fn (TeknikServisKaydi $record): HtmlString => self::sorunMetni($record))
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('fatura_bilgisi')
                    ->label('Fatura')
                    ->state(fn (TeknikServisKaydi $record): HtmlString => self::faturaMetni($record))
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Son güncelleme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Aç')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (TeknikServisKaydi $record): string => TeknikServisKaydiKaynagi::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('updated_at', 'desc')
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function sorunluKayitSorgusu(): Builder
    {
        return TeknikServisKaydi::query()
            ->select('teknik_servis_kayitlari.*')
            ->with([
                'cari:id,ad',
                'servisDurumu:id,ad,kod,is_teslim_edildi',
            ])
            ->where(function (Builder $query): void {
                $query
                    ->where(static fn (Builder $q): Builder => $q
                        ->whereExists(self::kalemVarAltSorgusu())
                        ->whereNotExists(self::satisFaturasiVarAltSorgusu()))
                    ->orWhere(static fn (Builder $q): Builder => $q
                        ->whereHas('servisDurumu', fn (Builder $durum): Builder => $durum->where('is_teslim_edildi', true))
                        ->whereNotExists(self::basariliSatisFaturasiVarAltSorgusu()))
                    ->orWhereExists(self::faturasizAktifTahsilatAltSorgusu())
                    ->orWhereExists(self::kopukSatisFaturaBaglantisiAltSorgusu())
                    ->orWhereExists(self::faturaKalemUyumsuzluguAltSorgusu());
            });
    }

    private static function sorunMetni(TeknikServisKaydi $record): HtmlString
    {
        $sorunlar = [];

        if (self::kalemVarMi($record) && ! self::satisFaturasiId($record)) {
            $sorunlar[] = 'Stok kalemi var, bağlı satış faturası yok.';
        }

        if ((bool) ($record->servisDurumu?->is_teslim_edildi ?? false) && ! self::basariliSatisFaturaBaglantisiVarMi($record)) {
            $sorunlar[] = 'Teslim edildi, ancak başarılı satış faturası bağlantısı yok.';
        }

        if (self::faturasizAktifTahsilatSayisi($record) > 0) {
            $sorunlar[] = 'Aktif tahsilatlardan bazıları faturaya bağlı değil.';
        }

        if (self::kopukSatisFaturaBaglantisiVarMi($record)) {
            $sorunlar[] = 'Muhasebe bağlantısı fatura kaydı bulunmayan bir ID gösteriyor.';
        }

        if (self::faturaKalemUyumsuzluguVarMi($record)) {
            $sorunlar[] = 'Servis kalemleri ile fatura kalemleri aynı görünmüyor.';
        }

        if ($sorunlar === []) {
            $sorunlar[] = 'Kontrol edilecek özel durum yok.';
        }

        return new HtmlString('<span style="color:#b91c1c;font-weight:600;">'.e(implode(' ', $sorunlar)).'</span>');
    }

    private static function faturaMetni(TeknikServisKaydi $record): HtmlString
    {
        $fatura = self::satisFaturasi($record);
        if (! $fatura) {
            return new HtmlString('<span style="color:#64748b;">Yok</span>');
        }

        $faturaNo = trim((string) ($fatura->fatura_no ?? '')) ?: '#'.$fatura->getKey();
        $metin = $faturaNo.' | '.self::enumMetni($fatura->tur).' / '.self::enumMetni($fatura->durum);
        $url = FaturaKaynagi::getUrl('edit', ['record' => $fatura]);

        return new HtmlString('<a href="'.e($url).'" target="_blank" style="text-decoration:underline;">'.e($metin).'</a>');
    }

    private static function kalemVarMi(TeknikServisKaydi $record): bool
    {
        return $record->kalemler()->exists();
    }

    private static function satisFaturasiId(TeknikServisKaydi $record): ?int
    {
        $id = DB::table('teknik_servis_muhasebe_baglantilari')
            ->where('firma_id', (int) $record->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $record->getKey())
            ->where('islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->orderByDesc('id')
            ->value('satis_faturasi_id');

        return $id ? (int) $id : null;
    }

    private static function satisFaturasi(TeknikServisKaydi $record): ?\App\Models\Muhasebe\Fatura
    {
        $faturaId = self::satisFaturasiId($record);
        if (! $faturaId) {
            return null;
        }

        return \App\Models\Muhasebe\Fatura::query()
            ->where('firma_id', (int) $record->firma_id)
            ->whereKey($faturaId)
            ->first(['id', 'firma_id', 'fatura_no', 'tur', 'durum']);
    }

    private static function basariliSatisFaturaBaglantisiVarMi(TeknikServisKaydi $record): bool
    {
        return DB::table('teknik_servis_muhasebe_baglantilari as b')
            ->join('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->where('b.firma_id', (int) $record->firma_id)
            ->where('b.teknik_servis_kaydi_id', (int) $record->getKey())
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->where('b.senkron_durumu', TeknikServisMuhasebeSenkronDurumu::Basarili->value)
            ->whereNull('f.deleted_at')
            ->exists();
    }

    private static function faturasizAktifTahsilatSayisi(TeknikServisKaydi $record): int
    {
        return DB::table('teknik_servis_tahsilatlari')
            ->where('firma_id', (int) $record->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $record->getKey())
            ->where('durum', 'aktif')
            ->whereNull('satis_faturasi_id')
            ->count();
    }

    private static function kopukSatisFaturaBaglantisiVarMi(TeknikServisKaydi $record): bool
    {
        return DB::table('teknik_servis_muhasebe_baglantilari as b')
            ->leftJoin('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->where('b.firma_id', (int) $record->firma_id)
            ->where('b.teknik_servis_kaydi_id', (int) $record->getKey())
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->whereNotNull('b.satis_faturasi_id')
            ->whereNull('f.id')
            ->exists();
    }

    private static function faturaKalemUyumsuzluguVarMi(TeknikServisKaydi $record): bool
    {
        $faturaId = self::satisFaturasiId($record);
        if (! $faturaId) {
            return false;
        }

        $servisOzet = DB::table('teknik_servis_kalemleri')
            ->where('firma_id', (int) $record->firma_id)
            ->where('teknik_servis_kaydi_id', (int) $record->getKey())
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as adet, ROUND(COALESCE(SUM(miktar * birim_fiyat), 0), 2) as toplam')
            ->first();

        $faturaOzet = DB::table('fatura_kalemleri')
            ->where('firma_id', (int) $record->firma_id)
            ->where('fatura_id', $faturaId)
            ->selectRaw('COUNT(*) as adet, ROUND(COALESCE(SUM(satir_toplami), 0), 2) as toplam')
            ->first();

        return (int) ($servisOzet->adet ?? 0) !== (int) ($faturaOzet->adet ?? 0)
            || number_format((float) ($servisOzet->toplam ?? 0), 2, '.', '') !== number_format((float) ($faturaOzet->toplam ?? 0), 2, '.', '');
    }

    private static function kalemVarAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_kalemleri as k')
            ->whereColumn('k.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('k.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->whereNull('k.deleted_at');
    }

    private static function satisFaturasiVarAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_muhasebe_baglantilari as b')
            ->join('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->whereColumn('b.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('b.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->whereNull('f.deleted_at');
    }

    private static function basariliSatisFaturasiVarAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_muhasebe_baglantilari as b')
            ->join('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->whereColumn('b.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('b.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->where('b.senkron_durumu', TeknikServisMuhasebeSenkronDurumu::Basarili->value)
            ->whereNull('f.deleted_at');
    }

    private static function faturasizAktifTahsilatAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_tahsilatlari as t')
            ->whereColumn('t.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('t.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->where('t.durum', 'aktif')
            ->whereNull('t.satis_faturasi_id')
            ->whereNull('t.deleted_at');
    }

    private static function kopukSatisFaturaBaglantisiAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_muhasebe_baglantilari as b')
            ->leftJoin('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->whereColumn('b.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('b.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->whereNotNull('b.satis_faturasi_id')
            ->whereNull('f.id');
    }

    private static function faturaKalemUyumsuzluguAltSorgusu(): \Closure
    {
        return static fn ($query) => $query
            ->selectRaw('1')
            ->from('teknik_servis_muhasebe_baglantilari as b')
            ->join('faturalar as f', 'f.id', '=', 'b.satis_faturasi_id')
            ->whereColumn('b.teknik_servis_kaydi_id', 'teknik_servis_kayitlari.id')
            ->whereColumn('b.firma_id', 'teknik_servis_kayitlari.firma_id')
            ->where('b.islem_tipi', TeknikServisMuhasebeIslemTipi::Satis->value)
            ->whereNull('f.deleted_at')
            ->where(function ($q): void {
                $q->whereRaw('(SELECT COUNT(*) FROM teknik_servis_kalemleri k WHERE k.teknik_servis_kaydi_id = teknik_servis_kayitlari.id AND k.firma_id = teknik_servis_kayitlari.firma_id AND k.deleted_at IS NULL) != (SELECT COUNT(*) FROM fatura_kalemleri fk WHERE fk.fatura_id = f.id AND fk.firma_id = f.firma_id)')
                    ->orWhereRaw('ROUND((SELECT COALESCE(SUM(k.miktar * k.birim_fiyat), 0) FROM teknik_servis_kalemleri k WHERE k.teknik_servis_kaydi_id = teknik_servis_kayitlari.id AND k.firma_id = teknik_servis_kayitlari.firma_id AND k.deleted_at IS NULL), 2) != ROUND((SELECT COALESCE(SUM(fk.satir_toplami), 0) FROM fatura_kalemleri fk WHERE fk.fatura_id = f.id AND fk.firma_id = f.firma_id), 2)');
            });
    }

    private static function enumMetni(mixed $deger): string
    {
        $metin = $deger instanceof \BackedEnum ? (string) $deger->value : (string) $deger;
        $metin = str_replace('_', ' ', $metin);

        return $metin !== '' ? mb_convert_case($metin, MB_CASE_TITLE, 'UTF-8') : '-';
    }
}
