<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Bloglar';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Blog Listesi';
    protected static ?string $modelLabel = 'Blog yazısı';
    protected static ?string $pluralModelLabel = 'Blog';
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Yazı bilgileri')->schema([
                    Forms\Components\Select::make('post_category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Kategori seçin'),
                    Forms\Components\TextInput::make('title')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(255)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, $context) {
                            if ($context === 'create') {
                                $set('slug', \Illuminate\Support\Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('URL (slug)')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, $context) {
                            if ($context === 'create' && empty($state)) {
                                $set('slug', \Illuminate\Support\Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('excerpt')
                        ->label('Özet')
                        ->maxLength(255),
                    Forms\Components\FileUpload::make('image')
                        ->label('Görsel')
                        ->disk('public')
                        ->directory('posts')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    Forms\Components\RichEditor::make('content')
                        ->label('İçerik')
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Yayın tarihi'),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Yayında')
                        ->default(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),
                ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')->label('Kategori')->sortable(),
                Tables\Columns\TextColumn::make('title')->label('Başlık')->searchable()->sortable(),
                Tables\Columns\ImageColumn::make('image')->label('Görsel')->circular(),
                Tables\Columns\TextColumn::make('published_at')->label('Yayın tarihi')->dateTime('d.m.Y')->sortable(),
                Tables\Columns\IconColumn::make('is_published')->label('Yayında')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Sıra')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')->label('Yayında'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'view' => Pages\ViewPost::route('/{record}'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
