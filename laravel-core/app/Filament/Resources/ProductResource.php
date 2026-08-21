<?php

namespace App\Filament\Resources;

use App\Filament\Support\HasTenantVisibility;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = Product::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modulKodu = 'urunler';
    protected static ?string $goruntuleYetkiKodu = 'urun.goruntule';
    protected static ?string $olusturYetkiKodu = 'urun.olustur';
    protected static ?string $guncelleYetkiKodu = 'urun.guncelle';
    protected static ?string $silYetkiKodu = 'urun.sil';

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Web';
    protected static ?string $navigationLabel = 'Ürünler';
    protected static ?string $modelLabel = 'Ürün';
    protected static ?string $pluralModelLabel = 'Ürünler';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Ürün')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Genel')
                        ->schema([
                            Forms\Components\Select::make('category_id')
                                ->label('Kategori')
                                ->relationship('category', 'name')
                                ->required()
                                ->searchable(),
                            Forms\Components\TextInput::make('name')
                                ->label('Ürün adı')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug((string) $state))),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            Forms\Components\TextInput::make('sku')->label('SKU')->maxLength(120),
                            Forms\Components\TextInput::make('brand')->label('Marka')->maxLength(120),
                            Forms\Components\Textarea::make('short_description')->label('Kısa açıklama')->rows(3)->columnSpanFull(),
                            Forms\Components\RichEditor::make('description')
                                ->label('Detay açıklama')
                                ->fileAttachmentsDisk('public')
                                ->fileAttachmentsDirectory('products')
                                ->columnSpanFull(),
                        ])->columns(2),
                    Forms\Components\Tabs\Tab::make('Fiyat & Stok')
                        ->schema([
                            Forms\Components\Select::make('para_birimi')
                                ->label('Para birimi')
                                ->options([
                                    'TRY' => 'TRY',
                                    'USD' => 'USD',
                                    'EUR' => 'EUR',
                                ])
                                ->default('TRY')
                                ->required()
                                ->searchable(),
                            Forms\Components\TextInput::make('price')
                                ->label('Fiyat')
                                ->numeric()
                                ->prefix(fn (Forms\Get $get) => strtoupper((string) ($get('para_birimi') ?: 'TRY'))),
                            Forms\Components\TextInput::make('discounted_price')
                                ->label('İndirimli fiyat')
                                ->numeric()
                                ->prefix(fn (Forms\Get $get) => strtoupper((string) ($get('para_birimi') ?: 'TRY'))),
                            Forms\Components\Select::make('stock_status')
                                ->label('Stok durumu')
                                ->options([
                                    Product::STOCK_IN_STOCK => 'Stokta',
                                    Product::STOCK_OUT_OF_STOCK => 'Tükendi',
                                    Product::STOCK_PREORDER => 'Ön sipariş',
                                ])
                                ->default(Product::STOCK_IN_STOCK),
                            Forms\Components\TextInput::make('sort_order')->label('Sıralama')->numeric()->default(0),
                            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
                            Forms\Components\Toggle::make('is_featured')->label('Öne çıkan')->default(false),
                        ])->columns(2),
                    Forms\Components\Tabs\Tab::make('Görseller')
                        ->schema([
                            Forms\Components\FileUpload::make('image')
                                ->label('Ana görsel')
                                ->disk('public')
                                ->directory('products/main')
                                ->visibility('public')
                                ->image(),
                            Forms\Components\FileUpload::make('gallery')
                                ->label('Galeri')
                                ->disk('public')
                                ->directory('products/gallery')
                                ->visibility('public')
                                ->multiple()
                                ->reorderable()
                                ->image(),
                        ]),
                    Forms\Components\Tabs\Tab::make('Teknik Özellikler')
                        ->schema([
                            Forms\Components\KeyValue::make('technical_specs')
                                ->label('Teknik özellikler')
                                ->keyLabel('Özellik')
                                ->valueLabel('Değer')
                                ->addActionLabel('Özellik ekle')
                                ->columnSpanFull(),
                        ]),
                    Forms\Components\Tabs\Tab::make('SEO')
                        ->schema([
                            Forms\Components\TextInput::make('seo_title')->label('SEO Başlık')->maxLength(255),
                            Forms\Components\Textarea::make('seo_description')->label('SEO Açıklama')->rows(3),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Ürün')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('brand')->label('Marka')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('final_price')->label('Fiyat')->money(fn ($record) => strtoupper((string) ($record->para_birimi ?: 'TRY')))->sortable(),
                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Stok')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Product::STOCK_IN_STOCK => 'Stokta',
                        Product::STOCK_OUT_OF_STOCK => 'Tükendi',
                        Product::STOCK_PREORDER => 'Ön Sipariş',
                        default => 'Sorunuz',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        Product::STOCK_IN_STOCK => 'success',
                        Product::STOCK_OUT_OF_STOCK => 'danger',
                        Product::STOCK_PREORDER => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_featured')->label('Öne çıkan')->boolean(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('Kategori')->relationship('category', 'name')->searchable()->preload(),
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktif'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Öne çıkan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('aktif')
                        ->label('Aktif Yap')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('pasif')
                        ->label('Pasif Yap')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}

