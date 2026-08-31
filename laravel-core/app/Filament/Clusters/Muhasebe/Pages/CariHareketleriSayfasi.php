<?php

namespace App\Filament\Clusters\Muhasebe\Pages;

use App\Filament\Clusters\Muhasebe as MuhasebeCluster;
use App\Filament\Clusters\Muhasebe\Kaynaklar\MuhasebeSayfaErisimleri;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Services\TenantContextService;
use App\Support\MuhasebeYetkiSablonlari;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CariHareketleriSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MuhasebeSayfaErisimleri;

    /** @var array<int, FinansHareketi|null> */
    private array $finansCache = [];

    private ?CariBakiyeServisi $cariBakiyeServisi = null;

    /** @var array<int, array<int, array{yon:string, ad:string, para_birimi:string, tutar:string}>> */
    private array $hesapAkislariCache = [];

    protected static ?string $cluster = MuhasebeCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Cari Hareketleri';

    protected static ?string $slug = 'cari-yonetimi/cari-hareketleri';

    protected static string $view = 'filament.clusters.muhasebe.pages.cari-hareketleri';

    public function getTitle(): string|Htmlable
    {
        return 'Cari Hareketleri';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Cari Hareketleri';
    }

    public function getSubheading(): ?string
    {
        return 'Tüm cari hareket satırları (aktif kayıtlar). Filtrelerle daraltın.';
    }

    protected static function gerekliYetkiKodu(): string
    {
        return MuhasebeYetkiSablonlari::CARI_GORUNTULE;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected static function muhasebeSayfasiYetkiKodlari(): array
    {
        return [
            MuhasebeYetkiSablonlari::CARI_GORUNTULE,
            MuhasebeYetkiSablonlari::CARI_GUNCELLE,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cari Hareketleri')
            ->query(
                CariHareketi::query()
                    ->select([
                        'id',
                        'firma_id',
                        'cari_id',
                        'belge_turu',
                        'belge_id',
                        'islem_tarihi',
                        'borc',
                        'baz_borc',
                        'alacak',
                        'baz_alacak',
                        'para_birimi',
                        'baz_para_birimi',
                        'kur',
                        'vade_tarihi',
                        'aciklama',
                        'durum',
                    ])
                    ->with([
                        'cari:id,ad',
                        'fatura:id,firma_id,tur',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('İşlem tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cari.ad')
                    ->label('Cari')
                    ->width('14rem')
                    ->wrapHeader()
                    ->extraHeaderAttributes(['style' => 'width: 14rem; max-width: 14rem;'], merge: true)
                    ->extraCellAttributes(['style' => 'width: 14rem; max-width: 14rem;'], merge: true)
                    ->extraAttributes([
                        'class' => '!min-w-0 !max-w-[14rem] !overflow-hidden',
                        'style' => 'width: 14rem; max-width: 14rem; min-width: 0;',
                    ])
                    ->size('sm')
                    ->limit(32)
                    ->lineClamp(1)
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('belge_turu')
                    ->label('İşlem tipi')
                    ->formatStateUsing(function ($state, CariHareketi $record): string {
                        $enum = $state instanceof CariHareketBelgeTuru
                            ? $state
                            : CariHareketBelgeTuru::tryFrom((string) $state);

                        if ($enum === CariHareketBelgeTuru::Fatura) {
                            $faturaTuru = $record->fatura?->tur;

                            return $faturaTuru instanceof FaturaTuru
                                ? $faturaTuru->etiket()
                                : 'Fatura';
                        }

                        return $enum?->etiket() ?? '-';
                    })
                    ->badge()
                    ->color(function ($state): string {
                        $enum = $state instanceof CariHareketBelgeTuru
                            ? $state
                            : CariHareketBelgeTuru::tryFrom((string) $state);

                        return match ($enum) {
                            CariHareketBelgeTuru::Tahsilat => 'success',
                            CariHareketBelgeTuru::Odeme => 'warning',
                            CariHareketBelgeTuru::Fatura, CariHareketBelgeTuru::Satis => 'info',
                            CariHareketBelgeTuru::Iade => 'danger',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('fatura_tipi')
                    ->label('Fatura tipi')
                    ->getStateUsing(function (CariHareketi $record): ?string {
                        $tur = $record->fatura?->tur;

                        return $tur instanceof FaturaTuru ? $tur->etiket() : null;
                    })
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('finans_yonu')
                    ->label('Finans yönü')
                    ->getStateUsing(fn (CariHareketi $record): string => $this->finansYonuMetni($record))
                    ->width('16rem')
                    ->limit(44)
                    ->lineClamp(1)
                    ->extraHeaderAttributes(['style' => 'width: 16rem; max-width: 16rem;'], merge: true)
                    ->extraCellAttributes(['style' => 'width: 16rem; max-width: 16rem;'], merge: true)
                    ->extraAttributes([
                        'class' => '!min-w-0 !max-w-[16rem] !overflow-hidden',
                        'style' => 'width: 16rem; max-width: 16rem; min-width: 0;',
                    ])
                    ->tooltip(fn (CariHareketi $record): ?string => ($yon = trim($this->finansYonuDetayMetni($record))) !== '—' ? $yon : null),
                Tables\Columns\TextColumn::make('belge_id')
                    ->label('Referans ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kaynak_kanal')
                    ->label('Kaynak')
                    ->getStateUsing(fn (CariHareketi $r): string => $this->kaynakMetni($r))
                    ->limit(36)
                    ->tooltip(fn (CariHareketi $r): ?string => $this->kaynakMetni($r))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('hedef_kanal')
                    ->label('Hedef')
                    ->getStateUsing(fn (CariHareketi $r): string => $this->hedefMetni($r))
                    ->limit(36)
                    ->tooltip(fn (CariHareketi $r): ?string => $this->hedefMetni($r))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('borc')
                    ->label('Borç')
                    ->formatStateUsing(fn ($state, CariHareketi $r) => number_format((float) $state, 2, ',', '.').' '.$r->para_birimi)
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('alacak')
                    ->label('Alacak')
                    ->formatStateUsing(fn ($state, CariHareketi $r) => number_format((float) $state, 2, ',', '.').' '.$r->para_birimi)
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('baz_borc')
                    ->label('Baz borç')
                    ->formatStateUsing(fn ($state, CariHareketi $r): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($r->baz_para_birimi ?: config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('baz_alacak')
                    ->label('Baz alacak')
                    ->formatStateUsing(fn ($state, CariHareketi $r): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($r->baz_para_birimi ?: config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('kur')
                    ->label('Kur')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : number_format((float) $state, 8, ',', '.'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('net')
                    ->label('Net (B-A)')
                    ->getStateUsing(fn (CariHareketi $record): string => $this->bakiyeServisi()->netBakiye((string) $record->borc, (string) $record->alacak))
                    ->formatStateUsing(fn ($state, CariHareketi $record) => number_format(abs((float) $state), 2, ',', '.').' '.$record->para_birimi.(((float) $state) < 0 ? ' (Alacak)' : (((float) $state) > 0 ? ' (Borç)' : '')))
                    ->color(fn ($state): string => ((float) $state) < 0 ? 'success' : (((float) $state) > 0 ? 'danger' : 'gray'))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para birimi')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof CariHareketDurumu ? $state->value : (string) $state) === CariHareketDurumu::Iptal->value ? 'İptal edildi' : 'Aktif')
                    ->color(fn ($state): string => ($state instanceof CariHareketDurumu ? $state->value : (string) $state) === CariHareketDurumu::Iptal->value ? 'danger' : 'success'),
            ])
            ->recordClasses(fn (CariHareketi $record): string => ($record->durum instanceof CariHareketDurumu ? $record->durum : CariHareketDurumu::tryFrom((string) $record->durum)) === CariHareketDurumu::Iptal
                ? 'bg-danger-50/60 text-danger-900 line-through decoration-danger-500 decoration-2 dark:bg-danger-500/10 dark:text-danger-100'
                : '')
            ->defaultSort('islem_tarihi', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('cari_id')
                    ->label('Cari')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->cariAramaSonuclari($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->cariEtiketi((int) $value)),
                Tables\Filters\SelectFilter::make('belge_turu')
                    ->label('İşlem tipi')
                    ->options(collect(CariHareketBelgeTuru::cases())->mapWithKeys(fn (CariHareketBelgeTuru $e) => [$e->value => $e->etiket()])),
                Tables\Filters\Filter::make('islem_tarihi')
                    ->form([
                        Forms\Components\DatePicker::make('bas')
                            ->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bit')
                            ->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['bas'] ?? null, fn (Builder $q, $d) => $q->where('islem_tarihi', '>=', (string) $d.' 00:00:00'))
                            ->when($data['bit'] ?? null, fn (Builder $q, $d) => $q->where('islem_tarihi', '<=', (string) $d.' 23:59:59'));
                    }),
            ])
            ->deferLoading()
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @return array<int, string>
     */
    private function cariAramaSonuclari(string $search): array
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1) {
            return [];
        }

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->when(trim($search) !== '', function (Builder $query) use ($search): void {
                $aranan = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
                $query->where(function (Builder $q) use ($aranan): void {
                    $q->where('ad', 'like', $aranan)
                        ->orWhere('kod', 'like', $aranan);
                });
            })
            ->orderBy('ad')
            ->limit(50)
            ->get(['id', 'ad', 'kod'])
            ->mapWithKeys(fn (Cari $cari): array => [
                (int) $cari->id => trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad),
            ])
            ->all();
    }

    private function cariEtiketi(int $cariId): ?string
    {
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
        if ($firmaId < 1 || $cariId < 1) {
            return null;
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first(['id', 'ad', 'kod']);

        if (! $cari instanceof Cari) {
            return null;
        }

        return trim(($cari->kod ? $cari->kod.' - ' : '').$cari->ad);
    }

    private function finansKaydiBul(CariHareketi $cariHareket): ?FinansHareketi
    {
        $belgeTuru = $cariHareket->belge_turu instanceof CariHareketBelgeTuru
            ? $cariHareket->belge_turu
            : CariHareketBelgeTuru::tryFrom((string) $cariHareket->belge_turu);

        if (! in_array($belgeTuru, [CariHareketBelgeTuru::Tahsilat, CariHareketBelgeTuru::Odeme], true)) {
            return null;
        }

        $finansId = (int) ($cariHareket->belge_id ?? 0);
        if ($finansId < 1) {
            return null;
        }

        if (! array_key_exists($finansId, $this->finansCache)) {
            $this->finansCache[$finansId] = FinansHareketi::query()
                ->with([
                    'cari:id,ad',
                    'kasaHareketleri.kasaHesabi:id,ad',
                    'bankaHareketleri.bankaHesabi:id,ad',
                    'posHareketleri.posHesabi:id,ad',
                ])
                ->find($finansId);
        }

        return $this->finansCache[$finansId];
    }

    private function bakiyeServisi(): CariBakiyeServisi
    {
        return $this->cariBakiyeServisi ??= app(CariBakiyeServisi::class);
    }

    /**
     * @return array<int, array{yon:string, ad:string, para_birimi:string, tutar:string}>
     */
    private function hesapAkislari(FinansHareketi $r): array
    {
        $finansId = (int) $r->getKey();
        if ($finansId > 0 && array_key_exists($finansId, $this->hesapAkislariCache)) {
            return $this->hesapAkislariCache[$finansId];
        }

        $liste = [];

        foreach ($r->kasaHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'Kasa: '.(($h->kasaHesabi?->ad) ?: ('#'.$h->kasa_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        foreach ($r->bankaHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'Banka: '.(($h->bankaHesabi?->ad) ?: ('#'.$h->banka_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        foreach ($r->posHareketleri as $h) {
            $liste[] = [
                'yon' => ((float) $h->tutar) < 0 ? 'cikis' : 'giris',
                'ad' => 'POS: '.(($h->posHesabi?->ad) ?: ('#'.$h->pos_hesap_id)),
                'para_birimi' => strtoupper((string) ($h->para_birimi ?: 'TRY')),
                'tutar' => number_format(abs((float) $h->tutar), 2, ',', '.'),
            ];
        }

        if ($finansId > 0) {
            $this->hesapAkislariCache[$finansId] = $liste;
        }

        return $liste;
    }

    private function kaynakMetni(CariHareketi $cariHareket): string
    {
        $finans = $this->finansKaydiBul($cariHareket);
        if (! $finans) {
            return '-';
        }

        $akimlar = $this->hesapAkislari($finans);
        $cikislar = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'cikis'));
        if ($cikislar !== []) {
            return implode(' | ', array_map(fn (array $x): string => $x['ad'].' '.$x['tutar'].' '.$x['para_birimi'], $cikislar));
        }

        $tur = $finans->tur instanceof FinansHareketTuru ? $finans->tur : FinansHareketTuru::tryFrom((string) $finans->tur);
        if ($tur === FinansHareketTuru::Tahsilat && $finans->cari) {
            return 'Cari: '.$finans->cari->ad.' '.number_format(abs((float) $finans->tutar), 2, ',', '.').' '.strtoupper((string) ($finans->para_birimi ?: 'TRY'));
        }

        return '-';
    }

    private function hedefMetni(CariHareketi $cariHareket): string
    {
        $finans = $this->finansKaydiBul($cariHareket);
        if (! $finans) {
            return '-';
        }

        $akimlar = $this->hesapAkislari($finans);
        $girisler = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'giris'));
        if ($girisler !== []) {
            return implode(' | ', array_map(fn (array $x): string => $x['ad'].' '.$x['tutar'].' '.$x['para_birimi'], $girisler));
        }

        $tur = $finans->tur instanceof FinansHareketTuru ? $finans->tur : FinansHareketTuru::tryFrom((string) $finans->tur);
        if ($tur === FinansHareketTuru::Odeme && $finans->cari) {
            return 'Cari: '.$finans->cari->ad.' '.number_format(abs((float) $finans->tutar), 2, ',', '.').' '.strtoupper((string) ($finans->para_birimi ?: 'TRY'));
        }

        return '-';
    }

    private function finansYonuMetni(CariHareketi $cariHareket): string
    {
        $finans = $this->finansKaydiBul($cariHareket);
        if (! $finans) {
            return '—';
        }

        $akimlar = $this->hesapAkislari($finans);
        $cikislar = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'cikis'));
        $girisler = array_values(array_filter($akimlar, fn (array $x): bool => $x['yon'] === 'giris'));

        $kaynak = $cikislar[0]['ad'] ?? null;
        $hedef = $girisler[0]['ad'] ?? null;
        $tur = $finans->tur instanceof FinansHareketTuru ? $finans->tur : FinansHareketTuru::tryFrom((string) $finans->tur);

        if (! $kaynak && $tur === FinansHareketTuru::Tahsilat && $finans->cari) {
            $kaynak = 'Cari: '.$finans->cari->ad;
        }

        if (! $hedef && $tur === FinansHareketTuru::Odeme && $finans->cari) {
            $hedef = 'Cari: '.$finans->cari->ad;
        }

        if (! $kaynak && ! $hedef) {
            return '—';
        }

        return $this->finansYonuTarafiKisalt($kaynak).' → '.$this->finansYonuTarafiKisalt($hedef);
    }

    private function finansYonuDetayMetni(CariHareketi $cariHareket): string
    {
        $kaynak = trim($this->kaynakMetni($cariHareket));
        $hedef = trim($this->hedefMetni($cariHareket));

        if (($kaynak === '' || $kaynak === '-') && ($hedef === '' || $hedef === '-')) {
            return '—';
        }

        return ($kaynak !== '' && $kaynak !== '-' ? $kaynak : '—').' → '.($hedef !== '' && $hedef !== '-' ? $hedef : '—');
    }

    private function finansYonuTarafiKisalt(?string $metin): string
    {
        $metin = trim((string) $metin);
        if ($metin === '' || $metin === '-' || $metin === '—') {
            return '—';
        }

        if (Str::startsWith($metin, 'Cari:')) {
            return 'Cari';
        }

        return Str::limit($metin, 18, '...');
    }
}
