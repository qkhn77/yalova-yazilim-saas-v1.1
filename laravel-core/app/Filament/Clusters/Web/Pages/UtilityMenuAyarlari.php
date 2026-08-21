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

class UtilityMenuAyarlari extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Utility Menü';

    protected static ?string $slug = 'web-ayarlar/menu-ayarlari/utility-menu';

    protected static string $view = 'filament.clusters.web.pages.utility-menu-ayarlari';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'utility_menu_enabled' => filter_var(Setting::get('utility_menu_enabled', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_language' => filter_var(Setting::get('utility_menu_show_language', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_currency' => filter_var(Setting::get('utility_menu_show_currency', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_search' => filter_var(Setting::get('utility_menu_show_search', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_campaign' => filter_var(Setting::get('utility_menu_show_campaign', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_account_links' => filter_var(Setting::get('utility_menu_show_account_links', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_cart' => filter_var(Setting::get('utility_menu_show_cart', true), FILTER_VALIDATE_BOOL),
            'utility_menu_show_customer_service' => filter_var(Setting::get('utility_menu_show_customer_service', true), FILTER_VALIDATE_BOOL),
            'utility_menu_campaign_text' => Setting::get('ust_kampanya_duyurusu', __('front.utility.campaign_default')),
            'utility_menu_customer_service_label' => Setting::get('musteri_hizmetleri_etiket', __('front.utility.customer_services')),
            'utility_menu_search_placeholder' => Setting::get('utility_menu_search_placeholder', __('front.utility.search_placeholder')),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Görünürlük')
                    ->description('Utility menünün genel görünürlüğünü ve içerik bloklarını yönetin.')
                    ->icon('heroicon-o-eye')
                    ->schema([
                        Forms\Components\Toggle::make('utility_menu_enabled')
                            ->label('Utility menü aktif')
                            ->helperText('Kapalı olduğunda ön yüzde üst utility menü tamamen gizlenir.')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_language')
                            ->label('Dil seçici görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_currency')
                            ->label('Para birimi seçici görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_search')
                            ->label('Site içi arama görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_campaign')
                            ->label('Kampanya duyurusu görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_account_links')
                            ->label('Giriş / hesap bağlantıları görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_cart')
                            ->label('Sepet bağlantısı görünür')
                            ->default(true),
                        Forms\Components\Toggle::make('utility_menu_show_customer_service')
                            ->label('Müşteri hizmetleri bağlantısı görünür')
                            ->default(true),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('İçerik')
                    ->description('Utility menüde görünen metinleri bu alandan düzenleyin.')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Forms\Components\Textarea::make('utility_menu_campaign_text')
                            ->label('Kampanya duyurusu metni')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder(__('front.utility.campaign_default')),
                        Forms\Components\TextInput::make('utility_menu_customer_service_label')
                            ->label('Müşteri hizmetleri etiketi')
                            ->maxLength(100)
                            ->placeholder(__('front.utility.customer_services')),
                        Forms\Components\TextInput::make('utility_menu_search_placeholder')
                            ->label('Arama kutusu placeholder')
                            ->maxLength(100)
                            ->placeholder(__('front.utility.search_placeholder')),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value, 'utility_menu');
        }

        if (array_key_exists('utility_menu_campaign_text', $data)) {
            Setting::set('ust_kampanya_duyurusu', $data['utility_menu_campaign_text'] ?? '', 'utility_menu');
        }

        if (array_key_exists('utility_menu_customer_service_label', $data)) {
            Setting::set('musteri_hizmetleri_etiket', $data['utility_menu_customer_service_label'] ?? '', 'utility_menu');
        }

        Notification::make()
            ->title('Utility menü ayarları kaydedildi.')
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
