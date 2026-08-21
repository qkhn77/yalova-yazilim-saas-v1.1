<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulReferanslar extends BaseModulSectionEditor
{
    protected static ?string $title = 'Referanslar';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/referanslar';

    protected static function getModuleKey(): string
    {
        return 'referanslar';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'Projeler',
            'sub_span' => 'Örnek',
            'sub_text' => 'uygulamalar',
            'item_image_1' => '',
            'item_category_1' => 'Konut',
            'item_title_1' => 'Konut Kamera Kurulumu',
            'item_url_1' => '',
            'item_image_2' => '',
            'item_category_2' => 'Ticari',
            'item_title_2' => 'İşletme Güvenlik Çözümü',
            'item_url_2' => '',
            'item_image_3' => '',
            'item_category_3' => 'Endüstriyel',
            'item_title_3' => 'Fabrika / Depo İzleme',
            'item_url_3' => '',
            'item_image_4' => '',
            'item_category_4' => 'Mağaza',
            'item_title_4' => 'Mağaza Kamera Sistemi',
            'item_url_4' => '',
            'item_image_5' => '',
            'item_category_5' => 'Konut',
            'item_title_5' => 'Site / Apartman Çözümü',
            'item_url_5' => '',
            'item_image_6' => '',
            'item_category_6' => 'Ofis',
            'item_title_6' => 'Ofis Güvenliği',
            'item_url_6' => '',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'item_image_1' => 'theme/yalovakamera/images/project-1.jpg',
            'item_image_2' => 'theme/yalovakamera/images/project-2.jpg',
            'item_image_3' => 'theme/yalovakamera/images/project-3.jpg',
            'item_image_4' => 'theme/yalovakamera/images/project-4.jpg',
            'item_image_5' => 'theme/yalovakamera/images/project-5.jpg',
            'item_image_6' => 'theme/yalovakamera/images/project-6.jpg',
        ];
    }

    protected function getEditorSchema(): array
    {
        return [
            Forms\Components\Section::make('Başlık Alanı')
                ->schema([
                    Forms\Components\TextInput::make('heading')->label('Bölüm Başlığı')->required(),
                    Forms\Components\TextInput::make('sub_span')->label('Alt Başlık (Span)')->required(),
                    Forms\Components\TextInput::make('sub_text')->label('Alt Başlık (Devamı)')->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 1')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_1')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_1')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_1')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_1')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 2')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_2')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_2')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_2')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_2')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 3')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_3')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_3')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_3')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_3')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 4')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_4')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_4')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_4')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_4')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 5')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_5')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_5')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_5')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_5')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Kart 6')
                ->schema([
                    Forms\Components\FileUpload::make('item_image_6')->label('Görsel')->image()->disk('public')->directory('moduller/referanslar')->visibility('public'),
                    Forms\Components\TextInput::make('item_category_6')->label('Etiket')->required(),
                    Forms\Components\TextInput::make('item_title_6')->label('Başlık')->required(),
                    Forms\Components\TextInput::make('item_url_6')->label('Link (opsiyonel)')->url(),
                ])
                ->columns(1),
        ];
    }
}

