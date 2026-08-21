<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\NewsletterSubscriber;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class WebAboneler extends Page implements HasTable
{
    use InteractsWithTable;

    private const SOURCE_CACHE_KEY = 'web:aboneler:source-secenekleri';

    private const GRUP_CACHE_KEY = 'web:aboneler:grup-secenekleri';

    private const SECENEK_CACHE_TTL = 300;

    /** @var array<string, string>|null */
    private ?array $sourceSecenekleri = null;

    /** @var array<string, string>|null */
    private ?array $grupSecenekleri = null;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Aboneler';

    protected static ?string $slug = 'web-ayarlar/aboneler';

    protected static string $view = 'filament.clusters.web.pages.web-aboneler';

    public function getTitle(): string|Htmlable
    {
        return 'Aboneler';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Aboneler';
    }

    public function getSubheading(): ?string
    {
        return 'Footer abonelik formundan kayit olan e-posta adreslerini buradan yonetebilirsiniz.';
    }

    public function table(?Table $table = null): Table
    {
        if ($table === null) {
            return $this->getTable();
        }

        return $table
            ->query(NewsletterSubscriber::query())
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->select([
                'id',
                'email',
                'is_active',
                'source',
                'group_name',
                'subscribed_at',
                'unsubscribed_at',
                'ip_address',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('E-posta')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Durum')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Kaynak')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('group_name')
                    ->label('Grup')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Abonelik Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('unsubscribed_at')
                    ->label('Pasif Tarihi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all'])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktiflik'),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Kaynak')
                    ->options(fn (): array => $this->sourceSecenekleri()),
                Tables\Filters\SelectFilter::make('group_name')
                    ->label('Grup')
                    ->options(fn (): array => $this->grupSecenekleri()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importSubscribers')
                    ->label('Ice Aktar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->form([
                        Forms\Components\FileUpload::make('import_file')
                            ->label('Excel veya CSV dosyasi')
                            ->disk('local')
                            ->directory('imports/newsletter-subscribers')
                            ->acceptedFileTypes([
                                'text/csv',
                                'text/plain',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.oasis.opendocument.spreadsheet',
                            ])
                            ->helperText('En saglikli sonuc icin aktarma taslagi ile indirilen .xlsx dosyasini kullanin. Zorunlu alan sadece e-posta kolonudur.')
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->importSubscribers($data)),
                Tables\Actions\Action::make('downloadImportTemplate')
                    ->label('Aktarma Taslagi')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->url(fn (): string => route('newsletter-subscribers.template')),
                Tables\Actions\Action::make('exportCsv')
                    ->label('CSV Disa Aktar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (): string => route('newsletter-subscribers.export-csv')),
                Tables\Actions\Action::make('exportExcel')
                    ->label('Excel ile Aktar')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->url(fn (): string => route('newsletter-subscribers.export-excel')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Duzenle')
                    ->modalHeading('Aboneyi duzenle')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('E-posta')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('source')
                            ->label('Kaynak')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('group_name')
                            ->label('Grup')
                            ->maxLength(100),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif'),
                    ])
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['is_active'] = (bool) ($data['is_active'] ?? false);

                        return $data;
                    })
                    ->using(function (NewsletterSubscriber $record, array $data): NewsletterSubscriber {
                        $isActive = (bool) ($data['is_active'] ?? false);

                        $record->update([
                            'email' => $data['email'],
                            'source' => $data['source'] !== '' ? $data['source'] : null,
                            'group_name' => $data['group_name'] !== '' ? $data['group_name'] : null,
                            'is_active' => $isActive,
                            'subscribed_at' => $record->subscribed_at ?? now(),
                            'unsubscribed_at' => $isActive ? null : ($record->unsubscribed_at ?? now()),
                        ]);
                        $this->secenekCacheTemizle();

                        return $record;
                    }),
                Tables\Actions\Action::make('activate')
                    ->label('Aktif yap')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (NewsletterSubscriber $record): bool => ! $record->is_active)
                    ->action(function (NewsletterSubscriber $record): void {
                        $record->update([
                            'is_active' => true,
                            'subscribed_at' => now(),
                            'unsubscribed_at' => null,
                        ]);

                        Notification::make()
                            ->title('Abone aktif yapildi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('deactivate')
                    ->label('Pasif yap')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->visible(fn (NewsletterSubscriber $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (NewsletterSubscriber $record): void {
                        $record->update([
                            'is_active' => false,
                            'unsubscribed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Abone pasif yapildi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->modalHeading('Aboneyi sil')
                    ->after(fn (): null => $this->secenekCacheTemizle())
                    ->successNotificationTitle('Abone silindi.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulkActivate')
                        ->label('Secilenleri aktif yap')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update([
                            'is_active' => true,
                            'subscribed_at' => now(),
                            'unsubscribed_at' => null,
                        ]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Secilen aboneler aktif yapildi.'),
                    Tables\Actions\BulkAction::make('bulkDeactivate')
                        ->label('Secilenleri pasif yap')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update([
                            'is_active' => false,
                            'unsubscribed_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Secilen aboneler pasif yapildi.'),
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Secilenleri sil')
                        ->modalHeading('Secilen aboneleri sil')
                        ->after(fn (): null => $this->secenekCacheTemizle())
                        ->successNotificationTitle('Secilen aboneler silindi.'),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function sourceSecenekleri(): array
    {
        return $this->sourceSecenekleri ??= Cache::remember(
            self::SOURCE_CACHE_KEY,
            self::SECENEK_CACHE_TTL,
            fn (): array => NewsletterSubscriber::query()
                ->select('source')
                ->whereNotNull('source')
                ->distinct()
                ->orderBy('source')
                ->pluck('source', 'source')
                ->all()
        );
    }

    /**
     * @return array<string, string>
     */
    private function grupSecenekleri(): array
    {
        return $this->grupSecenekleri ??= Cache::remember(
            self::GRUP_CACHE_KEY,
            self::SECENEK_CACHE_TTL,
            fn (): array => NewsletterSubscriber::query()
                ->select('group_name')
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->distinct()
                ->orderBy('group_name')
                ->pluck('group_name', 'group_name')
                ->all()
        );
    }

    private function secenekCacheTemizle(): null
    {
        $this->sourceSecenekleri = null;
        $this->grupSecenekleri = null;

        Cache::forget(self::SOURCE_CACHE_KEY);
        Cache::forget(self::GRUP_CACHE_KEY);

        return null;
    }

    protected function importSubscribers(array $data): void
    {
        $uploadedPath = $data['import_file'] ?? null;

        if (! is_string($uploadedPath) || $uploadedPath === '') {
            Notification::make()
                ->title('Dosya bulunamadi')
                ->body('Lutfen ice aktarmak icin bir dosya secin.')
                ->danger()
                ->send();

            return;
        }

        $fullPath = Storage::disk('local')->path($uploadedPath);

        try {
            [$createdCount, $updatedCount, $skippedCount] = $this->processImportFile($fullPath);
            $this->secenekCacheTemizle();

            Notification::make()
                ->title('Abone listesi ice aktarıldi')
                ->body("Yeni: {$createdCount} | Guncellenen: {$updatedCount} | Atlanan: {$skippedCount}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Ice aktarma basarisiz')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            Storage::disk('local')->delete($uploadedPath);
        }
    }

    protected function processImportFile(string $path): array
    {
        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $headerMap = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $values = array_map(
                        fn ($cell): string => trim((string) $cell->getValue()),
                        $row->getCells()
                    );

                    if ($headerMap === null) {
                        $headerMap = $this->resolveImportHeaderMap($values);
                        continue;
                    }

                    $result = $this->importSubscriberRow($values, $headerMap);

                    if ($result === 'created') {
                        $createdCount++;
                    } elseif ($result === 'updated') {
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return [$createdCount, $updatedCount, $skippedCount];
    }

    protected function resolveImportHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeImportHeader($header);

            if (in_array($normalized, ['email', 'eposta'], true)) {
                $map['email'] = $index;
            }

            if (in_array($normalized, ['kaynak', 'source'], true)) {
                $map['source'] = $index;
            }

            if (in_array($normalized, ['grup', 'group'], true)) {
                $map['group_name'] = $index;
            }

            if (in_array($normalized, ['durum', 'status'], true)) {
                $map['is_active'] = $index;
            }
        }

        if (! array_key_exists('email', $map)) {
            throw new \RuntimeException('Dosyada zorunlu E-posta kolonu bulunamadi. Aktarma taslagini kullanarak tekrar deneyin.');
        }

        return $map;
    }

    protected function importSubscriberRow(array $values, array $headerMap): string
    {
        $email = trim((string) ($values[$headerMap['email']] ?? ''));

        if ($email === '') {
            return 'skipped';
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'skipped';
        }

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();
        $isNew = $subscriber === null;
        $source = trim((string) ($values[$headerMap['source'] ?? -1] ?? ''));
        $groupName = trim((string) ($values[$headerMap['group_name'] ?? -1] ?? ''));
        $isActive = $this->parseImportedStatus($values[$headerMap['is_active'] ?? -1] ?? null);

        if ($subscriber === null) {
            NewsletterSubscriber::create([
                'email' => $email,
                'source' => $source !== '' ? $source : 'excel-ice-aktarim',
                'group_name' => $groupName !== '' ? $groupName : null,
                'is_active' => $isActive,
                'subscribed_at' => now(),
                'unsubscribed_at' => $isActive ? null : now(),
            ]);

            return 'created';
        }

        $updates = [
            'is_active' => $isActive,
            'unsubscribed_at' => $isActive ? null : now(),
        ];

        if ($subscriber->subscribed_at === null) {
            $updates['subscribed_at'] = now();
        }

        if ($source !== '') {
            $updates['source'] = $source;
        }

        if ($groupName !== '') {
            $updates['group_name'] = $groupName;
        }

        $subscriber->update($updates);

        return $isNew ? 'created' : 'updated';
    }

    protected function normalizeImportHeader(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'İ' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    protected function parseImportedStatus(mixed $value): bool
    {
        $normalized = $this->normalizeImportHeader((string) $value);

        if ($normalized === '') {
            return true;
        }

        if (in_array($normalized, ['pasif', 'inactive', 'false', '0', 'hayir', 'no'], true)) {
            return false;
        }

        return true;
    }
}
