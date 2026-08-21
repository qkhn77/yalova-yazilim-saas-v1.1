<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BlogAyarlar extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Blog Ayarlari';

    protected static ?string $slug = 'bloglar/ayarlar';

    protected static string $view = 'filament.clusters.web.pages.list-page-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'meta_title' => Setting::get('blog_index_meta_title', 'Yalova Kamera Kurulumu Blog | Güvenlik Sistemi İpuçları'),
            'meta_description' => Setting::get('blog_index_meta_description', 'Yalova kamera kurulumu, güvenlik sistemi ipuçları ve CCTV rehberleri. Profesyonel kamera montajı, alarm sistemi kurulumu hakkında detaylı bilgiler.'),
            'meta_keywords' => Setting::get('blog_index_meta_keywords', 'yalova kamera kurulumu, güvenlik kamerası kurulumu, CCTV montajı, alarm sistemi, güvenlik ipuçları'),
            'header_title' => Setting::get('blog_index_header_title', 'Blog'),
            'section_badge' => Setting::get('blog_index_section_badge', 'Blog'),
            'section_heading' => Setting::get('blog_index_section_heading', '<span>İpuçları ve</span> rehber içerikler'),
            'empty_text' => Setting::get('blog_index_empty_text', 'Henüz blog yazısı eklenmemiş.'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SEO Ayarlari')
                    ->description('Blog liste sayfasinin arama motoru ve sosyal onizleme alanlarini yonetin.')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta baslik')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta aciklama')
                            ->rows(3)
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('meta_keywords')
                            ->label('Meta anahtar kelimeler')
                            ->maxLength(500),
                    ]),
                Forms\Components\Section::make('Sayfa Icerigi')
                    ->description('Kategori secilmemis ana Blog sayfasinda gorunen metinleri duzenleyin.')
                    ->schema([
                        Forms\Components\TextInput::make('header_title')
                            ->label('Ust baslik')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('section_badge')
                            ->label('Bolum etiketi')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('section_heading')
                            ->label('Ana baslik')
                            ->rows(3)
                            ->helperText('Vurgulu alan kullanmak isterseniz span etiketi ekleyebilirsiniz.'),
                        Forms\Components\Textarea::make('empty_text')
                            ->label('Bos liste mesaji')
                            ->rows(2)
                            ->maxLength(500),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set('blog_index_'.$key, $value, 'web_pages');
        }

        Notification::make()
            ->title('Blog sayfasi ayarlari kaydedildi.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }
}
