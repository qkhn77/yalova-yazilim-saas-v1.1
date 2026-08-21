<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\NewsletterMailTemplate;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class WebMailSablonlari extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Mail Sablonlari';

    protected static ?string $slug = 'web-ayarlar/mail-sablonlari';

    protected static string $view = 'filament.clusters.web.pages.web-mail-sablonlari';

    public function getTitle(): string|Htmlable
    {
        return 'Mail Sablonlari';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Mail Sablonlari';
    }

    public function getSubheading(): ?string
    {
        return 'Toplu gonderimlerde kullanabileceginiz hazir mail sablonlarini buradan yonetebilirsiniz.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(NewsletterMailTemplate::query()->select([
                'id',
                'title',
                'subject',
                'is_active',
                'sort_order',
                'updated_at',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Sablon adi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Konu')
                    ->limit(60)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sira')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Guncellenme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Sablon ekle')
                    ->modalHeading('Mail sablonu ekle')
                    ->icon('heroicon-o-plus')
                    ->modalWidth('7xl')
                    ->form($this->getTemplateFormSchema())
                    ->mutateFormDataUsing(fn (array $data): array => $this->normalizeTemplateData($data))
                    ->action(function (array $data): void {
                        NewsletterMailTemplate::create($data);

                        Notification::make()
                            ->title('Mail sablonu eklendi.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Duzenle')
                    ->modalWidth('7xl')
                    ->form($this->getTemplateFormSchema())
                    ->mutateFormDataUsing(fn (array $data): array => $this->normalizeTemplateData($data))
                    ->action(function (NewsletterMailTemplate $record, array $data): void {
                        $record->update($data);

                        Notification::make()
                            ->title('Mail sablonu guncellendi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil'),
            ]);
    }

    protected function getTemplateFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Mail Sablonu')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Sablon adi')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('subject')
                        ->label('Konu')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Radio::make('editor_mode')
                        ->label('Editor modu')
                        ->options([
                            'rich' => 'Gorsel editor',
                            'html' => 'HTML editor',
                        ])
                        ->default('rich')
                        ->dehydrated(false)
                        ->live(),
                    Forms\Components\Placeholder::make('template_editor_note')
                        ->label('Kullanim notu')
                        ->content('Gorsel editor hizli mail metinleri icin uygundur. HTML editor ile gelismis e-posta tasarimlarini kaynak kodu duzeyinde yonetebilirsiniz.'),
                    Forms\Components\RichEditor::make('content')
                        ->label('Icerik')
                        ->required()
                        ->visible(fn (Get $get): bool => $get('editor_mode') === 'rich')
                        ->toolbarButtons([
                            'attachFiles',
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('mail-templates')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('html_content')
                        ->label('HTML icerik')
                        ->rows(18)
                        ->visible(fn (Get $get): bool => $get('editor_mode') === 'html')
                        ->dehydrated(false)
                        ->afterStateHydrated(fn ($state, Set $set, ?NewsletterMailTemplate $record) => $set('html_content', $state ?: $record?->content))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sira')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ])
                ->columns(2),
        ];
    }

    protected function normalizeTemplateData(array $data): array
    {
        $mode = $data['editor_mode'] ?? 'rich';

        if ($mode === 'html') {
            $data['content'] = $data['html_content'] ?? $data['content'] ?? '';
        }

        unset($data['editor_mode'], $data['html_content']);

        return $data;
    }
}
