<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DenetimKayidiKaynagi\Pages;
use App\Models\DenetimKayidi;
use App\Models\User;
use App\Services\AuditOlaySunumServisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DenetimKayidiKaynagi extends Resource
{
    private const KRITIK_OLAYLAR = [
        'cari.para_birimi_degisim_engellendi',
        'siparis.manuel_odeme_onayi',
        'reconcile.tutarsizlik_bulundu',
        'reconcile.fix_basladi',
        'reconcile.fix_basarili',
        'reconcile.fix_hata',
    ];

    private static ?AuditOlaySunumServisi $auditOlaySunumServisi = null;

    /** @var array<string, string>|null */
    private static ?array $konuTipiSecenekleri = null;

    protected static ?string $model = DenetimKayidi::class;

    protected static ?string $slug = 'sistem-denetim-kayitlari';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return 'Denetim kayıtları';
    }

    public static function getModelLabel(): string
    {
        return 'Denetim kaydı';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Denetim kayıtları';
    }

    protected static function sadeceSistemYoneticisi(): bool
    {
        $kullanici = Auth::user();

        return $kullanici instanceof User
            && ((bool) ($kullanici->super_admin_mi ?? false) || (bool) ($kullanici->is_admin ?? false));
    }

    public static function canAccess(): bool
    {
        return static::sadeceSistemYoneticisi() && SaaSemaYardimcisi::tabloVarMi('denetim_kayitlari');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $kayit): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $kayit): bool
    {
        return false;
    }

    public static function canDelete(Model $kayit): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'firma_id',
                'kullanici_id',
                'olay',
                'konu_tipi',
                'konu_id',
                'ip_adresi',
                'created_at',
            ])
            ->with([
                'firma:id,ad',
                'kullanici:id,name,email',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('olay')
                    ->label('Olay')
                    ->formatStateUsing(fn (string $state): string => app(AuditOlaySunumServisi::class)->etiket($state))
                    ->badge()
                    ->color(fn (DenetimKayidi $record): string => app(AuditOlaySunumServisi::class)->kritikMi((string) $record->olay) ? 'warning' : 'gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('konu_tipi')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('konu_id')
                    ->label('Konu ID')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('firma.ad')
                    ->label('Firma')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kullanici.email')
                    ->label('Kullanıcı')
                    ->formatStateUsing(function (DenetimKayidi $record): string {
                        $ad = (string) ($record->kullanici?->name ?? '');
                        $email = (string) ($record->kullanici?->email ?? '');
                        if ($ad !== '' && $email !== '') {
                            return $ad.' ('.$email.')';
                        }

                        return $email !== '' ? $email : 'Sistem';
                    })
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_adresi')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ozet')
                    ->label('Özet')
                    ->getStateUsing(fn (DenetimKayidi $record): string => static::kayitOzetMetni($record))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 20, 50, 100, 1000, 'all'])
            ->filters([
                Tables\Filters\Filter::make('olay')
                    ->label('Olay')
                    ->form([
                        Forms\Components\TextInput::make('deger')->label('Olay içerir'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $d = $data['deger'] ?? null;
                        if (! filled($d)) {
                            return $query;
                        }

                        return $query->where('olay', 'like', '%'.$d.'%');
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->label('Tarih aralığı')
                    ->form([
                        Forms\Components\DatePicker::make('baslangic')->label('Başlangıç'),
                        Forms\Components\DatePicker::make('bitis')->label('Bitiş'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['baslangic'] ?? null, fn (Builder $q, $t) => $q->where('created_at', '>=', $t.' 00:00:00'))
                            ->when($data['bitis'] ?? null, fn (Builder $q, $t) => $q->where('created_at', '<=', $t.' 23:59:59'));
                    }),
                Tables\Filters\SelectFilter::make('firma_id')
                    ->label('Firma')
                    ->relationship('firma', 'ad')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('kullanici_id')
                    ->label('Kullanıcı')
                    ->relationship('kullanici', 'email')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('konu_tipi')
                    ->label('Model tipi')
                    ->options(fn (): array => static::konuTipiSecenekleri()),
                Tables\Filters\TernaryFilter::make('kritik')
                    ->label('Kritik')
                    ->placeholder('Tümü')
                    ->trueLabel('Sadece kritik')
                    ->falseLabel('Kritik olmayan')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('olay', static::KRITIK_OLAYLAR),
                        false: fn (Builder $query) => $query->whereNotIn('olay', static::KRITIK_OLAYLAR),
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    private static function auditOlaySunumServisi(): AuditOlaySunumServisi
    {
        return static::$auditOlaySunumServisi ??= app(AuditOlaySunumServisi::class);
    }

    /**
     * @return array<string, string>
     */
    private static function konuTipiSecenekleri(): array
    {
        return static::$konuTipiSecenekleri ??= Cache::remember(
            'denetim:kayitlari:konu-tipi-secenekleri:v1',
            now()->addMinutes(5),
            fn (): array => DenetimKayidi::query()
                ->whereNotNull('konu_tipi')
                ->select('konu_tipi')
                ->distinct()
                ->orderBy('konu_tipi')
                ->pluck('konu_tipi', 'konu_tipi')
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private static function ozetMetni(?array $state): string
    {
        if (! is_array($state) || $state === []) {
            return '—';
        }

        $degisiklikler = $state['degisiklikler'] ?? null;
        if (is_array($degisiklikler) && $degisiklikler !== []) {
            $parcalar = [];
            foreach ($degisiklikler as $alan => $deger) {
                $parcalar[] = $alan.': '.(is_scalar($deger) || $deger === null ? (string) $deger : '[...]');
                if (count($parcalar) >= 4) {
                    break;
                }
            }

            return implode(', ', $parcalar);
        }

        return Str::limit((string) json_encode($state, JSON_UNESCAPED_UNICODE), 160);
    }

    private static function kayitOzetMetni(DenetimKayidi $record): string
    {
        $attributes = $record->getAttributes();
        $kayit = (array_key_exists('eski_veri', $attributes) || array_key_exists('yeni_veri', $attributes))
            ? $record
            : DenetimKayidi::query()
                ->select(['id', 'eski_veri', 'yeni_veri'])
                ->find($record->getKey());

        if (! $kayit instanceof DenetimKayidi) {
            return '—';
        }

        $eski = static::ozetMetni($kayit->eski_veri);
        $yeni = static::ozetMetni($kayit->yeni_veri);
        if ($eski !== '—' && $yeni !== '—') {
            return 'Eski: '.$eski.' | Yeni: '.$yeni;
        }

        if ($yeni !== '—') {
            return $yeni;
        }

        return $eski;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\DenetimKayitlariListesi::route('/'),
        ];
    }
}
