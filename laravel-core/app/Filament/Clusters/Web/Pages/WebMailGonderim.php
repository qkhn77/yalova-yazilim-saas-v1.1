<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\NewsletterMailTemplate;
use App\Models\NewsletterSubscriber;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Mail;

class WebMailGonderim extends Page implements HasForms
{
    use InteractsWithForms;
    use \Filament\Pages\Concerns\InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Mail Gonderim';

    protected static ?string $slug = 'web-ayarlar/mail-gonderim';

    protected static string $view = 'filament.clusters.web.pages.web-mail-gonderim';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'send_scope' => 'all_active',
            'recipient_ids' => [],
            'subscriber_source' => '',
            'subscriber_group' => '',
            'subscribed_from' => null,
            'subscribed_until' => null,
            'email_contains' => '',
            'template_id' => null,
            'editor_mode' => 'html',
            'test_recipient' => \App\Models\Setting::get('mail_recipient', \App\Models\Setting::get('mail_username')),
            'subject' => '',
            'content' => '',
            'html_content' => '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gonderim Ayarlari')
                    ->description('Buyuk abone listelerinde de hizli calismasi icin gonderim tipini, filtreleri ve hedef kitlenizi buradan belirleyin.')
                    ->icon('heroicon-o-envelope')
                    ->schema([
                        Forms\Components\Radio::make('send_scope')
                            ->label('Gonderim tipi')
                            ->options([
                                'all_active' => 'Tum aktif abonelere gonder',
                                'filtered' => 'Filtreye gore gonder',
                                'selected' => 'Sadece sectigim abonelere gonder',
                            ])
                            ->default('all_active')
                            ->live(),
                        Forms\Components\Grid::make(2)
                            ->visible(fn (Get $get): bool => $get('send_scope') === 'filtered')
                            ->schema([
                                Forms\Components\Select::make('subscriber_source')
                                    ->label('Kaynak filtresi')
                                    ->options(fn (): array => NewsletterSubscriber::query()
                                        ->select('source')
                                        ->whereNotNull('source')
                                        ->distinct()
                                        ->orderBy('source')
                                        ->pluck('source', 'source')
                                        ->all())
                                    ->placeholder('Tum kaynaklar'),
                                Forms\Components\Select::make('subscriber_group')
                                    ->label('Grup filtresi')
                                    ->options(fn (): array => NewsletterSubscriber::query()
                                        ->select('group_name')
                                        ->whereNotNull('group_name')
                                        ->where('group_name', '!=', '')
                                        ->distinct()
                                        ->orderBy('group_name')
                                        ->pluck('group_name', 'group_name')
                                        ->all())
                                    ->placeholder('Tum gruplar'),
                                Forms\Components\TextInput::make('email_contains')
                                    ->label('E-posta icinde gecen ifade')
                                    ->placeholder('Orn: gmail.com')
                                    ->helperText('Belirli bir domain veya kelimeye gore hedefleme yapabilirsiniz.'),
                                Forms\Components\DatePicker::make('subscribed_from')
                                    ->label('Abonelik baslangic tarihi'),
                                Forms\Components\DatePicker::make('subscribed_until')
                                    ->label('Abonelik bitis tarihi'),
                            ]),
                        Forms\Components\Select::make('recipient_ids')
                            ->label('Aboneler')
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Forms\Get $get): bool => $get('send_scope') === 'selected')
                            ->getSearchResultsUsing(fn (string $search): array => NewsletterSubscriber::query()
                                ->where('is_active', true)
                                ->where('email', 'like', "%{$search}%")
                                ->orderBy('email')
                                ->limit(50)
                                ->pluck('email', 'id')
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => NewsletterSubscriber::query()
                                ->whereIn('id', $values)
                                ->pluck('email', 'id')
                                ->all())
                            ->helperText('Arama yaparak aktif aboneler arasindan secim yapin. Buyuk listelerde performans icin sadece aranan kayitlar yuklenir.'),
                        Forms\Components\Placeholder::make('recipient_summary')
                            ->label('Hedef kitle ozeti')
                            ->content(function (Get $get): string {
                                $scope = $get('send_scope');

                                if ($scope === 'all_active') {
                                    return 'Tum aktif abonelere gonderim yapilacak.';
                                }

                                $count = $this->resolveRecipientQuery($get)->count();

                                return match ($scope) {
                                    'selected' => $count > 0
                                        ? "Secili filtreye gore {$count} abone hedefleniyor."
                                        : 'Henuz abone secilmedi.',
                                    'filtered' => "{$count} aktif abone filtreye uyuyor.",
                                    default => 'Tum aktif abonelere gonderim yapilacak.',
                                };
                            }),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Mail Icerigi')
                    ->description('Hazir bir sablon secip icerigi duzenleyebilir veya sifirdan yeni bir mail metni hazirlayabilirsiniz.')
                    ->schema([
                        Forms\Components\Select::make('template_id')
                            ->label('Mail sablonu')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => NewsletterMailTemplate::query()
                                ->where('is_active', true)
                                ->where('title', 'like', "%{$search}%")
                                ->orderBy('sort_order')
                                ->orderBy('title')
                                ->limit(50)
                                ->pluck('title', 'id')
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => NewsletterMailTemplate::query()
                                ->whereKey($value)
                                ->value('title'))
                            ->placeholder('Sablon secin')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                if (! $state) {
                                    return;
                                }

                                $template = NewsletterMailTemplate::query()->find($state);

                                if (! $template) {
                                    return;
                                }

                                $set('subject', $template->subject);
                                $set('content', $template->content);
                                $set('html_content', $template->content);
                            })
                            ->helperText('Sablon secildiginde konu ve icerik otomatik doldurulur.'),
                        Forms\Components\Radio::make('editor_mode')
                            ->label('Yazim modu')
                            ->options([
                                'html' => 'HTML editor',
                                'rich' => 'Gorsel editor',
                            ])
                            ->default('html')
                            ->live(),
                        Forms\Components\TextInput::make('subject')
                            ->label('Konu')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Orn: Yalova Kamera duyurusu'),
                        Forms\Components\TextInput::make('test_recipient')
                            ->label('Test mail adresi')
                            ->email()
                            ->placeholder('ornek@alanadi.com')
                            ->helperText('Toplu gonderimden once onizleme icin test mailini bu adrese gonderebilirsiniz.'),
                        Forms\Components\Placeholder::make('editor_help')
                            ->label('Editor notu')
                            ->content('Gorsel editor hizli ve pratik mail hazirlamak icin uygundur. HTML editor ise gelismis tasarim ve tam kaynak kontrolu saglar.'),
                        Forms\Components\RichEditor::make('content')
                            ->label('Icerik')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('editor_mode') === 'rich')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'underline',
                                'strike',
                                'codeBlock',
                                'redo',
                                'undo',
                                'link',
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('mail-gonderim')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('html_content')
                            ->label('HTML icerik')
                            ->rows(18)
                            ->visible(fn (Get $get): bool => $get('editor_mode') === 'html')
                            ->placeholder('<table>...</table>')
                            ->helperText('HTML modu secildiginde mail bu kaynak kod ile gonderilir.')
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('mail_preview')
                            ->label('Canli onizleme')
                            ->content(function (Get $get): HtmlString {
                                $subject = trim((string) $get('subject'));
                                $body = $this->resolveMailBody($get);
                                $subjectHtml = $subject !== '' ? e($subject) : 'Konu henuz girilmedi';
                                $bodyHtml = trim($body) !== '' ? $body : '<p style="color:#64748b;">Onizleme icin icerik girin.</p>';

                                return new HtmlString(
                                    '<div style="border:1px solid #e5e7eb;border-radius:16px;background:#fff;overflow:hidden;">'
                                    .'<div style="padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">'
                                    .'<strong style="display:block;color:#0f172a;">'.$subjectHtml.'</strong>'
                                    .'<span style="font-size:12px;color:#64748b;">Mail onizleme alani</span>'
                                    .'</div>'
                                    .'<div style="padding:20px;">'.$bodyHtml.'</div>'
                                    .'</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();

        if (($data['send_scope'] ?? 'all_active') === 'selected' && array_filter($data['recipient_ids'] ?? []) === []) {
            Notification::make()
                ->title('Abone secilmedi')
                ->body('Ozel gonderim icin en az bir abone secin.')
                ->danger()
                ->send();

            return;
        }

        $query = $this->resolveRecipientQuery($data);

        $recipients = $query
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            Notification::make()
                ->title('Gonderilecek abone bulunamadi')
                ->body('Seciminize uygun aktif abone bulunmuyor.')
                ->warning()
                ->send();

            return;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($recipients as $email) {
            try {
                Mail::html($this->resolveMailBody($data), function ($message) use ($email, $data): void {
                    $message->to($email)
                        ->subject((string) $data['subject']);
                });

                $successCount++;
            } catch (\Throwable) {
                $failedCount++;
            }
        }

        if ($successCount > 0) {
            Notification::make()
                ->title('Toplu mail gonderimi tamamlandi')
                ->body("Basarili: {$successCount} | Basarisiz: {$failedCount}")
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Mail gonderilemedi')
            ->body('Secilen abonelere mail gonderilemedi. Mail ayarlarinizi kontrol edin.')
            ->danger()
            ->send();
    }

    public function sendTestMail(): void
    {
        $data = $this->form->getState();
        $recipient = trim((string) ($data['test_recipient'] ?? ''));

        if ($recipient === '') {
            Notification::make()
                ->title('Test adresi gerekli')
                ->body('Lutfen test mail adresi girin.')
                ->danger()
                ->send();

            return;
        }

        if (trim((string) ($data['subject'] ?? '')) === '' || trim($this->resolveMailBody($data)) === '') {
            Notification::make()
                ->title('Mail icerigi eksik')
                ->body('Test gonderimi icin konu ve icerik alanlarini doldurun.')
                ->danger()
                ->send();

            return;
        }

        try {
            Mail::html($this->resolveMailBody($data), function ($message) use ($recipient, $data): void {
                $message->to($recipient)
                    ->subject('[TEST] '.(string) $data['subject']);
            });

            Notification::make()
                ->title('Test maili gonderildi')
                ->body($recipient.' adresine test maili gonderildi.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Test maili gonderilemedi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function resolveRecipientQuery(array|Get $data)
    {
        $scope = data_get($data, 'send_scope', 'all_active');
        $query = NewsletterSubscriber::query()->where('is_active', true);

        if ($scope === 'selected') {
            $recipientIds = array_filter((array) data_get($data, 'recipient_ids', []));

            if ($recipientIds !== []) {
                $query->whereIn('id', $recipientIds);
            } else {
                $query->whereRaw('1 = 0');
            }

            return $query;
        }

        if ($scope === 'filtered') {
            $source = trim((string) data_get($data, 'subscriber_source', ''));
            $group = trim((string) data_get($data, 'subscriber_group', ''));
            $emailContains = trim((string) data_get($data, 'email_contains', ''));
            $subscribedFrom = data_get($data, 'subscribed_from');
            $subscribedUntil = data_get($data, 'subscribed_until');

            if ($source !== '') {
                $query->where('source', $source);
            }

            if ($group !== '') {
                $query->where('group_name', $group);
            }

            if ($emailContains !== '') {
                $query->where('email', 'like', '%'.$emailContains.'%');
            }

            if ($subscribedFrom) {
                $query->whereDate('subscribed_at', '>=', $subscribedFrom);
            }

            if ($subscribedUntil) {
                $query->whereDate('subscribed_at', '<=', $subscribedUntil);
            }
        }

        return $query;
    }

    protected function resolveMailBody(array|Get $data): string
    {
        $mode = data_get($data, 'editor_mode', 'rich');

        if ($mode === 'html') {
            return (string) data_get($data, 'html_content', '');
        }

        return (string) data_get($data, 'content', '');
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('sendTestMail')
                ->label('Kendime Test Gonder')
                ->action('sendTestMail')
                ->color('gray'),
            Actions\Action::make('send')
                ->label('Mail Gonder')
                ->action('send')
                ->color('primary'),
        ];
    }
}
