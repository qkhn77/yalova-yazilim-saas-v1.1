<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulTeknikDestek extends BaseModulSectionEditor
{
    protected static ?string $title = 'Teknik Destek';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/teknik-destek';

    protected static function getModuleKey(): string
    {
        return 'teknik_destek';
    }

    protected function getDefaultData(): array
    {
        return [
            'image_1' => '',
            'image_2' => '',
            'circle_image' => '',
            'heading' => 'Teknik Destek',
            'sub_span' => 'Güvenilir',
            'sub_text' => 'destek',
            'text' => 'Kurulum sonrası destek, bakım ve arıza çözümleri.',
            'icon_1' => '',
            'title_1' => 'Kamera Kurulumu',
            'text_1' => 'Keşif + montaj + devreye alma.',
            'icon_2' => '',
            'title_2' => 'Güvenlik Çözümleri',
            'text_2' => 'Alarm, kayıt ve erişim sistemleri.',
            'button_text' => 'İletişime Geç',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'image_1' => 'theme/yalovakamera/images/support-image-1.jpg',
            'image_2' => 'theme/yalovakamera/images/support-image-2.jpg',
            'circle_image' => 'theme/yalovakamera/images/contact-now-circle-2.svg',
            'icon_1' => 'theme/yalovakamera/images/icon-support-item-1.svg',
            'icon_2' => 'theme/yalovakamera/images/icon-support-item-2.svg',
        ];
    }

    protected function getEditorSchema(): array
    {
        return [
            Forms\Components\Section::make('Görseller')
                ->schema([
                    Forms\Components\FileUpload::make('image_1')->label('Sol Görsel 1')->image()->disk('public')->directory('moduller/teknik-destek')->visibility('public'),
                    Forms\Components\FileUpload::make('image_2')->label('Sol Görsel 2')->image()->disk('public')->directory('moduller/teknik-destek')->visibility('public'),
                    Forms\Components\FileUpload::make('circle_image')->label('Çember Görsel')->image()->disk('public')->directory('moduller/teknik-destek')->visibility('public'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Metin Alanı')
                ->schema([
                    Forms\Components\TextInput::make('heading')->label('Bölüm Başlığı')->required(),
                    Forms\Components\TextInput::make('sub_span')->label('Alt Başlık (Span)')->required(),
                    Forms\Components\TextInput::make('sub_text')->label('Alt Başlık (Devamı)')->required(),
                    Forms\Components\Textarea::make('text')->label('Açıklama')->rows(3)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 1')
                ->schema([
                    Forms\Components\FileUpload::make('icon_1')->label('İkon')->image()->disk('public')->directory('moduller/teknik-destek')->visibility('public'),
                    Forms\Components\TextInput::make('title_1')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_1')->label('Metin')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 2')
                ->schema([
                    Forms\Components\FileUpload::make('icon_2')->label('İkon')->image()->disk('public')->directory('moduller/teknik-destek')->visibility('public'),
                    Forms\Components\TextInput::make('title_2')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_2')->label('Metin')->rows(2)->required(),
                    Forms\Components\TextInput::make('button_text')->label('Buton Metni')->required(),
                ])
                ->columns(1),
        ];
    }
}

