<?php

namespace App\Filament\Clusters\PersonelTakip\Resources\PersonelKaynagi\RelationManagers;

use App\Filament\Clusters\PersonelTakip\Kaynaklar\PersonelTakipFilamentErisimYardimcisi;
use App\Models\Personel\PersonelBelgesi;
use App\Support\PersonelTakip\PersonelTakipYetkiSablonlari;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BelgelerRelationManager extends RelationManager
{
    protected static string $relationship = 'belgeler';

    protected static ?string $title = 'Personel Belgeleri';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GORUNTULE);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('firma_id')
                ->default(fn (): int => (int) $this->getOwnerRecord()->firma_id)
                ->dehydrated(),
            Forms\Components\Select::make('belge_turu')
                ->label('Belge türü')
                ->options([
                    'kimlik' => 'Kimlik',
                    'sozlesme' => 'Sözleşme',
                    'saglik_raporu' => 'Sağlık raporu',
                    'egitim' => 'Eğitim / sertifika',
                    'izin' => 'İzin belgesi',
                    'diger' => 'Diğer',
                ])
                ->default('diger')
                ->required(),
            Forms\Components\TextInput::make('ad')
                ->label('Belge adı')
                ->required()
                ->maxLength(191),
            Forms\Components\FileUpload::make('dosya_yolu')
                ->label('Dosya')
                ->directory('personel/belgeleri')
                ->preserveFilenames()
                ->required(),
            Forms\Components\DatePicker::make('duzenleme_tarihi')
                ->label('Düzenleme tarihi'),
            Forms\Components\DatePicker::make('gecerlilik_tarihi')
                ->label('Geçerlilik tarihi'),
            Forms\Components\DatePicker::make('uyari_tarihi')
                ->label('Uyarı tarihi'),
            Forms\Components\Textarea::make('aciklama')
                ->label('Açıklama')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ad')
            ->modifyQueryUsing(fn ($query) => $query->select([
                'id',
                'firma_id',
                'personel_id',
                'belge_turu',
                'ad',
                'dosya_yolu',
                'gecerlilik_tarihi',
                'durum',
                'created_at',
                'updated_at',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('belge_turu')
                    ->label('Tür')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Belge')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dosya_yolu')
                    ->label('Dosya')
                    ->limit(45)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gecerlilik_tarihi')
                    ->label('Geçerlilik')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Eklenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        'gecerli' => 'Geçerli',
                        'yenilenecek' => 'Yenilenecek',
                        'suresi_doldu' => 'Süresi doldu',
                        'iptal' => 'İptal',
                        'arsiv' => 'Arşiv',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Belge ekle')
                    ->visible(fn (): bool => $this->duzenlemeYetkisiVarMi()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => $this->duzenlemeYetkisiVarMi()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => $this->duzenlemeYetkisiVarMi()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->duzenlemeYetkisiVarMi()),
                ]),
            ]);
    }

    public function isReadOnly(): bool
    {
        return ! $this->duzenlemeYetkisiVarMi();
    }

    private function duzenlemeYetkisiVarMi(): bool
    {
        return PersonelTakipFilamentErisimYardimcisi::personelYetkisiVarMi(PersonelTakipYetkiSablonlari::GUNCELLE);
    }
}
