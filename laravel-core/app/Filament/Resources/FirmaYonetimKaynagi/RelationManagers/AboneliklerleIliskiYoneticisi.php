<?php

namespace App\Filament\Resources\FirmaYonetimKaynagi\RelationManagers;

use App\Models\FirmaAboneligi;
use App\Support\DenetimYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AboneliklerleIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'firmaAbonelikleri';

    protected static ?string $title = 'Abonelikler';

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return SaaSemaYardimcisi::firmaAbonelikleriTablosuVarMi() && SaaSemaYardimcisi::planlarTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('plan_id')
                ->label('Plan')
                ->relationship('plan', 'ad', fn ($query) => $query->orderBy('ad'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\Select::make('durum')
                ->label('Durum')
                ->options(self::durumSecenekleri())
                ->required()
                ->default('aktif')
                ->native(false),
            Forms\Components\DatePicker::make('baslangic_tarihi')
                ->label('Başlangıç')
                ->required()
                ->default(now()),
            Forms\Components\DatePicker::make('bitis_tarihi')
                ->label('Bitiş')
                ->required()
                ->default(now()->addMonth()),
            Forms\Components\Toggle::make('otomatik_yenileme')
                ->label('Otomatik yenileme'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'firma_id',
                    'plan_id',
                    'durum',
                    'baslangic_tarihi',
                    'bitis_tarihi',
                    'otomatik_yenileme',
                ])
                ->with('plan:id,ad,kod'))
            ->columns([
                Tables\Columns\TextColumn::make('plan.ad')->label('Plan')->sortable(),
                Tables\Columns\TextColumn::make('plan.kod')->label('Plan kodu')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?string $record): string => self::durumEtiketi($record)),
                Tables\Columns\TextColumn::make('baslangic_tarihi')->label('Başlangıç')->date('d.m.Y'),
                Tables\Columns\TextColumn::make('bitis_tarihi')->label('Bitiş')->date('d.m.Y'),
                Tables\Columns\IconColumn::make('otomatik_yenileme')->label('Oto. yenileme')->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Abonelik ekle')
                    ->using(function (array $data): FirmaAboneligi {
                        $iliski = $this->getRelationship();
                        $kayit = new FirmaAboneligi($this->durumVerisiniNormalizeEt($data));
                        $iliski->save($kayit);
                        $kayit->refresh();
                        DenetimYardimcisi::kaydet(
                            'abonelik_guncellendi',
                            FirmaAboneligi::class,
                            (int) $kayit->getKey(),
                            (int) $kayit->firma_id,
                            null,
                            $kayit->only(['plan_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi'])
                        );

                        return $kayit;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->using(function (array $data, Model $record): void {
                        /** @var FirmaAboneligi $record */
                        $record->update($this->durumVerisiniNormalizeEt($data));
                        $record->refresh();
                        DenetimYardimcisi::kaydet(
                            'abonelik_guncellendi',
                            FirmaAboneligi::class,
                            (int) $record->getKey(),
                            (int) $record->firma_id,
                            null,
                            $record->only(['plan_id', 'durum', 'baslangic_tarihi', 'bitis_tarihi', 'otomatik_yenileme'])
                        );
                }),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function durumVerisiniNormalizeEt(array $data): array
    {
        $durum = self::durumKodunuNormalizeEt((string) ($data['durum'] ?? 'aktif'));
        $data['durum'] = $durum;
        $bitis = isset($data['bitis_tarihi']) ? Carbon::parse((string) $data['bitis_tarihi'])->startOfDay() : null;
        if ($durum === 'aktif' && $bitis instanceof Carbon && $bitis->lt(Carbon::today())) {
            $data['durum'] = 'suresi_doldu';
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private static function durumSecenekleri(): array
    {
        return [
            'aktif' => 'Aktif',
            'pasif' => 'Pasif',
            'suresi_doldu' => 'Süresi doldu',
            'iptal' => 'İptal',
            'beklemede' => 'Beklemede',
        ];
    }

    private static function durumKodunuNormalizeEt(?string $durum): string
    {
        $normal = mb_strtolower(trim((string) $durum));
        $normal = str_replace('ü', 'u', $normal);

        return match ($normal) {
            'aktif', 'pasif', 'iptal', 'beklemede' => $normal,
            'suresi_doldu', 'süresi_doldu', 'suresi doldu', 'süresi doldu' => 'suresi_doldu',
            default => 'aktif',
        };
    }

    private static function durumEtiketi(?string $durum): string
    {
        $kod = self::durumKodunuNormalizeEt($durum);

        return self::durumSecenekleri()[$kod] ?? 'Aktif';
    }
}
