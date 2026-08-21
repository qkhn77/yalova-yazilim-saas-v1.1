<?php

namespace App\Filament\Resources;

use App\Filament\Support\HasTenantVisibility;
use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductCategoryResource extends Resource
{
    use HasTenantVisibility;

    protected static ?string $model = ProductCategory::class;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modulKodu = 'urunler';
    protected static ?string $goruntuleYetkiKodu = 'urun_kategori.goruntule';
    protected static ?string $olusturYetkiKodu = 'urun_kategori.olustur';
    protected static ?string $guncelleYetkiKodu = 'urun_kategori.guncelle';
    protected static ?string $silYetkiKodu = 'urun_kategori.sil';

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Web';
    protected static ?string $navigationLabel = 'Ürün Kategorileri';
    protected static ?string $modelLabel = 'Ürün kategorisi';
    protected static ?string $pluralModelLabel = 'Ürün kategorileri';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kategori Bilgileri')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Kategori adı')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug((string) $state))),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('parent_id')
                        ->label('Üst kategori')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\Textarea::make('description')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Görsel')
                        ->disk('public')
                        ->directory('products/categories')
                        ->visibility('public')
                        ->image(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),
            Forms\Components\Section::make('SEO')
                ->schema([
                    Forms\Components\TextInput::make('seo_title')->label('SEO Başlık')->maxLength(255),
                    Forms\Components\Textarea::make('seo_description')->label('SEO Açıklama')->rows(3),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select([
                    'id',
                    'parent_id',
                    'name',
                    'slug',
                    'sort_order',
                    'is_active',
                ])
                ->with(['parent:id,name']))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Kategori')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Üst kategori')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('slug')->label('Slug')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('products_count')->label('Ürün')->counts('products')->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->label('Sıra')->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Aktif'),
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
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }
}

