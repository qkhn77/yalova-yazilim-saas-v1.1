<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulFaqs extends BaseModulSectionEditor
{
    protected static ?string $title = 'SSS';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/faqs';

    protected static function getModuleKey(): string
    {
        return 'faqs';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'SSS',
            'sub_span' => 'Sıkça',
            'sub_text' => 'sorulan sorular',
            'text' => 'Kurulum, servis ve sistemler hakkında hızlı cevaplar.',
            'list_1' => 'Yüksek çözünürlüklü kayıt',
            'list_2' => 'Uzaktan canlı izleme',
            'list_3' => 'Geniş açı kapsama',
            'q_1' => 'Hangi kamera sistemlerini kuruyorsunuz?',
            'a_1' => 'IP kameralar, NVR sistemleri ve dış ortam çözümleri sunuyoruz.',
            'q_2' => 'Kamerayı uzaktan izleyebilir miyim?',
            'a_2' => 'Evet. Telefon uygulaması ile canlı izleme ve kayıt erişimi sağlanır.',
            'q_3' => 'Kurulum hizmeti veriyor musunuz?',
            'a_3' => 'Evet. Keşif, montaj ve devreye alma dahil anahtar teslim kurulum yapıyoruz.',
            'q_4' => 'Kameralar dış ortam için uygun mu?',
            'a_4' => 'Evet. IP66/67 gibi koruma sınıfına sahip dış ortam kameraları kuruyoruz.',
            'image' => '',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'image' => 'theme/yalovakamera/images/faqs-accordion-img.jpg',
        ];
    }

    protected function getEditorSchema(): array
    {
        return [
            Forms\Components\Section::make('Başlık ve Giriş')
                ->schema([
                    Forms\Components\TextInput::make('heading')->label('Bölüm Başlığı')->required(),
                    Forms\Components\TextInput::make('sub_span')->label('Alt Başlık (Span)')->required(),
                    Forms\Components\TextInput::make('sub_text')->label('Alt Başlık (Devamı)')->required(),
                    Forms\Components\Textarea::make('text')->label('Açıklama')->rows(3)->required(),
                    Forms\Components\TextInput::make('list_1')->label('Liste 1')->required(),
                    Forms\Components\TextInput::make('list_2')->label('Liste 2')->required(),
                    Forms\Components\TextInput::make('list_3')->label('Liste 3')->required(),
                    Forms\Components\FileUpload::make('image')->label('FAQ Görseli')->image()->disk('public')->directory('moduller/faqs')->visibility('public'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Soru 1')
                ->schema([
                    Forms\Components\TextInput::make('q_1')->label('Soru')->required(),
                    Forms\Components\Textarea::make('a_1')->label('Cevap')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Soru 2')
                ->schema([
                    Forms\Components\TextInput::make('q_2')->label('Soru')->required(),
                    Forms\Components\Textarea::make('a_2')->label('Cevap')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Soru 3')
                ->schema([
                    Forms\Components\TextInput::make('q_3')->label('Soru')->required(),
                    Forms\Components\Textarea::make('a_3')->label('Cevap')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Soru 4')
                ->schema([
                    Forms\Components\TextInput::make('q_4')->label('Soru')->required(),
                    Forms\Components\Textarea::make('a_4')->label('Cevap')->rows(2)->required(),
                ])
                ->columns(1),
        ];
    }
}

