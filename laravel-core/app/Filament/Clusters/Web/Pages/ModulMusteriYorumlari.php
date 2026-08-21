<?php

namespace App\Filament\Clusters\Web\Pages;

use Filament\Forms;

class ModulMusteriYorumlari extends BaseModulSectionEditor
{
    protected static ?string $title = 'Müşteri Yorumları';

    protected static ?string $slug = 'web-moduller/ucuncu-grup/musteri-yorumlari';

    protected static function getModuleKey(): string
    {
        return 'musteri_yorumlari';
    }

    protected function getDefaultData(): array
    {
        return [
            'heading' => 'Müşteri Yorumları',
            'sub_span' => 'Gerçek',
            'sub_text' => 'geri bildirimler',
            'quote_icon' => '',
            'author_image_1' => '',
            'name_1' => 'Sophia Reynolds',
            'role_1' => 'CEO',
            'comment_1' => 'Kurulum çok hızlı ve temiz yapıldı. Görüntü kalitesi harika.',
            'author_image_2' => '',
            'name_2' => 'Kathryn Murphy',
            'role_2' => 'Yönetici',
            'comment_2' => 'Teknik destek hızlı. Tavsiye ederim.',
            'author_image_3' => '',
            'name_3' => 'John Miller',
            'role_3' => 'IT Manager',
            'comment_3' => 'Sistem stabil çalışıyor, uzaktan izleme sorunsuz.',
        ];
    }

    protected function getDefaultImageMap(): array
    {
        return [
            'quote_icon' => 'theme/yalovakamera/images/testimonial-quote.svg',
            'author_image_1' => 'theme/yalovakamera/images/author-1.jpg',
            'author_image_2' => 'theme/yalovakamera/images/author-2.jpg',
            'author_image_3' => 'theme/yalovakamera/images/author-3.jpg',
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
                    Forms\Components\FileUpload::make('quote_icon')->label('Alıntı İkonu')->image()->disk('public')->directory('moduller/musteri-yorumlari')->visibility('public'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Yorum 1')
                ->schema([
                    Forms\Components\FileUpload::make('author_image_1')->label('Yazar Görseli')->image()->disk('public')->directory('moduller/musteri-yorumlari')->visibility('public'),
                    Forms\Components\TextInput::make('name_1')->label('İsim')->required(),
                    Forms\Components\TextInput::make('role_1')->label('Ünvan')->required(),
                    Forms\Components\Textarea::make('comment_1')->label('Yorum')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Yorum 2')
                ->schema([
                    Forms\Components\FileUpload::make('author_image_2')->label('Yazar Görseli')->image()->disk('public')->directory('moduller/musteri-yorumlari')->visibility('public'),
                    Forms\Components\TextInput::make('name_2')->label('İsim')->required(),
                    Forms\Components\TextInput::make('role_2')->label('Ünvan')->required(),
                    Forms\Components\Textarea::make('comment_2')->label('Yorum')->rows(2)->required(),
                ])
                ->columns(1),

            Forms\Components\Section::make('Yorum 3')
                ->schema([
                    Forms\Components\FileUpload::make('author_image_3')->label('Yazar Görseli')->image()->disk('public')->directory('moduller/musteri-yorumlari')->visibility('public'),
                    Forms\Components\TextInput::make('name_3')->label('İsim')->required(),
                    Forms\Components\TextInput::make('role_3')->label('Ünvan')->required(),
                    Forms\Components\Textarea::make('comment_3')->label('Yorum')->rows(2)->required(),
                ])
                ->columns(1),
        ];
    }
}

