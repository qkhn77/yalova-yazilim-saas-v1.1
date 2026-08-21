<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulNelerYapiyoruz extends BaseModulSectionEditor
{
    protected static ?string $title = 'Neler Yapıyoruz';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/neler-yapiyoruz';

    protected static function getModuleKey(): string
    {
        return 'neler_yapiyoruz';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'Ne Yapıyoruz?',
            'sub_span' => 'Güvenilir',
            'sub_text' => 'izleme ve koruma',
            'text_1' => 'İleri teknoloji IP kameralar, NVR kayıt çözümleri ve alarm sistemleri ile ev ve işletmeler için uçtan uca hizmet veriyoruz.',
            'text_2' => 'Keşiften projelendirmeye, montajdan bakıma kadar tüm süreci yönetiyoruz.',
            'need_help_icon' => '',
            'need_help_text' => '7/24 Destek Hattı',
            'phone' => '0 (226) 352 07 24',
            'counter_icon_1' => '',
            'counter_value_1' => '550',
            'counter_label_1' => 'Kurulum / Proje',
            'counter_icon_2' => '',
            'counter_value_2' => '250',
            'counter_label_2' => 'Mutlu Müşteri',
            'main_image' => '',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'need_help_icon' => 'theme/yalovakamera/images/icon-need-help.svg',
            'counter_icon_1' => 'theme/yalovakamera/images/icon-what-we-counter-1.svg',
            'counter_icon_2' => 'theme/yalovakamera/images/icon-what-we-counter-2.svg',
            'main_image' => 'theme/yalovakamera/images/what-we-image.jpg',
        ];
    }

    protected function getEditorSchema(): array
    {
        return [
            Forms\Components\Section::make('Başlık ve Açıklama')
                ->schema([
                    Forms\Components\TextInput::make('heading')->label('Bölüm Başlığı')->required(),
                    Forms\Components\TextInput::make('sub_span')->label('Alt Başlık (Span)')->required(),
                    Forms\Components\TextInput::make('sub_text')->label('Alt Başlık (Devamı)')->required(),
                    Forms\Components\Textarea::make('text_1')->label('Paragraf 1')->rows(3)->required(),
                    Forms\Components\Textarea::make('text_2')->label('Paragraf 2')->rows(3)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Destek Alanı')
                ->schema([
                    Forms\Components\FileUpload::make('need_help_icon')->label('Yardım İkon')->image()->disk('public')->directory('moduller/neler-yapiyoruz')->visibility('public'),
                    Forms\Components\TextInput::make('need_help_text')->label('Yardım Metni')->required(),
                    Forms\Components\TextInput::make('phone')->label('Telefon')->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Sayaç 1')
                ->schema([
                    Forms\Components\FileUpload::make('counter_icon_1')->label('İkon')->image()->disk('public')->directory('moduller/neler-yapiyoruz')->visibility('public'),
                    Forms\Components\TextInput::make('counter_value_1')->label('Değer')->required(),
                    Forms\Components\TextInput::make('counter_label_1')->label('Etiket')->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Sayaç 2')
                ->schema([
                    Forms\Components\FileUpload::make('counter_icon_2')->label('İkon')->image()->disk('public')->directory('moduller/neler-yapiyoruz')->visibility('public'),
                    Forms\Components\TextInput::make('counter_value_2')->label('Değer')->required(),
                    Forms\Components\TextInput::make('counter_label_2')->label('Etiket')->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Ana Görsel')
                ->schema([
                    Forms\Components\FileUpload::make('main_image')->label('Görsel')->image()->disk('public')->directory('moduller/neler-yapiyoruz')->visibility('public'),
                ])
                ->columns(1),
        ];
    }
}

