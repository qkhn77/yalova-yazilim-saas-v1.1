<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulRakamlarlaBiz extends BaseModulSectionEditor
{
    protected static ?string $title = 'Rakamlarla Biz';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/rakamlarla-biz';

    protected static function getModuleKey(): string
    {
        return 'rakamlarla_biz';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'Özellikler',
            'sub_span' => 'Gelişmiş',
            'sub_text' => 'güvenlik sistemleri',
            'contact_circle' => '',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'contact_circle' => 'theme/yalovakamera/images/contact-now-circle.svg',
            'icon_1' => 'theme/yalovakamera/images/icon-feature-item-1.svg',
            'icon_2' => 'theme/yalovakamera/images/icon-feature-item-2.svg',
            'icon_3' => 'theme/yalovakamera/images/icon-feature-item-3.svg',
            'icon_4' => 'theme/yalovakamera/images/icon-feature-item-4.svg',
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
                    Forms\Components\FileUpload::make('contact_circle')->label('İletişim Çember Görseli')->image()->disk('public')->directory('moduller/rakamlarla-biz')->visibility('public'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Özellik 1')
                ->schema([
                    Forms\Components\FileUpload::make('icon_1')->label('İkon')->image()->disk('public')->directory('moduller/rakamlarla-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_1')->label('Başlık')->default('7/24 İzleme & Bildirim'),
                    Forms\Components\Textarea::make('text_1')->label('Metin')->rows(2)->default('Kesintisiz izleme ve hızlı bildirim altyapısı.'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Özellik 2')
                ->schema([
                    Forms\Components\FileUpload::make('icon_2')->label('İkon')->image()->disk('public')->directory('moduller/rakamlarla-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_2')->label('Başlık')->default('Yüksek Çözünürlük'),
                    Forms\Components\Textarea::make('text_2')->label('Metin')->rows(2)->default('Net görüntü ve güçlü gece görüş seçenekleri.'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Özellik 3')
                ->schema([
                    Forms\Components\FileUpload::make('icon_3')->label('İkon')->image()->disk('public')->directory('moduller/rakamlarla-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_3')->label('Başlık')->default('Genişletilebilir Yapı'),
                    Forms\Components\Textarea::make('text_3')->label('Metin')->rows(2)->default('İhtiyacınıza göre ek kamera ve cihaz desteği.'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Özellik 4')
                ->schema([
                    Forms\Components\FileUpload::make('icon_4')->label('İkon')->image()->disk('public')->directory('moduller/rakamlarla-biz')->visibility('public'),
                    Forms\Components\TextInput::make('title_4')->label('Başlık')->default('Darbeye Dayanım'),
                    Forms\Components\Textarea::make('text_4')->label('Metin')->rows(2)->default('Dış ortam koşullarına uygun sağlam tasarım.'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Sayaçlar')
                ->schema([
                    Forms\Components\TextInput::make('counter_1')->label('Sayaç 1 Değer')->default('220'),
                    Forms\Components\TextInput::make('counter_label_1')->label('Sayaç 1 Etiket')->default('Konut'),
                    Forms\Components\TextInput::make('counter_2')->label('Sayaç 2 Değer')->default('30'),
                    Forms\Components\TextInput::make('counter_label_2')->label('Sayaç 2 Etiket')->default('AVM & Bina'),
                    Forms\Components\TextInput::make('counter_3')->label('Sayaç 3 Değer')->default('100'),
                    Forms\Components\TextInput::make('counter_label_3')->label('Sayaç 3 Etiket')->default('Ticari Alan'),
                    Forms\Components\TextInput::make('counter_4')->label('Sayaç 4 Değer')->default('700'),
                    Forms\Components\TextInput::make('counter_label_4')->label('Sayaç 4 Etiket')->default('Proje'),
                    Forms\Components\TextInput::make('counter_5')->label('Sayaç 5 Değer')->default('10'),
                    Forms\Components\TextInput::make('counter_label_5')->label('Sayaç 5 Etiket')->default('Yıl Deneyim'),
                ])
                ->columns(1),
        ];
    }
}

