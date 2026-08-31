<?php

namespace App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Pages\CariEkstreSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\TumFaturalarSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi;
use App\Filament\Clusters\Muhasebe\Resources\ParaBirimiTanimKaynagi;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\SekreterGorevi;
use App\Models\SekreterRandevusu;
use App\Muhasebe\Enumlar\CariHareketBelgeTuru;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Filament\Clusters\Muhasebe\Pages\OdemeOlusturSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\TahsilatOlusturSayfasi;
use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ViewCari extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CariKartiKaynagi::class;

    protected static ?string $title = 'Cari detayı';

    protected static string $view = 'filament.clusters.muhasebe.resources.cari-karti-kaynagi.pages.view-cari';

    /** @var array<int, array{para_birimi: string, toplam_borc: string, toplam_alacak: string, bakiye: string}> */
    public array $bakiyeOzetleri = [];

    public string $paraBirimiEtiketi = 'TRY';

    public string $bazParaBirimi = 'TRY';

    /** @var array{borc: string, alacak: string, bakiye: string} */
    public array $bazBakiyeOzet = ['borc' => '0.00', 'alacak' => '0.00', 'bakiye' => '0.00'];

    /** Üst bilgi kartlarında gösterilen, cari para birimine çevrilmiş özet. */
    public array $cariParaBakiyeOzet = ['toplam_borc' => '0.00', 'toplam_alacak' => '0.00', 'bakiye' => '0.00'];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->bakiyeOzetleri = app(CariBakiyeServisi::class)
            ->paraBirimiOzetleri((int) $this->record->firma_id, (int) $this->record->getKey())
            ->map(static fn (object $satir): array => [
                'para_birimi' => (string) $satir->para_birimi,
                'toplam_borc' => (string) $satir->toplam_borc,
                'toplam_alacak' => (string) $satir->toplam_alacak,
                'bakiye' => (string) $satir->bakiye,
            ])
            ->values()
            ->all();

        $paraBirimi = strtoupper((string) ($this->record->para_birimi ?: 'TRY'));
        $this->paraBirimiEtiketi = CariKartiKaynagi::paraBirimiSecenekleriForFirma((int) $this->record->firma_id)[$paraBirimi] ?? $paraBirimi;
        $this->bazParaBirimi = strtoupper((string) config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'));
        $baz = CariHareketi::query()
            ->where('firma_id', (int) $this->record->firma_id)
            ->where('cari_id', (int) $this->record->getKey())
            ->where('durum', CariHareketDurumu::Aktif)
            ->selectRaw('COALESCE(SUM(COALESCE(baz_borc, 0)), 0) as borc, COALESCE(SUM(COALESCE(baz_alacak, 0)), 0) as alacak')
            ->first();
        $this->bazBakiyeOzet = [
            'borc' => (string) ($baz?->borc ?? '0.00'),
            'alacak' => (string) ($baz?->alacak ?? '0.00'),
            'bakiye' => app(CariBakiyeServisi::class)->netBakiye((string) ($baz?->borc ?? '0'), (string) ($baz?->alacak ?? '0')),
        ];
        $this->cariParaBakiyeOzet = $this->cariParaBirimiOzetiniHesapla();

        if (CariKartiKaynagi::detayModu()) {
            $this->record->loadMissing(['firma:id,ad']);
        }
    }

    protected function fillForm(): void
    {
        if (CariKartiKaynagi::detayModu()) {
            parent::fillForm();
        }
    }

    public function getTitle(): string|Htmlable
    {
        return (string) ($this->record?->ad ?: 'Cari detayı');
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): ?string
    {
        if (! $this->record) {
            return null;
        }

        $bakiye = $this->cariBakiyeSatiri();

        if (! CariKartiKaynagi::detayModu()) {
            $yon = $this->bakiyeYonEtiketi($bakiye['bakiye']);

            return 'Aktif cari · '.($this->record->kod ?: '-').' · '.$this->cariTuruMetni().' · '.$this->paraBirimiMetni().' · Bakiye: '.$this->bakiyeYazi($bakiye['bakiye'], (string) ($this->record->para_birimi ?: 'TRY')).($yon !== '' ? ' ('.$yon.')' : '');
        }

        return 'Kod: '.($this->record->kod ?: '-').' · Ana para birimi: '.$this->paraBirimiMetni();
    }

    protected function getHeaderActions(): array
    {
        $detayModu = CariKartiKaynagi::detayModu();

        if (! $detayModu) {
            return [];
        }

        $actions = [
            Actions\Action::make('duzenle')
                ->label('Düzenle')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => CariKartiKaynagi::getUrl('edit', ['record' => $this->record?->getKey()])),
            Actions\Action::make('cariler')
                ->label('Cariler')
                ->icon('heroicon-o-arrow-left')
                ->url(CariKartiKaynagi::getUrl('index'))
                ->color('gray'),
        ];

        if ($detayModu) {
            array_unshift($actions, Actions\Action::make('hizli_gorunum')
                ->label('Hızlı Görünüm')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(fn (): string => CariKartiKaynagi::getUrl('view', ['record' => $this->record?->getKey()])));
        }

        return $actions;
    }

    public function cariTuruMetni(): string
    {
        $tur = $this->record?->tur;

        return $tur instanceof CariTuru ? $tur->etiket() : '-';
    }

    public function cariDurumuMetni(): string
    {
        return match ($this->record?->durum) {
            CariDurumu::Aktif => 'Aktif',
            CariDurumu::Pasif => 'Pasif',
            default => '-',
        };
    }

    public function paraYazi(mixed $tutar): string
    {
        $pb = strtoupper((string) ($this->record?->para_birimi ?: 'TRY'));

        return $this->paraYaziPara($tutar, $pb);
    }

    public function paraYaziPara(mixed $tutar, string $paraBirimi): string
    {
        return number_format((float) ($tutar ?? 0), 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    public function bakiyeYazi(mixed $tutar, string $paraBirimi): string
    {
        return number_format(abs((float) ($tutar ?? 0)), 2, ',', '.').' '.strtoupper($paraBirimi ?: 'TRY');
    }

    public function bakiyeYonEtiketi(mixed $tutar): string
    {
        return bccomp((string) ($tutar ?? 0), '0', 2) > 0
            ? 'Borç'
            : (bccomp((string) ($tutar ?? 0), '0', 2) < 0 ? 'Alacak' : '');
    }

    /** @return array<int, array{para_birimi: string, toplam_borc: string, toplam_alacak: string, bakiye: string}> */
    public function cariHesapParaBakiyeSatirlari(): array
    {
        return collect($this->bakiyeOzetleri)
            ->values()
            ->all();
    }

    /** @deprecated Yeni ad: cariHesapParaBakiyeSatirlari. */
    public function digerCariBakiyeSatirlari(): array
    {
        return $this->cariHesapParaBakiyeSatirlari();
    }

    /** @return array{para_birimi: string, toplam_borc: string, toplam_alacak: string, bakiye: string} */
    public function cariBakiyeSatiri(): array
    {
        $pb = strtoupper((string) ($this->record?->para_birimi ?: 'TRY'));

        return collect($this->bakiyeOzetleri)->firstWhere('para_birimi', $pb) ?? [
            'para_birimi' => $pb,
            'toplam_borc' => '0.00',
            'toplam_alacak' => '0.00',
            'bakiye' => '0.00',
        ];
    }

    /** @return array{toplam_borc:string, toplam_alacak:string, bakiye:string} */
    private function cariParaBirimiOzetiniHesapla(): array
    {
        $hedef = strtoupper((string) ($this->record?->para_birimi ?: 'TRY'));
        $baz = strtoupper((string) ($this->bazParaBirimi ?: 'TRY'));

        if ($hedef === $baz) {
            return [
                'toplam_borc' => (string) $this->bazBakiyeOzet['borc'],
                'toplam_alacak' => (string) $this->bazBakiyeOzet['alacak'],
                'bakiye' => (string) $this->bazBakiyeOzet['bakiye'],
            ];
        }

        try {
            $kur = (string) Cache::remember(
                'muhasebe:cari-kart-kur:'.$this->record->firma_id.':'.$baz.':'.$hedef.':'.now()->toDateString(),
                now()->addMinutes(30),
                function () use ($baz, $hedef): string {
                    $kayitli = \App\Models\Muhasebe\DovizKuru::tenantScopeOlmadan(fn () => \App\Models\Muhasebe\DovizKuru::query()
                        ->where('firma_id', (int) $this->record->firma_id)
                        ->where('kaynak_para_birimi', $baz)
                        ->where('hedef_para_birimi', $hedef)
                        ->whereDate('tarih', '<=', now()->toDateString())
                        ->orderByDesc('tarih')
                        ->orderByDesc('id')
                        ->first());

                    return (string) ($kayitli?->kur ?: app(\App\Muhasebe\Servisler\DovizKurServisi::class)
                        ->otomatikKurGetir($baz, $hedef)['kur']);
                }
            );

            if ((float) $kur <= 0) {
                throw new \RuntimeException('Geçersiz kur');
            }

            // Kur, baz para biriminin bir biriminin hedef para birimindeki
            // karşılığıdır (örn. 1 TRY = 0,0208 USD); bu nedenle çarpılır.
            $borc = bcmul((string) $this->bazBakiyeOzet['borc'], $kur, 2);
            $alacak = bcmul((string) $this->bazBakiyeOzet['alacak'], $kur, 2);

            return ['toplam_borc' => $borc, 'toplam_alacak' => $alacak, 'bakiye' => bcsub($borc, $alacak, 2)];
        } catch (\Throwable) {
            $satir = $this->cariBakiyeSatiri();

            return [
                'toplam_borc' => (string) $satir['toplam_borc'],
                'toplam_alacak' => (string) $satir['toplam_alacak'],
                'bakiye' => (string) $satir['bakiye'],
            ];
        }
    }

    public function paraBirimiMetni(): string
    {
        return $this->paraBirimiEtiketi;
    }

    public function paraBirimiUrl(): string
    {
        return ParaBirimiTanimKaynagi::getUrl();
    }

    public function table(Table $table): Table
    {
        $firmaId = (int) ($this->record?->firma_id ?? 0);
        $cariId = (int) ($this->record?->getKey() ?? 0);

        return $table
            ->query(
                CariHareketi::query()
                    ->where('firma_id', $firmaId)
                    ->where('cari_id', $cariId)
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
                        'iptal_edilen_hareket_id',
                        'durum',
                        'aciklama',
                    ])
                    ->with([
                        'fatura:id,firma_id,tur',
                        'finansHareketi.kasaHareketleri.kasaHesabi:id,ad',
                        'finansHareketi.bankaHareketleri.bankaHesabi:id,ad',
                        'finansHareketi.posHareketleri.posHesabi:id,ad',
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('islem_tarihi')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('belge_turu')
                    ->label('İşlem')
                    ->formatStateUsing(function ($state, CariHareketi $record): string {
                        if ($record->iptal_edilen_hareket_id !== null
                            || str_contains(mb_strtolower((string) $record->aciklama), 'düzeltme')) {
                            return 'Tahsilat düzeltmesi';
                        }

                        $enum = $state instanceof CariHareketBelgeTuru
                            ? $state
                            : CariHareketBelgeTuru::tryFrom((string) $state);

                        return $enum?->etiket() ?? '-';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('fatura_tipi')
                    ->label('Fatura tipi')
                    ->getStateUsing(function (CariHareketi $record): string {
                        $tur = $record->fatura?->tur;

                        return $tur instanceof FaturaTuru ? $tur->etiket() : '—';
                    })
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('kaynak_kanal')
                    ->label('Kaynak')
                    ->getStateUsing(fn (CariHareketi $record): string => $this->cariHareketKaynagi($record))
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('hedef_kanal')
                    ->label('Hedef')
                    ->getStateUsing(fn (CariHareketi $record): string => $this->cariHareketHedefi($record))
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('aciklama')
                    ->label('Açıklama')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (CariHareketi $record): ?string => $record->aciklama ?: null)
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => ($state instanceof CariHareketDurumu ? $state->value : (string) $state) === CariHareketDurumu::Aktif->value ? 'Aktif' : 'İptal')
                    ->color(fn ($state): string => ($state instanceof CariHareketDurumu ? $state->value : (string) $state) === CariHareketDurumu::Aktif->value ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('borc')
                    ->label('Borç')
                    ->formatStateUsing(fn ($state, CariHareketi $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) $record->para_birimi))
                    ->sortable(),
                Tables\Columns\TextColumn::make('alacak')
                    ->label('Alacak')
                    ->formatStateUsing(fn ($state, CariHareketi $record): string => number_format((float) $state, 2, ',', '.').' '.strtoupper((string) $record->para_birimi))
                    ->sortable(),
                Tables\Columns\TextColumn::make('baz_borc')
                    ->label('Baz borç')
                    ->formatStateUsing(fn ($state, CariHareketi $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->baz_para_birimi ?: config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('baz_alacak')
                    ->label('Baz alacak')
                    ->formatStateUsing(fn ($state, CariHareketi $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', '.').' '.strtoupper((string) ($record->baz_para_birimi ?: config('muhasebe.coklu_para_birimi.baz_para_birimi', 'TRY'))))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kur')
                    ->label('Kur')
                    ->formatStateUsing(fn ($state): string => $state === null ? '—' : number_format((float) $state, 8, ',', '.'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vade_tarihi')
                    ->label('Vade')
                    ->date('d.m.Y')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->recordClasses(fn (CariHareketi $record): string => ($record->durum instanceof CariHareketDurumu ? $record->durum : CariHareketDurumu::tryFrom((string) $record->durum)) === CariHareketDurumu::Iptal
                ? 'bg-danger-50/60 text-danger-900 line-through decoration-danger-500 decoration-2 dark:bg-danger-500/10 dark:text-danger-100'
                : '')
            ->heading('Cari Hareketleri')
            ->description('Bu cariye ait tahsilat, ödeme ve diğer muhasebe hareketleri.')
            ->defaultSort('islem_tarihi', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('belge_turu')
                    ->label('İşlem')
                    ->options(collect(CariHareketBelgeTuru::cases())->mapWithKeys(fn (CariHareketBelgeTuru $e): array => [$e->value => $e->etiket()])),
                Tables\Filters\Filter::make('islem_tarihi')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['baslangic'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('islem_tarihi', '>=', $date))
                            ->when($data['bitis'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('islem_tarihi', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('tahsilat')
                    ->label('Tahsilat')
                    ->icon('heroicon-o-arrow-down-left')
                    ->color('primary')
                    ->url(fn (): string => $this->tahsilatUrl()),
                Tables\Actions\Action::make('odeme')
                    ->label('Ödeme')
                    ->icon('heroicon-o-arrow-up-right')
                    ->url(fn (): string => $this->odemeUrl()),
                Tables\Actions\Action::make('ekstre')
                    ->label('Ekstre')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (): ?string => $this->ekstreUrl()),
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->url(fn (): string => CariKartiKaynagi::getUrl('edit', ['record' => $this->record?->getKey()])),
                Tables\Actions\Action::make('cariler')
                    ->label('Cariler')
                    ->icon('heroicon-o-arrow-left')
                    ->color('gray')
                    ->url(CariKartiKaynagi::getUrl('index')),
            ])
            ->actions([
                Tables\Actions\Action::make('detay')
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->tooltip('Detay')
                    ->url(fn (CariHareketi $record): ?string => $this->cariHareketDetayUrl($record))
                    ->visible(fn (CariHareketi $record): bool => filled($this->cariHareketDetayUrl($record))),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public function tahsilatUrl(): string
    {
        return TahsilatOlusturSayfasi::getUrl().'?cari_id='.(int) $this->record?->getKey();
    }

    public function odemeUrl(): string
    {
        return OdemeOlusturSayfasi::getUrl().'?cari_id='.(int) $this->record?->getKey();
    }

    public function tumFaturalarUrl(): string
    {
        return TumFaturalarSayfasi::getUrl();
    }

    public function vadeTakibiUrl(): string
    {
        return VadeTakipSayfasi::getUrl();
    }

    public function ekstreUrl(): ?string
    {
        return CariEkstreSayfasi::canAccess()
            ? CariEkstreSayfasi::getUrl().'?cari_id='.(int) $this->record?->getKey()
            : null;
    }

    public function cariHareketDetayUrl(CariHareketi $hareket): ?string
    {
        $belgeTuru = $hareket->belge_turu instanceof CariHareketBelgeTuru
            ? $hareket->belge_turu
            : CariHareketBelgeTuru::tryFrom((string) $hareket->belge_turu);

        if ($belgeTuru !== CariHareketBelgeTuru::Fatura || (int) $hareket->belge_id < 1) {
            return null;
        }

        return FaturaKaynagi::getUrl('view', ['record' => (int) $hareket->belge_id]);
    }

    public function cariHareketKaynagi(CariHareketi $hareket): string
    {
        $finans = $this->cariHareketFinansKaydi($hareket);
        if ($finans === null) {
            return '—';
        }

        $tur = $finans->tur?->value ?? (string) $finans->tur;
        if ($tur === 'odeme') {
            return $this->finansHesapMetni($finans, false);
        }

        return 'Cari: '.($this->record?->ad ?: '—');
    }

    public function cariHareketHedefi(CariHareketi $hareket): string
    {
        $finans = $this->cariHareketFinansKaydi($hareket);
        if ($finans === null) {
            return '—';
        }

        $tur = $finans->tur?->value ?? (string) $finans->tur;
        if ($tur === 'tahsilat') {
            return $this->finansHesapMetni($finans, true);
        }

        return 'Cari: '.($this->record?->ad ?: '—');
    }

    private function cariHareketFinansKaydi(CariHareketi $hareket): ?FinansHareketi
    {
        $belgeTuru = $hareket->belge_turu instanceof CariHareketBelgeTuru
            ? $hareket->belge_turu->value
            : (string) $hareket->belge_turu;

        return in_array($belgeTuru, ['tahsilat', 'odeme'], true) ? $hareket->finansHareketi : null;
    }

    private function finansHesapMetni(FinansHareketi $finans, bool $giris): string
    {
        $adlar = [];
        foreach ($finans->kasaHareketleri as $hareket) {
            if (($giris && (float) $hareket->tutar > 0) || (! $giris && (float) $hareket->tutar < 0)) {
                $adlar[] = 'Kasa: '.($hareket->kasaHesabi?->ad ?: '#'.$hareket->kasa_hesap_id);
            }
        }
        foreach ($finans->bankaHareketleri as $hareket) {
            if (($giris && (float) $hareket->tutar > 0) || (! $giris && (float) $hareket->tutar < 0)) {
                $adlar[] = 'Banka: '.($hareket->bankaHesabi?->ad ?: '#'.$hareket->banka_hesap_id);
            }
        }
        foreach ($finans->posHareketleri as $hareket) {
            if (($giris && (float) $hareket->tutar > 0) || (! $giris && (float) $hareket->tutar < 0)) {
                $adlar[] = 'POS: '.($hareket->posHesabi?->ad ?: '#'.$hareket->pos_hesap_id);
            }
        }

        return $adlar === [] ? '—' : implode(' | ', $adlar);
    }

    public function sekreterAktifMi(): bool
    {
        return Schema::hasTable('sekreter_gorevleri')
            && Schema::hasTable('sekreter_randevulari')
            && app(\App\Services\ModulErisimService::class)->modulErisilebilirMi((int) $this->record?->firma_id, 'sekreter');
    }

    /** @return Collection<int, SekreterGorevi> */
    public function sekreterGorevleri(): Collection
    {
        if (! $this->sekreterAktifMi()) {
            return collect();
        }

        return SekreterGorevi::query()
            ->where('cari_id', (int) $this->record?->getKey())
            ->whereNotIn('durum', ['tamamlandi', 'iptal'])
            ->orderBy('tarih')
            ->orderBy('saat')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, SekreterRandevusu> */
    public function sekreterRandevulari(): Collection
    {
        if (! $this->sekreterAktifMi()) {
            return collect();
        }

        return SekreterRandevusu::query()
            ->where('cari_id', (int) $this->record?->getKey())
            ->whereDate('baslangic_tarihi', '>=', today()->subDays(30))
            ->orderBy('baslangic_tarihi')
            ->orderBy('baslangic_saati')
            ->limit(8)
            ->get();
    }
}
