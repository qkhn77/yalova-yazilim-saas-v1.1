<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulNedenBiz extends BaseModulSectionEditor
{
    protected static ?string $title = 'Neden Biz';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/neden-biz';

    protected static function getModuleKey(): string
    {
        return 'neden_biz';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'Neden Biz?',
            'sub_span' => 'Uzman ekip,',
            'sub_text' => 'güvenilir çözümler',
            'image' => '',
            'icon_1' => '',
            'title_1' => '7/24 Destek',
            'text_1' => 'Kurulum sonrası destek, bakım ve hızlı müdahale.',
            'icon_2' => '',
            'title_2' => 'İhtiyaca Özel',
            'text_2' => 'Mekâna uygun kamera ve alarm tasarımı.',
            'icon_3' => '',
            'title_3' => 'Uzaktan İzleme',
            'text_3' => 'Telefonla canlı izleme ve kayıt erişimi.',
            'icon_4' => '',
            'title_4' => 'Periyodik Bakım',
            'text_4' => 'Sistem performansını koruyan düzenli bakım.',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'image' => 'theme/yalovakamera/images/why-choose-image.png',
            'icon_1' => 'theme/yalovakamera/images/icon-why-choose-1.svg',
            'icon_2' => 'theme/yalovakamera/images/icon-why-choose-2.svg',
            'icon_3' => 'theme/yalovakamera/images/icon-why-choose-3.svg',
            'icon_4' => 'theme/yalovakamera/images/icon-why-choose-4.svg',
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
                    Forms\Components\FileUpload::make('image')->label('Orta Görsel')->image()->disk('public')->directory('moduller/neden-biz')->visibility('public'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 1')
                ->schema([
                    Forms\Components\FileUpload::make('icon_1')->label('İkon')->image()->disk('public')->directory('moduller/neden-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_1')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_1')->label('Metin')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 2')
                ->schema([
                    Forms\Components\FileUpload::make('icon_2')->label('İkon')->image()->disk('public')->directory('moduller/neden-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_2')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_2')->label('Metin')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 3')
                ->schema([
                    Forms\Components\FileUpload::make('icon_3')->label('İkon')->image()->disk('public')->directory('moduller/neden-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_3')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_3')->label('Metin')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Öğe 4')
                ->schema([
                    Forms\Components\FileUpload::make('icon_4')->label('İkon')->image()->disk('public')->directory('moduller/neden-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_4')->label('Başlık')->required(),
                    Forms\Components\Textarea::make('text_4')->label('Metin')->rows(2)->required(),
                ])
                ->columns(1),
        ];
    }
}

