<?php

namespace App\Filament\Resources\FirmaKullaniciGrubuKaynagi\RelationManagers;

use App\Models\Rol;
use App\Models\Yetki;
use App\Services\TenantContextService;
use App\Services\YetkiService;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class YetkilerIliskiYoneticisi extends RelationManager
{
    protected static string $relationship = 'yetkiler';

    protected static ?string $title = 'Grup yetkileri';

    public static function canViewForRecord(Model $record, string $pageClass): bool
    {
        return $record instanceof Rol
            && ! $record->sistem_rolu_mu
            && SaaSemaYardimcisi::yetkilerTablosuVarMi()
            && SaaSemaYardimcisi::rolYetkileriTablosuVarMi();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('yetki_ids')
                ->label('Yetkiler')
                ->multiple()
                ->options(fn (): array => $this->atanabilirYetkiSecenekleri())
                ->preload()
                ->searchable()
                ->native(false)
                ->required()
                ->helperText('Bu gruba eklemek istediğiniz yetkileri çoklu seçerek tek seferde kaydedebilirsiniz.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('modul_kodu')
                    ->label('Modül')
                    ->placeholder('sistem')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Açıklama'),
            ])
            ->defaultSort('modul_kodu')
            ->headerActions([
                Tables\Actions\Action::make('yetkiEkle')
                    ->label('Yetki ekle')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('yetki_ids')
                            ->label('Yetkiler')
                            ->multiple()
                            ->options(fn (): array => $this->atanabilirYetkiSecenekleri())
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->helperText('Tüm atanabilir yetkiler listelenir. Çoklu seçim yapabilirsiniz.'),
                    ])
                    ->action(function (array $data): void {
                        $rol = $this->getOwnerRecord();
                        $yetkiIdleri = collect($data['yetki_ids'] ?? [])
                            ->map(fn ($id): int => (int) $id)
                            ->filter(fn (int $id): bool => $id > 0)
                            ->unique()
                            ->values();

                        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                        $kullanici = Auth::user();

                        if ($yetkiIdleri->isEmpty()) {
                            throw ValidationException::withMessages([
                                'yetki_ids' => 'En az bir yetki seçmelisiniz.',
                            ]);
                        }

                        if (! $kullanici || $firmaId < 1) {
                            throw ValidationException::withMessages([
                                'yetki_ids' => 'Yetki eklemek için geçerli firma bulunamadı.',
                            ]);
                        }

                        $yetkiler = Yetki::query()
                            ->whereIn('id', $yetkiIdleri->all())
                            ->get()
                            ->keyBy('id');

                        foreach ($yetkiIdleri as $yetkiId) {
                            $yetki = $yetkiler->get($yetkiId);

                            if (! $yetki) {
                                throw ValidationException::withMessages([
                                    'yetki_ids' => 'Seçtiğiniz yetkilerden biri geçersiz.',
                                ]);
                            }

                            if (! app(YetkiService::class)->yetkiAtayabilirMi($kullanici, $firmaId, (string) $yetki->kod)) {
                                throw ValidationException::withMessages([
                                    'yetki_ids' => 'Seçtiğiniz yetkilerden en az biri için atama izniniz yok.',
                                ]);
                            }
                        }

                        $mevcutYetkiIdleri = $rol->yetkiler()->pluck('yetkiler.id')->all();
                        $eklenecekYetkiIdleri = $yetkiIdleri
                            ->reject(fn (int $id): bool => in_array($id, $mevcutYetkiIdleri, true))
                            ->all();

                        if ($eklenecekYetkiIdleri === []) {
                            throw ValidationException::withMessages([
                                'yetki_ids' => 'Seçtiğiniz yetkiler zaten bu grupta tanımlı.',
                            ]);
                        }

                        $rol->yetkiler()->attach($eklenecekYetkiIdleri);
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Kaldır')
                    ->action(function (Model $record): void {
                        $rol = $this->getOwnerRecord();
                        $rol->yetkiler()->detach((int) $record->getKey());
                    }),
            ])
            ->bulkActions([]);
    }

    protected function atanabilirYetkiSecenekleri(): array
    {
        $kullanici = Auth::user();
        $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);

        if (! $kullanici || $firmaId < 1) {
            return [];
        }

        $rol = $this->getOwnerRecord();
        $seciliYetkiIdleri = $rol->yetkiler()->pluck('yetkiler.id')->all();

        return app(YetkiService::class)
            ->atanabilirYetkiKayitlari($kullanici, $firmaId)
            ->whereNotIn('id', $seciliYetkiIdleri)
            ->mapWithKeys(fn (Yetki $yetki): array => [
                (int) $yetki->id => trim(($yetki->modul_kodu ?: 'sistem') . ' / ' . $yetki->kod . ' / ' . $yetki->ad),
            ])
            ->all();
    }
}
