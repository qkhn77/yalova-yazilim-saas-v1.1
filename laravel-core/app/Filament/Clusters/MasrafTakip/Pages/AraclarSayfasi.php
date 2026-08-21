<?php

namespace App\Filament\Clusters\MasrafTakip\Pages;

use App\Filament\Clusters\MasrafTakip as MasrafTakipCluster;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipFilamentErisimYardimcisi;
use App\Filament\Clusters\MasrafTakip\Kaynaklar\MasrafTakipSayfaErisimleri;
use App\Models\Masraf\Arac;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Services\TenantContextService;
use App\Support\MasrafTakipYetkiSablonlari;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AraclarSayfasi extends Page implements HasTable
{
    use InteractsWithTable;
    use MasrafTakipSayfaErisimleri;

    protected static ?string $cluster = MasrafTakipCluster::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Araçlar';
    protected static ?string $slug = 'tanimlar/araclar';
    protected static string $view = 'filament.clusters.masraf-takip.pages.araclar';

    public function getHeading(): string
    {
        return 'Araçlar';
    }

    public function getSubheading(): ?string
    {
        return 'Plaka, araç kimliği, kilometre ve belge tarihlerini tek yerden yönetin.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniArac')
                ->label('Yeni araç')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::OLUSTUR))
                ->form($this->aracFormu())
                ->action(fn (array $data): mixed => $this->aracKaydet($data)),
            Action::make('masrafTakibineDon')
                ->label('Masraflara dön')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(MasrafTakibiSayfasi::getUrl()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Arac::query()->where('firma_id', $this->aktifFirmaId() ?? 0))
            ->deferLoading()
            ->defaultSort('plaka')
            ->columns([
                Tables\Columns\TextColumn::make('plaka')->label('Plaka')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('marka')->label('Marka')->searchable(),
                Tables\Columns\TextColumn::make('model')->label('Model')->searchable(),
                Tables\Columns\TextColumn::make('model_yili')->label('Model yılı')->placeholder('—'),
                Tables\Columns\TextColumn::make('yakit_tipi')->label('Yakıt')->placeholder('—'),
                Tables\Columns\TextColumn::make('kilometre')->label('Kilometre')->numeric()->suffix(' km'),
                Tables\Columns\TextColumn::make('sigorta_bitis')->label('Sigorta')->date('d.m.Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('muayene_bitis')->label('Muayene')->date('d.m.Y')->placeholder('—'),
                Tables\Columns\IconColumn::make('aktif_mi')->label('Aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('duzenle')
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->fillForm(fn (Arac $record): array => $record->only([
                        'plaka', 'marka', 'model', 'model_yili', 'yakit_tipi', 'kilometre', 'sigorta_bitis', 'muayene_bitis', 'aktif_mi', 'notlar',
                    ]))
                    ->form($this->aracFormu())
                    ->action(fn (Arac $record, array $data): mixed => $this->aracKaydet($data, (int) $record->getKey())),
                Tables\Actions\Action::make('durumDegistir')
                    ->label(fn (Arac $record): string => $record->aktif_mi ? 'Pasifleştir' : 'Aktifleştir')
                    ->icon(fn (Arac $record): string => $record->aktif_mi ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (Arac $record): string => $record->aktif_mi ? 'warning' : 'success')
                    ->visible(fn (): bool => $this->yetkiVarMi(MasrafTakipYetkiSablonlari::GUNCELLE))
                    ->action(function (Arac $record): void {
                        $record->update(['aktif_mi' => ! $record->aktif_mi]);
                        $this->resetTable();
                    }),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /** @return array<int, Forms\Components\Component> */
    private function aracFormu(): array
    {
        return [
            Forms\Components\TextInput::make('plaka')->label('Plaka')->required()->maxLength(20),
            Forms\Components\TextInput::make('marka')->label('Marka')->required()->maxLength(80),
            Forms\Components\TextInput::make('model')->label('Model')->required()->maxLength(80),
            Forms\Components\TextInput::make('model_yili')->label('Model yılı')->numeric()->integer()->minValue(1900)->maxValue(now()->year + 1),
            Forms\Components\Select::make('yakit_tipi')->label('Yakıt tipi')->options([
                'benzin' => 'Benzin', 'dizel' => 'Dizel', 'lpg' => 'LPG', 'hibrit' => 'Hibrit', 'elektrik' => 'Elektrik', 'diger' => 'Diğer',
            ])->native(false),
            Forms\Components\TextInput::make('kilometre')->label('Kilometre')->numeric()->integer()->minValue(0)->default(0),
            Forms\Components\DatePicker::make('sigorta_bitis')->label('Sigorta bitiş')->native(false),
            Forms\Components\DatePicker::make('muayene_bitis')->label('Muayene bitiş')->native(false),
            Forms\Components\Toggle::make('aktif_mi')->label('Aktif')->default(true),
            Forms\Components\Textarea::make('notlar')->label('Notlar')->rows(3)->maxLength(2000)->columnSpanFull(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function aracKaydet(array $data, ?int $aracId = null): mixed
    {
        $firmaId = $this->aktifFirmaId();
        if ($firmaId === null) {
            return $this->uyariGoster('Aktif firma bulunamadı', 'Araç kaydetmek için önce aktif firma seçin.');
        }

        $plaka = Str::upper(trim((string) ($data['plaka'] ?? '')));
        $ayni = Arac::query()->where('firma_id', $firmaId)->where('plaka', $plaka)->when($aracId !== null, fn ($query) => $query->whereKeyNot($aracId))->exists();
        if ($ayni) {
            return $this->uyariGoster('Araç kaydedilemedi', 'Bu plaka aktif firmada zaten kayıtlı.');
        }

        try {
            $arac = $aracId === null
                ? new Arac()
                : Arac::query()->where('firma_id', $firmaId)->whereKey($aracId)->firstOrFail();
            $arac->fill(array_merge($data, ['firma_id' => $firmaId, 'plaka' => $plaka]))->save();
        } catch (\Throwable $exception) {
            return $this->uyariGoster('Araç kaydedilemedi', $exception->getMessage());
        }

        $this->resetTable();
        Notification::make()->title($aracId === null ? 'Araç eklendi' : 'Araç güncellendi')->success()->send();

        return null;
    }

    private function aktifFirmaId(): ?int
    {
        $firmaId = app(TenantContextService::class)->aktifFirmaId();
        return $firmaId ? (int) $firmaId : null;
    }

    private function yetkiVarMi(string $yetki): bool
    {
        return MasrafTakipFilamentErisimYardimcisi::masrafTakipYetkisiVarMi($yetki);
    }

    private function uyariGoster(string $baslik, string $govde): void
    {
        Notification::make()->title($baslik)->body($govde)->warning()->send();
    }
}
