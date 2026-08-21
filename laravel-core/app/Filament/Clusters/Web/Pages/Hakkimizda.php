<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\AboutPage;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class Hakkimizda extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    private const BOLUMLER = [
        'seo' => 'SEO',
        'gorunurluk' => 'Görünürlük',
        'ust_baslik' => 'Üst başlık',
        'hakkimizda' => 'Hakkımızda',
        'misyon_vizyon' => 'Misyon & Vizyon',
        'neden_biz' => 'Neden biz',
        'taahhut' => 'Taahhüt',
        'uzmanlik' => 'Uzmanlık',
        'ne_yapiyoruz' => 'Ne yapıyoruz',
        'ekip' => 'Ekip',
        'destek' => 'Destek',
        'yorumlar' => 'Yorumlar',
        'iletisim' => 'İletişim',
        'sss' => 'SSS',
    ];

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Hakkımızda';

    protected static ?string $slug = 'sayfalar/hakkimizda';

    protected static string $view = 'filament.clusters.web.pages.hakkimizda';

    public ?array $data = [];

    public string $aktifBolum = 'seo';

    public function mount(): void
    {
        if (! Schema::hasTable('about_pages')) {
            $this->form->fill(AboutPage::defaults());
            return;
        }

        $about = AboutPage::instance();
        $defaultImageData = $this->ensureDefaultImages();
        $existingData = array_filter(
            $about->toArray(),
            fn (mixed $value): bool => $value !== null && $value !== ''
        );

        $this->form->fill(array_replace(AboutPage::defaults(), $defaultImageData, $existingData));
    }

    public function form(Form $form): Form
    {
        FileUpload::configureUsing(function (FileUpload $component): void {
            $component
                ->panelLayout('compact')
                ->imagePreviewHeight('120')
                ->extraAttributes([
                    'style' => 'max-width: 340px;',
                ]);
        });

        return $form
            ->schema(match ($this->aktifBolum) {
                'seo' => [
                Section::make('SEO')
                    ->description('Hakkımızda sayfasının Google arama sonuçlarında görünen meta alanları.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->visible(fn (): bool => $this->aktifBolum === 'seo')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Sayfa başlığı')
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label('Meta açıklama')
                            ->rows(3),
                        Textarea::make('meta_keywords')
                            ->label('Meta anahtar kelimeler')
                            ->rows(2),
                    ])
                    ->columns(1),

                ],
                'gorunurluk' => [
                Section::make('Bölüm Görünürlüğü')
                    ->description('Hakkımızda sayfasındaki ana blokları buradan aktif veya pasif yapabilirsiniz.')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (): bool => $this->aktifBolum === 'gorunurluk')
                    ->schema([
                        Forms\Components\Toggle::make('show_header_section')->label('Üst başlık alanı'),
                        Forms\Components\Toggle::make('show_about_section')->label('Hakkımızda bölümü'),
                        Forms\Components\Toggle::make('show_mission_vision_section')->label('Misyon & Vizyon bölümü'),
                        Forms\Components\Toggle::make('show_why_choose_section')->label('Neden bizi tercih etmelisiniz bölümü'),
                        Forms\Components\Toggle::make('show_commitment_section')->label('Taahhüdümüz bölümü'),
                        Forms\Components\Toggle::make('show_expertise_section')->label('Uzmanlığımız bölümü'),
                        Forms\Components\Toggle::make('show_what_we_do_section')->label('Ne yapıyoruz bölümü'),
                        Forms\Components\Toggle::make('show_team_section')->label('Uzman ekibimiz bölümü'),
                        Forms\Components\Toggle::make('show_support_section')->label('Teknik destek bölümü'),
                        Forms\Components\Toggle::make('show_testimonials_section')->label('Müşteri yorumları bölümü'),
                        Forms\Components\Toggle::make('show_cta_section')->label('İletişim CTA bölümü'),
                        Forms\Components\Toggle::make('show_faq_section')->label('Sık sorulan sorular bölümü'),
                    ])
                    ->columns(2),

                ],
                'ust_baslik' => [
                Section::make('Sayfa Üst Başlık')
                    ->description('Sayfanın üst başlığı')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (): bool => $this->aktifBolum === 'ust_baslik')
                    ->headerActions([$this->visibilityAction('show_header_section')])
                    ->schema([
                        TextInput::make('header_h1')
                            ->label('H1 Başlık')
                            ->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'hakkimizda' => [
                Section::make('Hakkımızda')
                    ->description('Hakkımızda bölüm başlıkları ve görseller')
                    ->icon('heroicon-o-information-circle')
                    ->visible(fn (): bool => $this->aktifBolum === 'hakkimizda')
                    ->headerActions([$this->visibilityAction('show_about_section')])
                    ->schema([
                        FileUpload::make('about_img_1')
                            ->label('Hakkımızda Görsel 1')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        FileUpload::make('about_circle_icon')
                            ->label('Deneyim/Çember İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        FileUpload::make('about_img_2')
                            ->label('Hakkımızda Görsel 2')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('about_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('about_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('about_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('about_paragraph')->label('Açıklama')->rows(4),

                        FileUpload::make('about_experience_image')
                            ->label('Hakkımızda Experience Görseli')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        FileUpload::make('about_icon_experience')
                            ->label('Experience İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('about_experience_heading')->label('Alt Kart Başlığı')->maxLength(255),

                        FileUpload::make('about_icon_contact')
                            ->label('Contact İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('about_contact_prompt')->label('İletişim Kutusu Üst Metni')->maxLength(255),
                        TextInput::make('about_phone_text')->label('Telefon Metni')->maxLength(50),
                        TextInput::make('about_contact_button_text')->label('Buton Metni')->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'misyon_vizyon' => [
                Section::make('Misyon & Vizyon')
                    ->description('Misyon, vizyon ve hedef metinleri + görseller')
                    ->icon('heroicon-o-briefcase')
                    ->visible(fn (): bool => $this->aktifBolum === 'misyon_vizyon')
                    ->headerActions([$this->visibilityAction('show_mission_vision_section')])
                    ->schema([
                        FileUpload::make('mission_vision_image')
                            ->label('Misyon/Vizyon Görseli')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        FileUpload::make('icon_mission')
                            ->label('Misyon İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('mission_heading')->label('Misyon Başlık')->maxLength(255),
                        Textarea::make('mission_text')->label('Misyon Metni')->rows(3),
                        FileUpload::make('icon_vision')
                            ->label('Vizyon İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('vision_heading')->label('Vizyon Başlık')->maxLength(255),
                        Textarea::make('vision_text')->label('Vizyon Metni')->rows(3),
                        FileUpload::make('icon_goal')
                            ->label('Hedef İkon')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                        TextInput::make('goal_heading')->label('Hedef Başlık')->maxLength(255),
                        Textarea::make('goal_text')->label('Hedef Metni')->rows(3),
                    ])
                    ->columns(1),

                ],
                'neden_biz' => [
                Section::make('Neden Bizi Tercih Etmelisiniz?')
                    ->description('Neden bizi tercih etmeli? metinler ve görseller')
                    ->icon('heroicon-o-heart')
                    ->visible(fn (): bool => $this->aktifBolum === 'neden_biz')
                    ->headerActions([$this->visibilityAction('show_why_choose_section')])
                    ->schema([
                        TextInput::make('why_choose_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('why_choose_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('why_choose_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),

                        FileUpload::make('why_choose_icon_1')->label('İkon 1')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('why_choose_item_1_title')->label('Başlık 1')->maxLength(255),
                        Textarea::make('why_choose_item_1_text')->label('Metin 1')->rows(3),

                        FileUpload::make('why_choose_icon_2')->label('İkon 2')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('why_choose_item_2_title')->label('Başlık 2')->maxLength(255),
                        Textarea::make('why_choose_item_2_text')->label('Metin 2')->rows(3),

                        FileUpload::make('why_choose_icon_3')->label('İkon 3')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('why_choose_item_3_title')->label('Başlık 3')->maxLength(255),
                        Textarea::make('why_choose_item_3_text')->label('Metin 3')->rows(3),

                        FileUpload::make('why_choose_icon_4')->label('İkon 4')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('why_choose_item_4_title')->label('Başlık 4')->maxLength(255),
                        Textarea::make('why_choose_item_4_text')->label('Metin 4')->rows(3),
                        FileUpload::make('why_choose_image')
                            ->label('Neden Görseli')
                            ->image()
                            ->disk('public')
                            ->directory('about')
                            ->visibility('public'),
                    ])
                    ->columns(1),

                ],
                'taahhut' => [
                Section::make('Taahhüdümüz')
                    ->description('Taahhüdümüz metinleri ve görseller')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (): bool => $this->aktifBolum === 'taahhut')
                    ->headerActions([$this->visibilityAction('show_commitment_section')])
                    ->schema([
                        FileUpload::make('commitment_image_1')->label('Taahhüt Görsel 1')->image()->disk('public')->directory('about')->visibility('public'),

                        TextInput::make('satisfy_client_text')->label('Memnun Müşteri Metni')->maxLength(255),
                        FileUpload::make('satisfy_client_img_1')->label('Memnun Müşteri Görsel 1')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('satisfy_client_img_2')->label('Memnun Müşteri Görsel 2')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('satisfy_client_img_3')->label('Memnun Müşteri Görsel 3')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('satisfy_client_img_4')->label('Memnun Müşteri Görsel 4')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('satisfy_client_img_5')->label('Memnun Müşteri Görsel 5')->image()->disk('public')->directory('about')->visibility('public'),

                        FileUpload::make('commitment_image_2')->label('Taahhüt Görsel 2')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('commitment_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('commitment_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('commitment_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('commitment_paragraph')->label('Açıklama')->rows(4),
                        TextInput::make('commitment_counter_item_1')->label('Sayaç 1')->maxLength(255),
                        TextInput::make('commitment_counter_item_2')->label('Sayaç 2')->maxLength(255),
                        TextInput::make('commitment_counter_item_3')->label('Sayaç 3')->maxLength(255),
                        TextInput::make('commitment_list_item_1')->label('Liste 1')->maxLength(255),
                        TextInput::make('commitment_list_item_2')->label('Liste 2')->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'uzmanlik' => [
                Section::make('Uzmanlığımız')
                    ->description('Uzmanlık metinleri ve görseller')
                    ->icon('heroicon-o-light-bulb')
                    ->visible(fn (): bool => $this->aktifBolum === 'uzmanlik')
                    ->headerActions([$this->visibilityAction('show_expertise_section')])
                    ->schema([
                        TextInput::make('expertise_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('expertise_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('expertise_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('expertise_paragraph')->label('Açıklama')->rows(3),

                        FileUpload::make('expertise_icon_1')->label('İkon 1')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('expertise_item_1_title')->label('Başlık 1')->maxLength(255),
                        Textarea::make('expertise_item_1_text')->label('Metin 1')->rows(3),

                        FileUpload::make('expertise_icon_2')->label('İkon 2')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('expertise_item_2_title')->label('Başlık 2')->maxLength(255),
                        Textarea::make('expertise_item_2_text')->label('Metin 2')->rows(3),

                        TextInput::make('expertise_counter_label')->label('Sayaç Etiketi')->maxLength(255),
                        TextInput::make('expertise_counter_list_item_1')->label('Sayaç Liste 1')->maxLength(255),
                        TextInput::make('expertise_counter_list_item_2')->label('Sayaç Liste 2')->maxLength(255),

                        FileUpload::make('expertise_image')->label('Uzmanlık Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('expertise_phone_icon')->label('Telefon İkon')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('expertise_contact_heading')->label('İletişim Başlığı')->maxLength(255),
                        TextInput::make('expertise_phone_text')->label('Telefon Metni')->maxLength(50),
                    ])
                    ->columns(1),

                ],
                'ne_yapiyoruz' => [
                Section::make('Ne Yapıyoruz?')
                    ->description('Ne yapıyoruz bölüm metinleri ve görseller')
                    ->icon('heroicon-o-question-mark-circle')
                    ->visible(fn (): bool => $this->aktifBolum === 'ne_yapiyoruz')
                    ->headerActions([$this->visibilityAction('show_what_we_do_section')])
                    ->schema([
                        TextInput::make('what_we_do_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('what_we_do_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('what_we_do_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('what_we_do_paragraph_1')->label('Paragraf 1')->rows(3),
                        Textarea::make('what_we_do_paragraph_2')->label('Paragraf 2')->rows(3),

                        FileUpload::make('need_help_icon')->label('Need Help İkon')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('need_help_text')->label('Need Help Metni')->maxLength(255),
                        TextInput::make('what_we_do_phone_text')->label('Telefon Metni')->maxLength(50),

                        FileUpload::make('what_we_counter_icon_1')->label('Sayaç İkon 1')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('what_we_counter_label_1')->label('Sayaç 1 Metni')->maxLength(255),

                        FileUpload::make('what_we_counter_icon_2')->label('Sayaç İkon 2')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('what_we_counter_label_2')->label('Sayaç 2 Metni')->maxLength(255),

                        FileUpload::make('what_we_image')->label('What We Image')->image()->disk('public')->directory('about')->visibility('public'),
                    ])
                    ->columns(1),

                ],
                'ekip' => [
                Section::make('Uzman Ekibimiz')
                    ->description('Ekip metinleri ve görseller')
                    ->icon('heroicon-o-user-group')
                    ->visible(fn (): bool => $this->aktifBolum === 'ekip')
                    ->headerActions([$this->visibilityAction('show_team_section')])
                    ->schema([
                        TextInput::make('team_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('team_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('team_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('team_paragraph')->label('Açıklama')->rows(3),
                        TextInput::make('team_button_text')->label('Buton Metni')->maxLength(255),

                        FileUpload::make('team_img_1')->label('Ekip 1 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('team_title_1')->label('Ekip 1 Başlık')->maxLength(255),
                        TextInput::make('team_text_1')->label('Ekip 1 Metin')->maxLength(255),

                        FileUpload::make('team_img_2')->label('Ekip 2 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('team_title_2')->label('Ekip 2 Başlık')->maxLength(255),
                        TextInput::make('team_text_2')->label('Ekip 2 Metin')->maxLength(255),

                        FileUpload::make('team_img_3')->label('Ekip 3 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('team_title_3')->label('Ekip 3 Başlık')->maxLength(255),
                        TextInput::make('team_text_3')->label('Ekip 3 Metin')->maxLength(255),

                        FileUpload::make('team_img_4')->label('Ekip 4 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('team_title_4')->label('Ekip 4 Başlık')->maxLength(255),
                        TextInput::make('team_text_4')->label('Ekip 4 Metin')->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'destek' => [
                Section::make('Teknik Destek')
                    ->description('Teknik destek bölüm metinleri ve görseller')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->visible(fn (): bool => $this->aktifBolum === 'destek')
                    ->headerActions([$this->visibilityAction('show_support_section')])
                    ->schema([
                        TextInput::make('support_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('support_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('support_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('support_paragraph')->label('Açıklama')->rows(3),

                        FileUpload::make('support_image_1')->label('Görsel 1')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('support_image_2')->label('Görsel 2')->image()->disk('public')->directory('about')->visibility('public'),
                        FileUpload::make('support_circle_icon')->label('Çember İkon')->image()->disk('public')->directory('about')->visibility('public'),

                        FileUpload::make('support_icon_1')->label('Destek İkon 1')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('support_item_1_title')->label('Destek 1 Başlık')->maxLength(255),
                        Textarea::make('support_item_1_text')->label('Destek 1 Metin')->rows(3),

                        FileUpload::make('support_icon_2')->label('Destek İkon 2')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('support_item_2_title')->label('Destek 2 Başlık')->maxLength(255),
                        Textarea::make('support_item_2_text')->label('Destek 2 Metin')->rows(3),

                        TextInput::make('support_button_text')->label('Buton Metni')->maxLength(255),
                    ])
                    ->columns(1),

                ],
                'yorumlar' => [
                Section::make('Müşteri Yorumları')
                    ->description('Yorum başlıkları ve 3 yorum bloğu')
                    ->icon('heroicon-o-star')
                    ->visible(fn (): bool => $this->aktifBolum === 'yorumlar')
                    ->headerActions([$this->visibilityAction('show_testimonials_section')])
                    ->schema([
                        TextInput::make('testimonials_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('testimonials_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('testimonials_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        FileUpload::make('testimonial_quote_icon')->label('Quote İkon')->image()->disk('public')->directory('about')->visibility('public'),

                        FileUpload::make('testimonial_author_img_1')->label('Yazar 1 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('testimonial_name_1')->label('Yazar 1 Adı')->maxLength(255),
                        TextInput::make('testimonial_role_1')->label('Yazar 1 Ünvanı')->maxLength(255),
                        Textarea::make('testimonial_text_1')->label('Yorum 1')->rows(3),

                        FileUpload::make('testimonial_author_img_2')->label('Yazar 2 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('testimonial_name_2')->label('Yazar 2 Adı')->maxLength(255),
                        TextInput::make('testimonial_role_2')->label('Yazar 2 Ünvanı')->maxLength(255),
                        Textarea::make('testimonial_text_2')->label('Yorum 2')->rows(3),

                        FileUpload::make('testimonial_author_img_3')->label('Yazar 3 Görseli')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('testimonial_name_3')->label('Yazar 3 Adı')->maxLength(255),
                        TextInput::make('testimonial_role_3')->label('Yazar 3 Ünvanı')->maxLength(255),
                        Textarea::make('testimonial_text_3')->label('Yorum 3')->rows(3),
                    ])
                    ->columns(1),

                ],
                'iletisim' => [
                Section::make('İletişim')
                    ->description('CTA iletişim bölümü')
                    ->icon('heroicon-o-phone')
                    ->visible(fn (): bool => $this->aktifBolum === 'iletisim')
                    ->headerActions([$this->visibilityAction('show_cta_section')])
                    ->schema([
                        TextInput::make('cta_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('cta_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('cta_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('cta_paragraph')->label('Açıklama')->rows(3),

                        FileUpload::make('cta_phone_icon')->label('Telefon İkon')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('cta_phone_label')->label('Telefon Label')->maxLength(255),
                        TextInput::make('cta_phone_text')->label('Telefon Metni')->maxLength(50),

                        FileUpload::make('cta_mail_icon')->label('Mail İkon')->image()->disk('public')->directory('about')->visibility('public'),
                        TextInput::make('cta_mail_label')->label('Mail Label')->maxLength(255),
                        TextInput::make('cta_mail_text')->label('Mail Metni')->maxLength(255),

                        FileUpload::make('cta_image')->label('CTA Görsel')->image()->disk('public')->directory('about')->visibility('public'),
                    ])
                    ->columns(1),

                ],
                'sss' => [
                Section::make('Sık Sorulan Sorular')
                    ->description('SSS listesi ve 4 soru-cevap')
                    ->icon('heroicon-o-question-mark-circle')
                    ->visible(fn (): bool => $this->aktifBolum === 'sss')
                    ->headerActions([$this->visibilityAction('show_faq_section')])
                    ->schema([
                        TextInput::make('faqs_heading')->label('Bölüm Başlığı')->maxLength(255),
                        TextInput::make('faqs_subheading_span')->label('Alt Başlık (Span)')->maxLength(255),
                        TextInput::make('faqs_subheading_rest')->label('Alt Başlık (Devamı)')->maxLength(255),
                        Textarea::make('faqs_paragraph')->label('Açıklama')->rows(3),

                        TextInput::make('faqs_list_item_1')->label('Liste 1')->maxLength(255),
                        TextInput::make('faqs_list_item_2')->label('Liste 2')->maxLength(255),
                        TextInput::make('faqs_list_item_3')->label('Liste 3')->maxLength(255),

                        TextInput::make('faq_q_1')->label('Soru 1')->maxLength(255),
                        Textarea::make('faq_a_1')->label('Cevap 1')->rows(3),
                        TextInput::make('faq_q_2')->label('Soru 2')->maxLength(255),
                        Textarea::make('faq_a_2')->label('Cevap 2')->rows(3),
                        TextInput::make('faq_q_3')->label('Soru 3')->maxLength(255),
                        Textarea::make('faq_a_3')->label('Cevap 3')->rows(3),
                        TextInput::make('faq_q_4')->label('Soru 4')->maxLength(255),
                        Textarea::make('faq_a_4')->label('Cevap 4')->rows(3),
                        FileUpload::make('faq_image')->label('Accordion Görsel')->image()->disk('public')->directory('about')->visibility('public'),
                    ])
                    ->columns(1),
                ],
                default => [],
            })
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    public function bolumler(): array
    {
        return self::BOLUMLER;
    }

    public function bolumSec(string $bolum): void
    {
        if (array_key_exists($bolum, self::BOLUMLER)) {
            $this->aktifBolum = $bolum;
            $this->form->fill($this->data);
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! Schema::hasTable('about_pages')) {
            Notification::make()
                ->title('about_pages tablosu bulunamadı.')
                ->danger()
                ->send();
            return;
        }

        $about = AboutPage::instance();
        $table = $about->getTable();
        $allowed = [];

        foreach ($data as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $allowed[$column] = $value;
            }
        }

        $about->update($allowed);

        Notification::make()
            ->title('Hakkımızda sayfası kaydedildi.')
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

    private function visibilityAction(string $field): FormAction
    {
        return FormAction::make($field)
            ->label(fn (): string => (($this->data[$field] ?? true) ? 'Aktif' : 'Pasif'))
            ->icon(fn (): string => (($this->data[$field] ?? true) ? 'heroicon-m-eye' : 'heroicon-m-eye-slash'))
            ->color(fn (): string => (($this->data[$field] ?? true) ? 'success' : 'gray'))
            ->action(function () use ($field): void {
                $current = (bool) ($this->data[$field] ?? true);
                $this->data[$field] = ! $current;
            });
    }

    private function ensureDefaultImages(): array
    {
        $map = [
            'about_img_1' => 'about-img-1.jpg',
            'about_circle_icon' => 'experience-circle.svg',
            'about_img_2' => 'about-img-2.jpg',
            'about_experience_image' => 'about-experience-image.jpg',
            'about_icon_experience' => 'icon-about-experience.svg',
            'about_icon_contact' => 'icon-about-contact.svg',
            'mission_vision_image' => 'mission-vision-img.jpg',
            'icon_mission' => 'icon-mission.svg',
            'icon_vision' => 'icon-vision.svg',
            'icon_goal' => 'icon-goal.svg',
            'why_choose_icon_1' => 'icon-why-choose-1.svg',
            'why_choose_icon_2' => 'icon-why-choose-2.svg',
            'why_choose_icon_3' => 'icon-why-choose-3.svg',
            'why_choose_icon_4' => 'icon-why-choose-4.svg',
            'why_choose_image' => 'why-choose-image.png',
            'commitment_image_1' => 'commitment-image-1.jpg',
            'satisfy_client_img_1' => 'satisfy-client-img-1.jpg',
            'satisfy_client_img_2' => 'satisfy-client-img-2.jpg',
            'satisfy_client_img_3' => 'satisfy-client-img-3.jpg',
            'satisfy_client_img_4' => 'satisfy-client-img-4.jpg',
            'satisfy_client_img_5' => 'satisfy-client-img-5.jpg',
            'commitment_image_2' => 'commitment-image-2.jpg',
            'expertise_icon_1' => 'icon-expertise-item-1.svg',
            'expertise_icon_2' => 'icon-expertise-item-2.svg',
            'expertise_image' => 'expertise-image.jpg',
            'expertise_phone_icon' => 'icon-phone.svg',
            'need_help_icon' => 'icon-need-help.svg',
            'what_we_counter_icon_1' => 'icon-what-we-counter-1.svg',
            'what_we_counter_icon_2' => 'icon-what-we-counter-2.svg',
            'what_we_image' => 'what-we-image.jpg',
            'team_img_1' => 'team-1.jpg',
            'team_img_2' => 'team-2.jpg',
            'team_img_3' => 'team-3.jpg',
            'team_img_4' => 'team-4.jpg',
            'support_image_1' => 'support-image-1.jpg',
            'support_image_2' => 'support-image-2.jpg',
            'support_circle_icon' => 'contact-now-circle-2.svg',
            'support_icon_1' => 'icon-support-item-1.svg',
            'support_icon_2' => 'icon-support-item-2.svg',
            'testimonial_quote_icon' => 'testimonial-quote.svg',
            'testimonial_author_img_1' => 'author-1.jpg',
            'testimonial_author_img_2' => 'author-2.jpg',
            'testimonial_author_img_3' => 'author-3.jpg',
            'cta_phone_icon' => 'icon-phone.svg',
            'cta_mail_icon' => 'icon-mail.svg',
            'cta_image' => 'cta-box-image.png',
            'faq_image' => 'faqs-accordion-img.jpg',
        ];

        $disk = Storage::disk('public');
        $defaults = [];

        foreach ($map as $field => $filename) {
            $target = 'about/' . $filename;

            if (! $disk->exists($target)) {
                $source = public_path('theme/yalovakamera/images/' . $filename);
                if (! is_file($source)) {
                    $source = base_path('public/theme/yalovakamera/images/' . $filename);
                }

                if (is_file($source)) {
                    $disk->makeDirectory('about');
                    $disk->put($target, File::get($source));
                }
            }

            if ($disk->exists($target)) {
                $defaults[$field] = $target;
            }
        }

        return $defaults;
    }
}
