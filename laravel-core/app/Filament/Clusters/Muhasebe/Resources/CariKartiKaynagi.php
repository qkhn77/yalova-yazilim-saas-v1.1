<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe\Resources\CariKartiKaynagi\Pages;
use App\Models\Firma;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\CariGrubu;
use App\Models\Muhasebe\ParaBirimi;
use App\Muhasebe\Enumlar\CariHareketDurumu;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Filament\AbstractKaynaklar\CariKaynagi;
use App\Muhasebe\Servisler\CariBakiyeServisi;
use App\Services\EBelgeHazirlikKontrolServisi;
use App\Services\TenantContextService;
use App\Support\KullaniciRolYardimcisi;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class CariKartiKaynagi extends CariKaynagi
{
    protected static ?string $slug = 'cari-yonetimi/cariler';

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Cari kartı';

    protected static ?string $pluralModelLabel = 'Cariler';

    protected static ?string $recordTitleAttribute = 'ad';

    /** @var array<string, string> */
    private static array $bakiyeOzetEtiketiCache = [];

    public static function bakiyeOzetEtiketi(Cari $cari): string
    {
        $anahtar = ((int) $cari->firma_id).':'.((int) $cari->getKey());

        if (isset(self::$bakiyeOzetEtiketiCache[$anahtar])) {
            return self::$bakiyeOzetEtiketiCache[$anahtar];
        }

        $anaPara = strtoupper((string) ($cari->para_birimi ?: 'TRY'));
        $etiketler = app(CariBakiyeServisi::class)
            ->paraBirimiOzetleri((int) $cari->firma_id, (int) $cari->getKey())
            ->map(function (object $satir): string {
                $etiket = strtoupper((string) $satir->para_birimi).' '.number_format(abs((float) $satir->bakiye), 2, ',', '.');
                $yon = self::bakiyeYonEtiketi((string) $satir->bakiye);

                return $yon !== '' ? $etiket.' ('.$yon.')' : $etiket;
            })
            ->values();

        if (! $etiketler->contains(fn (string $etiket): bool => str_starts_with($etiket, $anaPara.' '))) {
            $etiketler->prepend($anaPara.' '.number_format(0, 2, ',', '.'));
        }

        return self::$bakiyeOzetEtiketiCache[$anahtar] = $etiketler->implode(' · ');
    }

    public static function bakiyeYonEtiketi(string|float $tutar): string
    {
        return bccomp((string) $tutar, '0', 2) > 0
            ? 'Borç'
            : (bccomp((string) $tutar, '0', 2) < 0 ? 'Alacak' : '');
    }

    public static function bakiyeOzetHtml(Cari $cari): HtmlString
    {
        $anahtar = ((int) $cari->firma_id).':'.((int) $cari->getKey());
        $satirlar = app(CariBakiyeServisi::class)
            ->paraBirimiOzetleri((int) $cari->firma_id, (int) $cari->getKey());

        $html = $satirlar->map(function (object $satir): string {
            $tutar = (float) $satir->bakiye;
            $yon = self::bakiyeYonEtiketi((string) $satir->bakiye);
            $renk = $yon === 'Borç' ? 'color:#dc2626' : ($yon === 'Alacak' ? 'color:#16a34a' : 'color:#6b7280');
            $etiket = e(strtoupper((string) $satir->para_birimi).' '.number_format(abs($tutar), 2, ',', '.'));

            return '<span style="'.$renk.'">'.$etiket.($yon !== '' ? ' ('.e($yon).')' : '').'</span>';
        })->implode(' <span class="text-gray-300">·</span> ');

        if ($satirlar->isEmpty()) {
            $html = e(strtoupper((string) ($cari->para_birimi ?: 'TRY')).' 0,00');
        }

        return new HtmlString($html);
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    public static function kodBenzersizMi(int $firmaId, string $kod, ?int $haricId = null): bool
    {
        $sorgu = Cari::query()->where('firma_id', $firmaId)->where('kod', $kod);
        if ($haricId !== null) {
            $sorgu->whereKeyNot($haricId);
        }

        return ! $sorgu->exists();
    }

    public static function telefonNormalize(?string $telefon): string
    {
        return preg_replace('/\D+/', '', (string) $telefon) ?: '';
    }

    public static function telefonBenzersizMi(int $firmaId, ?string $telefon, ?int $haricId = null): bool
    {
        $normalize = self::telefonNormalize($telefon);
        if ($firmaId < 1 || $normalize === '') {
            return true;
        }

        $sorgu = Cari::withoutGlobalScopes()
            ->where('firma_id', $firmaId)
            ->whereNotNull('telefon')
            ->select(['id', 'telefon']);
        if ($haricId !== null) {
            $sorgu->whereKeyNot($haricId);
        }

        return ! $sorgu->get()->contains(
            fn (Cari $cari): bool => self::telefonNormalize($cari->telefon) === $normalize
        );
    }

    /**
     * @return array<string, string> ISO kod => etiket
     */
    public static function paraBirimiSecenekleriForFirma(int $firmaId): array
    {
        if ($firmaId < 1) {
            return [];
        }

        return ParaBirimi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get()
            ->mapWithKeys(function (ParaBirimi $kayit): array {
                $kod = strtoupper((string) $kayit->kod);
                $etiket = $kod.($kayit->ad ? ' — '.$kayit->ad : '');

                return [$kod => $etiket];
            })
            ->all();
    }

    public static function paraBirimiFirmaIcinGecerliMi(int $firmaId, string $kod): bool
    {
        $kod = strtoupper(trim($kod));
        if ($firmaId < 1 || strlen($kod) !== 3) {
            return false;
        }

        return ParaBirimi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->whereRaw('UPPER(kod) = ?', [$kod])
            ->exists();
    }

    public static function detayModu(): bool
    {
        $routeName = (string) (request()->route()?->getName() ?? '');
        if (str_ends_with($routeName, '.create') || str_ends_with($routeName, '.edit')) {
            return true;
        }

        // Filament/Livewire yeniden isteklerinde route adı livewire.update olur.
        // Referer veya mevcut path üzerinden create/edit ekranında kalındığını koru;
        // aksi halde repeater'a satır eklendiğinde form basit durum formuna düşer.
        $yollar = [
            (string) request()->path(),
            (string) parse_url((string) request()->header('referer'), PHP_URL_PATH),
        ];
        foreach ($yollar as $yol) {
            if (preg_match('#/cariler/(?:create|[0-9]+/edit)$#', $yol) === 1) {
                return true;
            }
        }

        return request()->boolean('detay');
    }

    public static function form(Form $form): Form
    {
        $superAdminMi = KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
        $detayModu = static::detayModu();

        if (! $detayModu) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options(collect(CariDurumu::cases())->mapWithKeys(fn (CariDurumu $d) => [$d->value => match ($d) {
                        CariDurumu::Aktif => 'Aktif',
                        CariDurumu::Pasif => 'Pasif',
                    }]))
                    ->required()
                    ->native()
                    ->default(CariDurumu::Aktif->value),
            ]);
        }

        return $form->schema([
            Forms\Components\Section::make('Temel bilgiler')
                ->schema([
                    Forms\Components\Select::make('firma_id')
                        ->label('Firma')
                        ->options(fn (): array => Firma::query()->orderBy('ad')->pluck('ad', 'id')->all())
                        ->searchable()
                        ->required($superAdminMi)
                        ->visible($superAdminMi)
                        ->live()
                        ->default(fn () => app(TenantContextService::class)->aktifFirmaId())
                        ->dehydrated(fn () => $superAdminMi)
                        ->afterStateUpdated(function (Set $set, $state) use ($superAdminMi): void {
                            if (! $superAdminMi) {
                                return;
                            }
                            $fid = (int) $state;
                            if ($fid < 1) {
                                $set('para_birimi', null);

                                return;
                            }
                            $secenekler = static::paraBirimiSecenekleriForFirma($fid);
                            if ($secenekler === []) {
                                $set('para_birimi', null);

                                return;
                            }
                            $set('para_birimi', array_key_exists('TRY', $secenekler) ? 'TRY' : array_key_first($secenekler));
                        })
                        ->helperText(fn () => $superAdminMi ? null : 'Firma oturumdaki aktif firmadan atanır.'),
                    Forms\Components\TextInput::make('ad')
                        ->label('Ünvan / ad')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('kisa_ad')
                        ->label('Kısa ad')
                        ->maxLength(128),
                    Forms\Components\Select::make('tur')
                        ->label('Cari türü')
                        ->options(collect(CariTuru::cases())->mapWithKeys(fn (CariTuru $e) => [$e->value => $e->etiket()]))
                        ->required()
                        ->default(CariTuru::Musteri->value),
                    Forms\Components\Select::make('durum')
                        ->label('Durum')
                        ->options(collect(CariDurumu::cases())->mapWithKeys(fn (CariDurumu $d) => [$d->value => match ($d) {
                            CariDurumu::Aktif => 'Aktif',
                            CariDurumu::Pasif => 'Pasif',
                        }]))
                        ->required()
                        ->default(CariDurumu::Aktif->value),
                    ...($detayModu ? [
                    Forms\Components\Select::make('cari_grubu_id')
                        ->label('Cari grubu')
                        ->options(function (Get $get) use ($superAdminMi): array {
                            $fid = $superAdminMi
                                ? (int) ($get('firma_id') ?: 0)
                                : (int) (app(TenantContextService::class)->aktifFirmaId() ?: 0);
                            if ($fid < 1) {
                                return [];
                            }

                            return CariGrubu::query()
                                ->gorunurFirmaIle($fid)
                                ->orderBy('ad')
                                ->pluck('ad', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->nullable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('kod')
                                ->label('Kod')
                                ->required()
                                ->maxLength(64),
                            Forms\Components\TextInput::make('ad')
                                ->label('Ad')
                                ->required()
                                ->maxLength(128),
                        ])
                        ->createOptionUsing(function (array $data, ComponentContainer $form) use ($superAdminMi): int {
                            $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: app(TenantContextService::class)->aktifFirmaId() ?? 0);
                            if (! $superAdminMi) {
                                $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                            }
                            if ($firmaId < 1) {
                                throw ValidationException::withMessages([
                                    'cari_grubu_id' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                ]);
                            }
                            $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                            $ad = trim((string) ($data['ad'] ?? ''));
                            if ($kod === '' || $ad === '') {
                                throw ValidationException::withMessages([
                                    'cari_grubu_id' => 'Kod ve ad zorunludur.',
                                ]);
                            }
                            $kapsam = $firmaId;
                            if (CariGrubu::tenantScopeOlmadan(fn () => CariGrubu::query()
                                ->where('tanim_firma_kapsami', $kapsam)
                                ->whereRaw('UPPER(kod) = ?', [$kod])
                                ->exists())) {
                                throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                            }
                            $kayit = CariGrubu::query()->create([
                                'firma_id' => $firmaId,
                                'kod' => $kod,
                                'ad' => $ad,
                                'aktif_mi' => true,
                                'is_sabit' => false,
                            ]);

                            return (int) $kayit->getKey();
                        }),
                    ] : []),
                ])->columns(['default' => 1, 'sm' => 2])->compact(),

            ...($detayModu ? [
            Forms\Components\Section::make('Vergi')
                ->schema([
                    Forms\Components\TextInput::make('vergi_dairesi')
                        ->label('Vergi dairesi')
                        ->maxLength(128),
                    Forms\Components\TextInput::make('vergi_no')
                        ->label('Vergi no')
                        ->maxLength(32),
                    Forms\Components\TextInput::make('tc_no')
                        ->label('T.C. kimlik no')
                        ->maxLength(11)
                        ->numeric(),
                    Forms\Components\Placeholder::make('cari_e_belge_uyarilari')
                        ->label('')
                        ->dehydrated(false)
                        ->content(fn (Get $get): HtmlString => static::cariEBelgeUyariHtml([
                            'ad' => $get('ad'),
                            'vergi_dairesi' => $get('vergi_dairesi'),
                            'vergi_no' => $get('vergi_no'),
                            'tc_no' => $get('tc_no'),
                            'email' => $get('email'),
                            'adres' => $get('adres'),
                            'il' => $get('il'),
                            'ilce' => $get('ilce'),
                        ]))
                        ->columnSpanFull(),
                ])->columns(['default' => 1, 'sm' => 2])->compact(),

            Forms\Components\Section::make('İletişim')
                ->schema([
                    Forms\Components\TextInput::make('telefon')
                        ->label('Telefon')
                        ->tel()
                        ->maxLength(64),
                    Forms\Components\TextInput::make('gsm')
                        ->label('2. Telefon')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('email')
                        ->label('E-posta')
                        ->email()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('website')
                        ->label('Web sitesi')
                        ->maxLength(255)
                        ->placeholder('www.ornek.com'),
                ])->columns(['default' => 1, 'sm' => 2])->compact(),

            Forms\Components\Section::make('Merkez adres')
                ->description('Mevcut cari adresi merkez adres olarak kabul edilir. Diğer adresleri aşağıdaki alandan ekleyebilirsiniz.')
                ->schema([
                    Forms\Components\Textarea::make('adres')
                        ->label('Adres')
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('ulke')
                        ->label('Ülke')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('il')
                        ->label('İl')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('ilce')
                        ->label('İlçe')
                        ->maxLength(64),
                    Forms\Components\TextInput::make('posta_kodu')
                        ->label('Posta kodu')
                        ->maxLength(16),
                    Forms\Components\Repeater::make('yetkiliKisiler')
                        ->label('Yetkili kişiler')
                        ->relationship('yetkiliKisiler')
                        ->schema([
                            Forms\Components\TextInput::make('ad_soyad')
                                ->label('Ad soyad')
                                ->required()
                                ->maxLength(191),
                            Forms\Components\TextInput::make('gorevi')
                                ->label('Görevi')
                                ->maxLength(128),
                            Forms\Components\TextInput::make('telefon')
                                ->label('Telefon')
                                ->tel()
                                ->maxLength(64),
                            Forms\Components\TextInput::make('email')
                                ->label('E-posta')
                                ->email()
                                ->maxLength(191),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());

                            return $data;
                        })
                        ->columns(['default' => 1, 'sm' => 2])
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => filled($state['ad_soyad'] ?? null) ? (string) $state['ad_soyad'] : 'Yeni yetkili kişi')
                        ->addActionLabel('Yetkili kişi ekle')
                        ->orderColumn('sira')
                        ->columnSpanFull()
                        ->helperText('Sınırsız kişi ekleyebilirsiniz. Her kişi ayrı kayıt olarak saklanır.'),
                ])->columns(['default' => 1, 'sm' => 2])->compact(),

            Forms\Components\Section::make('Diğer adresler')
                ->schema([
                    Forms\Components\Repeater::make('adresler')
                        ->label('Ek adresler')
                        ->relationship('adresler')
                        ->schema([
                            Forms\Components\TextInput::make('baslik')
                                ->label('Adres başlığı')
                                ->maxLength(128),
                            Forms\Components\TextInput::make('tur')
                                ->label('Adres türü')
                                ->maxLength(64)
                                ->placeholder('Merkez, Fatura, Sevkiyat, Şube')
                                ->helperText('Hazır örneklerden birini veya kendi türünüzü yazabilirsiniz.'),
                            Forms\Components\Textarea::make('adres')
                                ->label('Adres')
                                ->rows(2)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('ulke')
                                ->label('Ülke')
                                ->maxLength(64),
                            Forms\Components\TextInput::make('il')
                                ->label('İl')
                                ->maxLength(64),
                            Forms\Components\TextInput::make('ilce')
                                ->label('İlçe')
                                ->maxLength(64),
                            Forms\Components\TextInput::make('posta_kodu')
                                ->label('Posta kodu')
                                ->maxLength(16),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());

                            return $data;
                        })
                        ->columns(['default' => 1, 'sm' => 2])
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => filled($state['baslik'] ?? null) ? (string) $state['baslik'] : 'Yeni adres')
                        ->addActionLabel('Adres ekle')
                        ->orderColumn('sira')
                        ->columnSpanFull()
                        ->helperText('Yukarıdaki mevcut adres Merkez adres olarak kabul edilir. Buradan Fatura, Sevkiyat, Şube veya özel türde adres ekleyebilirsiniz.'),
                ])
                ->compact(),

            Forms\Components\Section::make('Finans ve koşullar')
                ->schema([
                    Forms\Components\TextInput::make('risk_limiti')
                        ->label('Risk limiti')
                        ->numeric()
                        ->default(0)
                        ->prefix('₺'),
                    Forms\Components\TextInput::make('vade_gunu')
                        ->label('Vade (gün)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\Select::make('para_birimi')
                        ->label('Para birimi')
                        ->options(function (Get $get) use ($superAdminMi): array {
                            $fid = $superAdminMi
                                ? (int) ($get('firma_id') ?: 0)
                                : (int) (app(TenantContextService::class)->aktifFirmaId() ?: 0);

                            return static::paraBirimiSecenekleriForFirma($fid);
                        })
                        ->default(function (Get $get) use ($superAdminMi): ?string {
                            $fid = $superAdminMi
                                ? (int) ($get('firma_id') ?: 0)
                                : (int) (app(TenantContextService::class)->aktifFirmaId() ?: 0);
                            $secenekler = static::paraBirimiSecenekleriForFirma($fid);
                            if (array_key_exists('TRY', $secenekler)) {
                                return 'TRY';
                            }

                            return $secenekler === [] ? null : array_key_first($secenekler);
                        })
                        ->required()
                        ->searchable()
                        ->disabled(fn (Get $get) => $superAdminMi && (int) ($get('firma_id') ?: 0) < 1)
                        ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state)
                        ->helperText(
                            fn (): HtmlString => new HtmlString(
                                'Tanımlar: <a href="'.e(ParaBirimiTanimKaynagi::getUrl()).'" target="_blank" rel="noopener" class="text-primary-600 underline">Para birimleri</a>'
                            )
                        ),
                    Forms\Components\Textarea::make('aciklama')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('bankaHesaplari')
                        ->label('Cari banka hesapları')
                        ->relationship('bankaHesaplari')
                        ->schema([
                            Forms\Components\TextInput::make('hesap_adi')
                                ->label('Hesap adı')
                                ->maxLength(191),
                            Forms\Components\TextInput::make('banka_adi')
                                ->label('Banka adı')
                                ->maxLength(191),
                            Forms\Components\TextInput::make('sube_adi')
                                ->label('Şube adı')
                                ->maxLength(191),
                            Forms\Components\TextInput::make('sube_kodu')
                                ->label('Şube kodu')
                                ->maxLength(64),
                            Forms\Components\TextInput::make('hesap_no')
                                ->label('Hesap no')
                                ->maxLength(128),
                            Forms\Components\Select::make('para_birimi')
                                ->label('Döviz')
                                ->options(fn (Get $get): array => static::paraBirimiSecenekleriForFirma((int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId())))
                                ->default(fn (Get $get): ?string => strtoupper((string) ($get('../../para_birimi') ?: 'TRY')))
                                ->searchable(),
                            Forms\Components\TextInput::make('iban')
                                ->label('IBAN')
                                ->maxLength(34),
                            Forms\Components\Toggle::make('varsayilan_mi')
                                ->label('Varsayılan hesap')
                                ->default(false),
                        ])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());
                            $data['para_birimi'] = strtoupper(trim((string) ($data['para_birimi'] ?? $get('../../para_birimi') ?? 'TRY')));

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                            $data['firma_id'] = (int) ($get('../../firma_id') ?: app(TenantContextService::class)->aktifFirmaId());
                            $data['para_birimi'] = strtoupper(trim((string) ($data['para_birimi'] ?? $get('../../para_birimi') ?? 'TRY')));

                            return $data;
                        })
                        ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => filled($state['hesap_adi'] ?? null) ? (string) $state['hesap_adi'] : 'Yeni banka hesabı')
                        ->addActionLabel('Banka hesabı ekle')
                        ->orderColumn('sira')
                        ->columnSpanFull()
                        ->helperText('Banka hesapları cari firmaya özel ayrı kayıt olarak tutulur. Varsayılan hesap seçildiğinde diğerleri otomatik kaldırılır.'),
                ])->columns(['default' => 1, 'sm' => 2])->compact(),
            ] : []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    private static function cariEBelgeUyariHtml(array $veri): HtmlString
    {
        $uyarilar = app(EBelgeHazirlikKontrolServisi::class)->cariVerisindenUyarilar($veri);
        if ($uyarilar === []) {
            return new HtmlString('<div class="text-sm text-success-600 dark:text-success-400">Cari e-belge alanları hazır görünüyor.</div>');
        }

        $liste = collect($uyarilar)
            ->map(fn (string $uyari): string => '<li>'.e($uyari).'</li>')
            ->implode('');

        return new HtmlString('<div class="rounded-md border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300"><div class="font-medium">E-belge için önerilen cari düzeltmeleri:</div><ul class="mt-1 list-disc space-y-1 ps-5">'.$liste.'</ul></div>');
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        $detayModu = static::detayModu();
        $routeName = (string) (request()->route()?->getName() ?? '');

        if (! $detayModu && str_ends_with($routeName, '.view')) {
            return static::getEloquentQuery()
                ->whereKey($key)
                ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'kod',
                    'kisa_ad',
                    'tur',
                    'durum',
                    'cari_grubu_id',
                    'vergi_dairesi',
                    'vergi_no',
                    'tc_no',
                    'telefon',
                    'gsm',
                    'email',
                    'website',
                    'adres',
                    'ulke',
                    'il',
                    'ilce',
                    'posta_kodu',
                    'yetkili_kisi',
                    'risk_limiti',
                    'vade_gunu',
                    'para_birimi',
                ])
                ->first();
        }

        $kolonlar = $detayModu ? [
            'id',
            'firma_id',
            'kod',
            'ad',
            'kisa_ad',
            'tur',
            'durum',
            'cari_grubu_id',
            'vergi_dairesi',
            'vergi_no',
            'tc_no',
            'telefon',
            'gsm',
            'email',
            'website',
            'adres',
            'ulke',
            'il',
            'ilce',
            'posta_kodu',
            'yetkili_kisi',
            'risk_limiti',
            'vade_gunu',
            'para_birimi',
            'aciklama',
            'created_at',
            'updated_at',
        ] : [
            'id',
            'firma_id',
            'durum',
            'para_birimi',
        ];

        $sorgu = static::getEloquentQuery()
            ->whereKey($key)
            ->select($kolonlar);

        if ($detayModu) {
            $sorgu->with(['firma:id,ad', 'cariGrubu:id,firma_id,ad']);
        } else {
            $sorgu->with('cariGrubu:id,firma_id,ad');
        }

        return $sorgu->first();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->select([
                        'id',
                        'firma_id',
                        'ad',
                        'kod',
                        'tur',
                        'durum',
                        'para_birimi',
                        'telefon',
                        'created_at',
                    ])
                    ->addSelect([
                        'bakiye_tutar' => CariHareketi::query()
                            ->selectRaw('COALESCE(SUM(borc - alacak), 0)')
                            ->whereColumn('cari_id', 'cariler.id')
                            ->whereColumn('para_birimi', 'cariler.para_birimi')
                            ->where('durum', CariHareketDurumu::Aktif->value),
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ünvan')
                    ->searchable()
                    ->sortable()
                    ->limit(36)
                    ->tooltip(fn (?string $state): ?string => Str::length((string) $state) > 36 ? $state : null),
                Tables\Columns\TextColumn::make('telefon')
                    ->label('Telefon')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tur')
                    ->label('Tür')
                    ->formatStateUsing(fn (?CariTuru $state) => $state?->etiket() ?? '—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?CariDurumu $state) => match ($state) {
                        CariDurumu::Aktif => 'Aktif',
                        CariDurumu::Pasif => 'Pasif',
                        default => '—',
                    })
                    ->color(fn (?CariDurumu $state) => match ($state) {
                        CariDurumu::Aktif => 'success',
                        CariDurumu::Pasif => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('para_birimi')
                    ->label('Para bir.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bakiye_tutar')
                    ->label('Bakiye')
                    ->formatStateUsing(fn ($state, Cari $record): HtmlString => static::bakiyeOzetHtml($record))
                    ->html()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tur')
                    ->label('Tür')
                    ->options(collect(CariTuru::cases())->mapWithKeys(fn (CariTuru $e) => [$e->value => $e->etiket()]))
                    ->placeholder('Tümü'),
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        CariDurumu::Aktif->value => 'Aktif',
                        CariDurumu::Pasif->value => 'Pasif',
                    ])
                    ->placeholder('Tümü'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (Cari $record): bool => ! DB::table('cari_hareketleri')->where('cari_id', (int) $record->getKey())->exists()),
                Tables\Actions\Action::make('pasiflestir')
                    ->label('Pasifleştir')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Cari pasifleştirilsin mi?')
                    ->modalDescription('Cari kartı korunur; yeni finans hareketlerinde seçilemez, geçmiş hareketler etkilenmez.')
                    ->visible(fn (Cari $record): bool => $record->durum === CariDurumu::Aktif
                        && DB::table('cari_hareketleri')->where('cari_id', (int) $record->getKey())->exists())
                    ->action(fn (Cari $record): bool => (bool) $record->update(['durum' => CariDurumu::Pasif])),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCariler::route('/'),
            'create' => Pages\CreateCari::route('/create'),
            'view' => Pages\ViewCari::route('/{record}'),
            'edit' => Pages\EditCari::route('/{record}/edit'),
        ];
    }
}
