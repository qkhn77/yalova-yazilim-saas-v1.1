<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\PostCategory;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class BlogKategori extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Blog Kategorileri';

    protected static ?string $slug = 'bloglar/blog-kategori';

    protected static string $view = 'filament.clusters.web.pages.blog-kategori';

    public static function getNavigationLabel(): string
    {
        return 'Blog Kategorileri';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Blog Kategorileri';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Blog Kategorileri';
    }

    public function getSubheading(): ?string
    {
        return 'Blog yazıları için kategorileri ekleyin ve düzenleyin.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(PostCategory::query()->select(['id', 'name', 'slug', 'description', 'sort_order', 'is_active']))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kategori adı')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('description')
                    ->label('Açıklama')
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('posts_count')
                    ->label('Yazı sayısı')
                    ->counts('posts')
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Kategori ekle')
                    ->modalHeading('Kategori ekle')
                    ->icon('heroicon-o-plus')
                    ->form($this->getCategoryFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['slug']) && ! empty($data['name'])) {
                            $data['slug'] = Str::slug($data['name']);
                        }

                        return $data;
                    })
                    ->action(function (array $data): void {
                        if (empty($data['meta_title'])) {
                            $data['meta_title'] = $data['name'] ?? null;
                        }
                        if (empty($data['meta_description'])) {
                            $data['meta_description'] = $data['description'] ?? null;
                        }

                        PostCategory::create($data);

                        Notification::make()
                            ->title('Kategori eklendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->form($this->getCategoryFormSchema())
                    ->action(function (PostCategory $record, array $data): void {
                        if (empty($data['meta_title'])) {
                            $data['meta_title'] = $data['name'] ?? $record->name;
                        }
                        if (empty($data['meta_description'])) {
                            $data['meta_description'] = $data['description'] ?? $record->description;
                        }

                        $record->update($data);

                        Notification::make()
                            ->title('Kategori güncellendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->icon('heroicon-o-trash')
                    ->before(function (PostCategory $record) {
                        $record->posts()->update(['post_category_id' => null]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('aktifYap')
                        ->label('Aktif yap')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen kategoriler aktif yapıldı.'),
                    Tables\Actions\BulkAction::make('pasifYap')
                        ->label('Pasif yap')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen kategoriler pasif yapıldı.'),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $record->posts()->update(['post_category_id' => null]);
                            }
                        }),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (PostCategory $record): bool => true)
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    protected function getCategoryFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Kategori adı (SEO başlık)')
                ->required()
                ->maxLength(70)
                ->live(debounce: 500)
                ->helperText(fn ($state) => mb_strlen((string) $state).' / 70 karakter. 50-60 karakter önerilir.'),
            Forms\Components\Textarea::make('description')
                ->label('Açıklama (Meta açıklama)')
                ->maxLength(165)
                ->rows(3)
                ->columnSpanFull()
                ->live(debounce: 500)
                ->helperText(fn ($state) => mb_strlen((string) $state).' / 165 karakter. 150-160 karakter önerilir.'),
            Forms\Components\TextInput::make('meta_keywords')
                ->label('Anahtar kelimeler')
                ->maxLength(500)
                ->columnSpanFull()
                ->placeholder('yalova kamera, ip kamera kurulumu, güvenlik kamera')
                ->helperText('Virgül ile ayrılmalıdır.'),
            Forms\Components\TextInput::make('slug')
                ->label('SEO URL adresi')
                ->required()
                ->maxLength(255)
                ->unique(PostCategory::class, 'slug', ignoreRecord: true)
                ->rules(['alpha_dash:ascii'])
                ->placeholder('ip-kamera-kurulumu')
                ->helperText('Kategori adından otomatik oluşturulur, isterseniz düzenleyebilirsiniz.'),
            Forms\Components\TextInput::make('meta_title')
                ->label('Meta başlık')
                ->maxLength(70)
                ->helperText('Boş bırakılırsa kategori adı kullanılır.'),
            Forms\Components\Textarea::make('meta_description')
                ->label('Meta açıklama')
                ->maxLength(165)
                ->rows(2)
                ->helperText('Boş bırakılırsa yukarıdaki açıklama kullanılır.'),
            Forms\Components\TextInput::make('sort_order')
                ->label('Sıra')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ];
    }
}
