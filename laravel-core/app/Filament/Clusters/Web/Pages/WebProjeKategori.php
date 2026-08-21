<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\ProjectCategory;
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

class WebProjeKategori extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Proje Kategorileri';

    protected static ?string $slug = 'projeler/kategoriler';

    protected static string $view = 'filament.clusters.web.pages.web-proje-kategori';

    public static function getNavigationLabel(): string
    {
        return 'Proje Kategorileri';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Proje Kategorileri';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Proje Kategorileri';
    }

    public function getSubheading(): ?string
    {
        return 'Projeler için kategorileri ekleyin ve düzenleyin.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(ProjectCategory::query()->select([
                'id',
                'name',
                'meta_title',
                'slug',
                'description',
                'meta_description',
                'meta_keywords',
                'sort_order',
                'is_active',
            ]))
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
                Tables\Columns\TextColumn::make('projects_count')
                    ->label('Proje sayısı')
                    ->counts('projects')
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

                        ProjectCategory::create($data);

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
                    ->action(function (ProjectCategory $record, array $data): void {
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
                    ->before(function (ProjectCategory $record) {
                        $record->projects()->update(['project_category_id' => null]);
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
                                $record->projects()->update(['project_category_id' => null]);
                            }
                        }),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (ProjectCategory $record): bool => true)
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
                ->placeholder('yalova kamera, proje örnekleri, güvenlik kamerası')
                ->helperText('Virgül ile ayrılmalıdır.'),
            Forms\Components\TextInput::make('slug')
                ->label('SEO URL adresi')
                ->required()
                ->maxLength(255)
                ->unique(ProjectCategory::class, 'slug', ignoreRecord: true)
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
