<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\Project;
use App\Services\ThumbnailService;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebProje extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Projeler';

    protected static ?string $slug = 'projeler/liste';

    protected static string $view = 'filament.clusters.web.pages.web-proje';

    public static function getNavigationLabel(): string
    {
        return 'Proje Listesi';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Projeler';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Projeler';
    }

    public function getSubheading(): ?string
    {
        return 'Projeleri ekleyin, düzenleyin. Görsele tıklayarak ön izleme yapabilirsiniz.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(
                Project::query()
                    ->select([
                        'id',
                        'project_category_id',
                        'title',
                        'slug',
                        'short_description',
                        'image',
                        'is_active',
                        'sort_order',
                    ])
                    ->with('category:id,name')
            )
            ->columns([
                Tables\Columns\ViewColumn::make('image')
                    ->label('Görsel')
                    ->view('filament.clusters.web.columns.project-image-preview')
                    ->alignCenter()
                    ->width('3rem')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('short_description')
                    ->label('Kısa açıklama')
                    ->limit(35)
                    ->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıra')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 20, 50, 100, 1000, 'all'])
            ->filters([
                Tables\Filters\SelectFilter::make('project_category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Proje ekle')
                    ->modalHeading('Proje ekle')
                    ->icon('heroicon-o-plus')
                    ->form($this->getProjectFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['slug']) && ! empty($data['title'])) {
                            $data['slug'] = Str::slug($data['title']);
                        }

                        return $data;
                    })
                    ->action(function (array $data): void {
                        $imageName = $data['image_name'] ?? null;
                        unset($data['image_name']);

                        $project = Project::create($data);

                        if ($imageName && $project->image) {
                            $newPath = $this->renameStoredImage($project->image, $imageName, 'projects');
                            if ($newPath) {
                                $project->update(['image' => $newPath]);
                            }
                        }

                        if ($project->image) {
                            app(ThumbnailService::class)->generate('projects', $project->image);
                        }

                        Notification::make()
                            ->title('Proje eklendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Project $record): string => route('projects.show', $record->slug), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->form($this->getProjectFormSchema())
                    ->fillForm(function (Project $record): array {
                        $project = $this->fullProjectRecord($record);

                        return array_merge($project->toArray(), [
                            'image_name' => $project->image ? pathinfo($project->image, PATHINFO_FILENAME) : '',
                        ]);
                    })
                    ->action(function (Project $record, array $data): void {
                        $imageName = $data['image_name'] ?? null;
                        unset($data['image_name']);

                        $record->update($data);

                        if ($imageName && $record->image) {
                            $currentBase = pathinfo($record->image, PATHINFO_FILENAME);
                            if (Str::slug($imageName) !== $currentBase) {
                                $newPath = $this->renameStoredImage($record->image, $imageName, 'projects');
                                if ($newPath) {
                                    $record->update(['image' => $newPath]);
                                }
                            }
                        }

                        if ($record->image) {
                            app(ThumbnailService::class)->generate('projects', $record->image);
                        }

                        Notification::make()
                            ->title('Proje güncellendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('kopyala')
                        ->label('Kopyala')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('info')
                        ->action(function ($records): void {
                            foreach ($records as $project) {
                                $copy = $this->fullProjectRecord($project)->replicate();
                                $copy->title = $project->title.' (Kopya)';
                                $copy->slug = Str::slug($copy->title).'-'.uniqid();
                                $copy->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen projeler kopyalandı.'),
                    Tables\Actions\BulkAction::make('aktifYap')
                        ->label('Aktif yap')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen projeler aktif yapıldı.'),
                    Tables\Actions\BulkAction::make('pasifYap')
                        ->label('Pasif yap')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen projeler pasif yapıldı.'),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Project $record): bool => true);
    }

    protected function fullProjectRecord(Project $record): Project
    {
        return Project::query()->findOrFail($record->getKey());
    }

    protected function getProjectFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Proje bilgileri')
                ->schema([
                    Forms\Components\Select::make('project_category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()
                        ->placeholder('Kategori seçin'),
                    Forms\Components\TextInput::make('title')
                        ->label('Başlık (SEO)')
                        ->required()
                        ->maxLength(70)
                        ->live(debounce: 500)
                        ->helperText(fn ($state) => mb_strlen((string) $state).' / 70 karakter. 50-60 önerilir.'),
                    Forms\Components\Textarea::make('short_description')
                        ->label('Kısa açıklama (Meta açıklama)')
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
                    Forms\Components\RichEditor::make('content')
                        ->label('Açıklama')
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('projects')
                        ->helperText('Sayfa içeriğinde gösterilecek metin. Editör ile biçimlendirebilirsiniz.'),
                    Forms\Components\TextInput::make('slug')
                        ->label('SEO URL adresi')
                        ->required()
                        ->maxLength(255)
                        ->unique(Project::class, 'slug', ignoreRecord: true)
                        ->rules(['alpha_dash:ascii'])
                        ->placeholder('ip-kamera-kurulumu')
                        ->helperText('Boş bırakılırsa başlıktan otomatik oluşturulur.'),
                    Forms\Components\FileUpload::make('image')
                        ->label('Görsel')
                        ->disk('public')
                        ->directory('projects')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                        ->maxSize(2048)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->helperText('Önerilen: 1200x630 px. Max 2 MB.'),
                    Forms\Components\TextInput::make('image_name')
                        ->label('Görsel dosya adı')
                        ->placeholder('Örn: proje-gorseli')
                        ->maxLength(255)
                        ->helperText('İsteğe bağlı. Uzantı otomatik eklenir.'),
                    Forms\Components\TextInput::make('icon')
                        ->label('İkon (sınıf adı)')
                        ->maxLength(100)
                        ->placeholder('Örn: flaticon-camera'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),
        ];
    }

    protected function renameStoredImage(string $currentPath, string $newNameWithoutExt, string $directory = 'projects'): ?string
    {
        $disk = Storage::disk('public');
        $path = str_replace('\\', '/', $currentPath);
        $path = ltrim($path, '/');

        if (! str_starts_with($path, $directory.'/')) {
            $path = $directory.'/'.$path;
        }

        if (! $disk->exists($path)) {
            return null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $base = Str::slug($newNameWithoutExt) ?: 'image';
        $newPath = $directory.'/'.$base.'.'.$ext;

        if ($disk->exists($newPath) && $newPath !== $path) {
            $newPath = $directory.'/'.$base.'-'.uniqid().'.'.$ext;
        }

        if ($newPath === $path) {
            return $path;
        }

        $disk->move($path, $newPath);

        return $newPath;
    }
}
