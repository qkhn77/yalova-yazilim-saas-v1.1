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

class WebProjeAyarlar extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Proje Ayarlari';

    protected static ?string $slug = 'projeler/ayarlar';

    protected static string $view = 'filament.clusters.web.pages.list-page-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'meta_title' => Setting::get('projects_index_meta_title', 'Yalova Kamera Kurulumu Projeleri | Güvenlik Sistemi Örnekleri'),
            'meta_description' => Setting::get('projects_index_meta_description', 'IP kamera kurulumu, alarm sistemleri, montaj. Yalova genelinde profesyonel projeler.'),
            'meta_keywords' => Setting::get('projects_index_meta_keywords', 'yalova kamera kurulumu projeleri, güvenlik kamerası montajı, CCTV projeleri, alarm sistemi örnekleri'),
            'header_title' => Setting::get('projects_index_header_title', 'Projeler'),
            'section_badge' => Setting::get('projects_index_section_badge', 'Projeler'),
            'section_heading' => Setting::get('projects_index_section_heading', '<span>Kapsamlı güvenlik</span> ve izleme çözümleri'),
            'empty_text' => Setting::get('projects_index_empty_text', 'Henüz proje eklenmemiş. Admin panelinden ekleyebilirsiniz.'),
            'footer_cta_text' => Setting::get('projects_index_footer_cta_text', '<span>Ücretsiz</span> keşif ve teklif için <a href="'.route('contact').'">iletişime geç</a>'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SEO Ayarlari')
                    ->description('Projeler liste sayfasinin arama motoru ve sosyal onizleme alanlarini yonetin.')
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
                    ->description('Kategori secilmemis ana Projeler sayfasinda gorunen metinleri duzenleyin.')
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
                        Forms\Components\Textarea::make('footer_cta_text')
                            ->label('Alt CTA metni')
                            ->rows(2)
                            ->helperText('HTML link veya span etiketi kullanabilirsiniz.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set('projects_index_'.$key, $value, 'web_pages');
        }

        Notification::make()
            ->title('Proje sayfasi ayarlari kaydedildi.')
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
