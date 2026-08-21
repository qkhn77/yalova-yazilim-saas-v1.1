<?php

namespace App\Filament\Pages;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PostCategory;
use App\Models\ProjectCategory;
use App\Models\ServiceCategory;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page as BasePage;

class ModulMenu extends BasePage
{
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $navigationGroup = 'Modüller';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Menü';
    protected static ?string $title = 'Menü Düzenleme';
    protected static string $view = 'filament.pages.modul-menu';
    protected static string $routePath = 'moduller/menu';

    public ?array $data = [];

    public static function getRoutePath(): string
    {
        return static::$routePath;
    }

    public function mount(): void
    {
        $this->form->fill([
            'menu_items' => MenuItem::getTreeForAdmin(),
            'menu_bg_color' => Setting::get('menu_bg_color', '#ffffff'),
            'menu_text_color' => Setting::get('menu_text_color', '#333333'),
            'menu_hover_bg' => Setting::get('menu_hover_bg', ''),
            'menu_hover_text' => Setting::get('menu_hover_text', '#0066cc'),
            'menu_active_bg' => Setting::get('menu_active_bg', ''),
            'menu_active_text' => Setting::get('menu_active_text', '#0066cc'),
        ]);
    }

    public function form(Form $form): Form
    {
        $linkTypeOptions = [
            'home' => 'Anasayfa',
            'custom' => 'Özel URL',
            'page' => 'Sayfa',
            'services' => 'Servisler',
            'service_category' => 'Servis Kategorisi',
            'projects' => 'Projeler',
            'project_category' => 'Proje Kategorisi',
            'blog' => 'Blog',
            'post_category' => 'Blog Kategorisi',
        ];

        $itemSchema = [
            Forms\Components\Select::make('type')
                ->label('Tür')
                ->options($linkTypeOptions)
                ->required()
                ->live()
                ->default('custom'),
            Forms\Components\TextInput::make('label')
                ->label('Menü metni')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('url')
                ->label('URL (sadece Özel URL seçiliyse)')
                ->url()
                ->visible(fn (Get $get) => $get('type') === 'custom'),
            Forms\Components\Select::make('link_id')
                ->label('Bağlantı')
                ->options(function (Get $get) {
                    return match ($get('type')) {
                        'page' => Page::where('is_active', true)->pluck('title', 'id')->all(),
                        'service_category' => ServiceCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all(),
                        'project_category' => ProjectCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all(),
                        'post_category' => PostCategory::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all(),
                        default => [],
                    };
                })
                ->searchable()
                ->visible(fn (Get $get) => in_array($get('type'), ['page', 'service_category', 'project_category', 'post_category'], true)),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ];

        return $form
            ->schema([
                Forms\Components\Section::make('Menü renk ayarları')
                    ->description('Site başlığındaki menü çubuğu renkleri. Boş bırakırsanız tema varsayılanı kullanılır.')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\TextInput::make('menu_bg_color')
                                ->label('Menü arka plan rengi')
                                ->placeholder('#ffffff'),
                            Forms\Components\TextInput::make('menu_text_color')
                                ->label('Menü yazı rengi')
                                ->placeholder('#333333'),
                            Forms\Components\TextInput::make('menu_hover_bg')
                                ->label('Hover arka plan')
                                ->placeholder('Boş bırakılabilir'),
                            Forms\Components\TextInput::make('menu_hover_text')
                                ->label('Hover yazı rengi')
                                ->placeholder('#0066cc'),
                            Forms\Components\TextInput::make('menu_active_bg')
                                ->label('Aktif sayfa arka plan')
                                ->placeholder('Boş bırakılabilir'),
                            Forms\Components\TextInput::make('menu_active_text')
                                ->label('Aktif sayfa yazı rengi')
                                ->placeholder('#0066cc'),
                        ]),
                    ]),
                Forms\Components\Section::make('Menü öğeleri')
                    ->description('Üst menü ve alt menü (2. seviye) ekleyip sıralayabilirsiniz. Sürükleyerek sıra değiştirin.')
                    ->schema([
                        Forms\Components\Repeater::make('menu_items')
                            ->label('')
                            ->schema([
                                ...$itemSchema,
                                Forms\Components\Repeater::make('children')
                                    ->label('Alt menü (2. seviye)')
                                    ->schema($itemSchema)
                                    ->defaultItems(0)
                                    ->addActionLabel('Alt menü ekle')
                                    ->reorderable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state) => ($state['label'] ?? '') ?: 'Alt öğe'),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Menü öğesi ekle')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state) => $state['label'] ?? 'Yeni öğe')
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $items = $data['menu_items'] ?? [];
        MenuItem::syncFromArray($items);

        foreach (['menu_bg_color', 'menu_text_color', 'menu_hover_bg', 'menu_hover_text', 'menu_active_bg', 'menu_active_text'] as $key) {
            Setting::set($key, $data[$key] ?? '', 'menu');
        }

        Notification::make()->title('Menü kaydedildi')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')->label('Kaydet')->action('save')->color('primary'),
        ];
    }
}
