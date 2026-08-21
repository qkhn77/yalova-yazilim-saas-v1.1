<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\MenuItem;
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
use Illuminate\Support\Facades\Route;

class ModulMenu extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Menü Düzenleme';

    protected static ?string $slug = 'web-ayarlar/menu-ayarlari/menu-duzenleme';

    protected static string $view = 'filament.clusters.web.pages.modul-menu';

    public function mount(): void
    {
        $this->backfillLegacyMenuTitles();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Menü Düzenleme';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Menü Düzenleme';
    }

    public function getSubheading(): ?string
    {
        return 'Üst menü ve alt menüleri teknik bilgi gerektirmeden kolayca yönetebilirsiniz.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(MenuItem::query()
                ->select([
                    'id',
                    'parent_id',
                    'type',
                    'label',
                    'title',
                    'url',
                    'link_id',
                    'route_name',
                    'target_type',
                    'icon',
                    'css_class',
                    'menu_location',
                    'sort_order',
                    'is_active',
                ])
                ->with(['parent:id,title,label']))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Menü Başlığı')
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($inner) use ($search): void {
                            $inner->where('title', 'like', "%{$search}%")
                                ->orWhere('label', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->getStateUsing(fn (MenuItem $record): string => (string) ($record->title ?: $record->label))
                    ->formatStateUsing(fn (string $state, MenuItem $record): string => $record->parent_id ? '— '.$state : $state),
                Tables\Columns\TextColumn::make('menu_location')
                    ->label('Konum')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'footer' ? 'Footer' : 'Ana Menü')
                    ->color(fn (string $state): string => $state === 'footer' ? 'gray' : 'primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.title')
                    ->label('Üst Menü')
                    ->getStateUsing(fn (MenuItem $record): string => (string) ($record->parent?->title ?: $record->parent?->label ?: 'Ana Menü'))
                    ->placeholder('Ana Menü')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sıralama')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('target_type')
                    ->label('Yeni Sekme')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-top-right-on-square')
                    ->falseIcon('heroicon-o-window')
                    ->getStateUsing(fn (MenuItem $record): bool => $record->target_type === 'new_tab')
                    ->alignCenter(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->paginated([10, 20, 50, 100, 1000, 'all'])
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('menu_location')
                    ->label('Menü Konumu')
                    ->options([
                        'primary' => 'Ana Menü',
                        'footer' => 'Footer',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif Durum'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Menü Ekle')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Yeni Menü Ekle')
                    ->form($this->getMenuFormSchema())
                    ->mutateFormDataUsing(fn (array $data): array => $this->normalizeFormData($data))
                    ->action(function (array $data): void {
                        MenuItem::create($data);
                        Notification::make()->title('Menü eklendi.')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Düzenle')
                    ->modalHeading('Menü Düzenle')
                    ->form(fn (MenuItem $record): array => $this->getMenuFormSchema($record))
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['title'] = $data['title'] ?: ($data['label'] ?? '');
                        $data['link_type'] = ! empty($data['route_name']) ? 'internal' : 'external';
                        $data['custom_url'] = null;

                        if (($data['link_type'] ?? 'internal') === 'external' && ! empty($data['url']) && ! array_key_exists($data['url'], $this->getMenuUrlOptions())) {
                            $data['custom_url'] = $data['url'];
                            $data['url'] = '__custom__';
                        }

                        return $data;
                    })
                    ->mutateFormDataUsing(fn (array $data): array => $this->normalizeFormData($data))
                    ->action(function (MenuItem $record, array $data): void {
                        if (($data['parent_id'] ?? null) && (int) $data['parent_id'] === (int) $record->id) {
                            Notification::make()->title('Bir menü kendisini üst menü olarak seçemez.')->danger()->send();

                            return;
                        }

                        $record->update($data);
                        Notification::make()->title('Menü güncellendi.')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('aktifYap')
                        ->label('Aktif Yap')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('pasifYap')
                        ->label('Pasif Yap')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function getMenuFormSchema(?MenuItem $record = null): array
    {
        return [
            Forms\Components\Section::make('Temel Bilgiler')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Menü Başlığı')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Radio::make('link_type')
                        ->label('Menü Türü')
                        ->options([
                            'internal' => 'Dahili (Route)',
                            'external' => 'Harici (URL)',
                        ])
                        ->default('internal')
                        ->live()
                        ->dehydrated(false)
                        ->required(),
                    Forms\Components\Select::make('route_name')
                        ->label('Route Adı')
                        ->options($this->getMenuRouteOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Bir route seçin')
                        ->visible(fn (Forms\Get $get): bool => $get('link_type') === 'internal')
                        ->required(fn (Forms\Get $get): bool => $get('link_type') === 'internal'),
                    Forms\Components\Select::make('url')
                        ->label('URL')
                        ->options($this->getMenuUrlOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Bir URL seçin')
                        ->visible(fn (Forms\Get $get): bool => $get('link_type') === 'external')
                        ->required(fn (Forms\Get $get): bool => $get('link_type') === 'external' && $get('url') !== '__custom__')
                        ->live(),
                    Forms\Components\TextInput::make('custom_url')
                        ->label('Özel URL')
                        ->placeholder('/kampanya veya https://site.com')
                        ->visible(fn (Forms\Get $get): bool => $get('link_type') === 'external' && $get('url') === '__custom__')
                        ->required(fn (Forms\Get $get): bool => $get('link_type') === 'external' && $get('url') === '__custom__')
                        ->maxLength(255),
                    Forms\Components\Select::make('target_type')
                        ->label('Açılma Şekli')
                        ->options([
                            'same_tab' => 'Aynı Sekmede Aç',
                            'new_tab' => 'Yeni Sekmede Aç',
                        ])
                        ->default('same_tab')
                        ->required(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Menü Yapısı')
                ->schema([
                    Forms\Components\Select::make('menu_location')
                        ->label('Menü Konumu')
                        ->options([
                            'primary' => 'Ana Menü',
                            'footer' => 'Footer',
                        ])
                        ->default('primary')
                        ->required(),
                    Forms\Components\Select::make('parent_id')
                        ->label('Üst Menü')
                        ->placeholder('Ana Menü olarak kalsın')
                        ->options(function () use ($record): array {
                            return MenuItem::query()
                                ->whereNull('parent_id')
                                ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (MenuItem $item) => [$item->id => ($item->title ?: $item->label ?: 'Menü #'.$item->id)])
                                ->all();
                        })
                        ->searchable(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sıralama')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ])
                ->columns(3),

            Forms\Components\Section::make('Durum')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif / Pasif')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Gelişmiş (Opsiyonel)')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('css_class')
                        ->label('CSS Class')
                        ->placeholder('ornek-class')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon')
                        ->placeholder('heroicon-o-home')
                        ->maxLength(255),
                ])
                ->columns(2),
        ];
    }

    protected function normalizeFormData(array $data): array
    {
        $linkType = $data['link_type'] ?? null;

        if ($linkType === null) {
            $hasRoute = filled($data['route_name'] ?? null);
            $hasUrl = filled($data['url'] ?? null) || filled($data['custom_url'] ?? null);

            $linkType = $hasUrl && ! $hasRoute ? 'external' : 'internal';
        }

        if ($linkType === 'internal') {
            $data['url'] = null;
        } else {
            $data['route_name'] = null;
            if (($data['url'] ?? null) === '__custom__') {
                $data['url'] = trim((string) ($data['custom_url'] ?? ''));
            }
        }

        if (empty($data['target_type'])) {
            $data['target_type'] = 'same_tab';
        }

        $data['type'] = $data['type'] ?? 'custom';
        $data['label'] = $data['title'] ?? ($data['label'] ?? '');
        $data['link_id'] = $data['link_id'] ?? null;
        unset($data['custom_url']);

        return $data;
    }

    protected function backfillLegacyMenuTitles(): void
    {
        MenuItem::query()
            ->whereNull('title')
            ->whereNotNull('label')
            ->each(function (MenuItem $item): void {
                $item->update([
                    'title' => $item->label,
                ]);
            });
    }

    /**
     * @return array<string, string>
     */
    protected function getMenuRouteOptions(): array
    {
        $adaylar = [
            'home' => 'Ana Sayfa',
            'about' => 'Hakkımızda',
            'contact' => 'İletişim',
            'services.index' => 'Servisler',
            'projects.index' => 'Projeler',
            'blog.index' => 'Blog',
            'products.index' => 'Ürünler',
            'cart.index' => 'Sepet',
        ];

        return collect($adaylar)
            ->filter(fn (string $label, string $routeName): bool => Route::has($routeName))
            ->map(fn (string $label, string $routeName): string => $label.' ('.$routeName.')')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function getMenuUrlOptions(): array
    {
        return [
            '/' => 'Ana Sayfa (/)',
            '/hakkimizda' => 'Hakkımızda (/hakkimizda)',
            '/iletisim' => 'İletişim (/iletisim)',
            '/Servisler' => 'Servisler (/Servisler)',
            '/Projeler' => 'Projeler (/Projeler)',
            '/blog' => 'Blog (/blog)',
            '/urunler' => 'Ürünler (/urunler)',
            '/sepet' => 'Sepet (/sepet)',
            '__custom__' => 'Özel URL gir',
        ];
    }
}
