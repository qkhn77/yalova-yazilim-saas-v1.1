<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\BilgiSayfa;
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

class BilgiSayfalari extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Bilgi Sayfaları';

    protected static ?string $slug = 'sayfalar/bilgi-sayfalari';

    protected static string $view = 'filament.clusters.web.pages.bilgi-sayfalari';

    public function getTitle(): string|Htmlable
    {
        return 'Bilgi Sayfaları';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Bilgi Sayfaları';
    }

    public function getSubheading(): ?string
    {
        return 'Gizlilik politikası, iade politikası gibi bilgilendirici sayfaları buradan yönetebilirsiniz.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(BilgiSayfa::query()->select(['id', 'title', 'slug', 'published_at', 'is_active', 'sort_order']))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Başlık')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Yayın Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('-')
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
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Bilgi sayfası ekle')
                    ->modalHeading('Bilgi sayfası ekle')
                    ->icon('heroicon-o-plus')
                    ->form($this->getBilgiFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        if (empty($data['slug']) && ! empty($data['title'])) {
                            $data['slug'] = Str::slug($data['title']);
                        }

                        return $data;
                    })
                    ->action(function (array $data): void {
                        BilgiSayfa::create($data);

                        Notification::make()
                            ->title('Bilgi sayfası eklendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Görüntüle')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BilgiSayfa $record): string => route('information.show', $record->slug), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->icon('heroicon-o-pencil-square')
                    ->form($this->getBilgiFormSchema())
                    ->action(function (BilgiSayfa $record, array $data): void {
                        $record->update($data);

                        Notification::make()
                            ->title('Bilgi sayfası güncellendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('aktifYap')
                        ->label('Aktif yap')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen sayfalar aktif yapıldı.'),
                    Tables\Actions\BulkAction::make('pasifYap')
                        ->label('Pasif yap')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Seçilen sayfalar pasif yapıldı.'),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    protected function getBilgiFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Bilgi Sayfası')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation === 'create' && $state) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug (SEO URL)')
                        ->required()
                        ->unique(BilgiSayfa::class, 'slug', ignoreRecord: true)
                        ->rules(['alpha_dash:ascii'])
                        ->maxLength(255),
                    Forms\Components\TextInput::make('author_name')
                        ->label('Yazar')
                        ->maxLength(255),
                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Yayın Tarihi')
                        ->seconds(false),
                    Forms\Components\FileUpload::make('featured_image')
                        ->label('Kapak Görseli')
                        ->disk('public')
                        ->directory('info-pages')
                        ->visibility('public')
                        ->image()
                        ->imageEditor(),
                    Forms\Components\RichEditor::make('content')
                        ->label('İçerik')
                        ->required()
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('info-pages'),
                    Forms\Components\TextInput::make('tags')
                        ->label('Etiketler')
                        ->placeholder('gizlilik, iade, teslimat')
                        ->helperText('Virgül ile ayırın.')
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('meta_title')
                        ->label('Meta Başlık')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('meta_description')
                        ->label('Meta Açıklama')
                        ->rows(3)
                        ->maxLength(500),
                    Forms\Components\TextInput::make('meta_keywords')
                        ->label('Meta Anahtar Kelimeler')
                        ->maxLength(500),
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
}
