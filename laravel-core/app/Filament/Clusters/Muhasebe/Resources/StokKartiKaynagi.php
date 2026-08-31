<?php

namespace App\Filament\Clusters\Muhasebe\Resources;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;
use App\Models\Firma;
use App\Models\Muhasebe\Birim;
use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\MuhasebeLogoTuru;
use App\Models\Muhasebe\MuhasebeMalzemeTuru;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\MuhasebeMarkaUretici;
use App\Models\Muhasebe\MuhasebeStokModeli;
use App\Models\Muhasebe\MuhasebeTasarim;
use App\Models\Muhasebe\MuhasebeVaryant;
use App\Models\Muhasebe\StokKarti;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Models\Muhasebe\StokKategorisi;
use App\Models\Muhasebe\VergiOrani;
use App\Muhasebe\Enumlar\HesapDurumu;
use App\Muhasebe\Enumlar\CariDurumu;
use App\Muhasebe\Enumlar\CariTuru;
use App\Muhasebe\Enumlar\StokKartiTuru;
use App\Muhasebe\Filament\AbstractKaynaklar\StokKaynagi;
use App\Services\TenantContextService;
use App\Services\FirmaAyarDeposu;
use App\Support\KullaniciRolYardimcisi;
use App\Support\SaaSemaYardimcisi;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StokKartiKaynagi extends StokKaynagi
{
    /** @var array<string, array<int|string, string>> */
    private static array $formSecenekCache = [];

    private static ?int $aktifFirmaIdCache = null;

    private static ?bool $superAdminMiCache = null;

    private static ?bool $stokBarkodTablosuVarMiCache = null;

    private static ?bool $stokKartiGorselleriTablosuVarMiCache = null;

    protected static ?string $slug = 'stok/stok-listesi';

    protected static bool $isScopedToTenant = false;

    protected static ?string $modelLabel = 'Stok kartı';

    protected static ?string $pluralModelLabel = 'Stok Kartları';

    protected static ?string $recordTitleAttribute = 'ad';

    public static function isWebUrunContext(): bool
    {
        return request()->boolean('web_urun');
    }

    public static function stokYapisiKilitliMi(?StokKarti $stok): bool
    {
        if (! $stok?->exists) {
            return false;
        }

        return $stok->stokHareketleri()->withoutGlobalScopes()->exists()
            || $stok->olcuBakiyeleri()->withoutGlobalScopes()->exists()
            || $stok->stokSeriNolari()->withoutGlobalScopes()->exists();
    }

    public static function vergiOranFormAnahtari(float $oran): string
    {
        $s = rtrim(rtrim(number_format($oran, 4, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }

    /**
     * @param  array<string, mixed>  $veri
     */
    public static function kodBenzersizMi(int $firmaId, string $kod, ?int $haricId = null): bool
    {
        $sorgu = StokKarti::query()->where('firma_id', $firmaId)->where('kod', $kod);
        if ($haricId !== null) {
            $sorgu->whereKeyNot($haricId);
        }

        return ! $sorgu->exists();
    }

    /**
     * @return array{kategori_id:int|null,kategori_kodu:string|null}
     */
    public static function kategoriDegerleriniHazirla(int $firmaId, ?int $kategoriId): array
    {
        $kid = (int) ($kategoriId ?? 0);
        if ($kid < 1) {
            return ['kategori_id' => null, 'kategori_kodu' => null];
        }

        $kategori = StokKategorisi::query()
            ->whereKey($kid)
            ->gorunurFirmaIle($firmaId)
            ->first();

        if (! $kategori) {
            throw ValidationException::withMessages([
                'kategori_id' => 'Kategori aktif firmaya ait olmalıdır.',
            ]);
        }

        return ['kategori_id' => $kid, 'kategori_kodu' => $kategori->kod];
    }

    public static function stokKodUret(int $firmaId): string
    {
        $max = 0;
        $stoklar = StokKarti::query()
            ->where('firma_id', $firmaId)
            ->whereNotNull('kod')
            ->where('kod', 'like', 'STK%')
            ->get(['kod']);

        foreach ($stoklar as $s) {
            $kod = (string) $s->kod;
            $parca = preg_replace('/[^0-9]/', '', substr($kod, 3));
            if ($parca === '' || ! ctype_digit($parca)) {
                continue;
            }
            $max = max($max, (int) $parca);
        }

        $next = $max + 1;

        return 'STK'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, string>
     */
    private static function firmaSecenekleri(): array
    {
        $yerelAnahtar = 'firma';
        if (array_key_exists($yerelAnahtar, self::$formSecenekCache)) {
            return self::$formSecenekCache[$yerelAnahtar];
        }

        return self::$formSecenekCache[$yerelAnahtar] = Cache::remember(
            'muhasebe:stok-karti:form-secenekleri:firma:v1',
            now()->addMinutes(5),
            fn (): array => Firma::query()
                ->orderBy('ad')
                ->pluck('ad', 'id')
                ->all()
        );
    }

    /**
     * @return array<int|string, string>
     */
    private static function formSecenekleri(string $tur, ?int $firmaId, callable $olusturucu, int|string $ek = ''): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return [];
        }

        $yerelAnahtar = $tur.'|'.$firmaId.'|'.$ek;
        if (array_key_exists($yerelAnahtar, self::$formSecenekCache)) {
            return self::$formSecenekCache[$yerelAnahtar];
        }

        return self::$formSecenekCache[$yerelAnahtar] = Cache::remember(
            'muhasebe:stok-karti:form-secenekleri:v1:'.$yerelAnahtar,
            now()->addSeconds(90),
            fn (): array => $olusturucu()
        );
    }

    private static function firmaId(Get $get): int
    {
        $firmaId = $get('firma_id') ?: self::aktifFirmaId();

        return (int) ($firmaId ?: 0);
    }

    private static function aktifFirmaId(): int
    {
        return self::$aktifFirmaIdCache ??= (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
    }

    private static function depoModuluAktifMi(): bool
    {
        return (bool) app(FirmaAyarDeposu::class)->oku(self::aktifFirmaId(), 'stok_depo_modulu_aktif_mi', false);
    }

    private static function depoSecenekleri(?int $firmaId): array
    {
        $firmaId = (int) ($firmaId ?: self::aktifFirmaId());
        $ayarDeposu = app(FirmaAyarDeposu::class);
        $deposuzIzinli = (bool) $ayarDeposu->oku($firmaId, 'stok_deposuz_izinli_mi', true);
        $options = (! self::depoModuluAktifMi() || $deposuzIzinli)
            ? ['0' => 'Deposuz / Genel stok']
            : [];

        if (! self::depoModuluAktifMi() || $firmaId < 1) {
            return $options;
        }

        return $options + Depo::query()
            ->where('firma_id', $firmaId)
            ->aktif()
            ->orderBy('ad')
            ->pluck('ad', 'id')
            ->mapWithKeys(fn (string $ad, int|string $id): array => [(string) $id => $ad])
            ->all();
    }

    private static function varsayilanDepoId(): ?int
    {
        if (! self::depoModuluAktifMi()) {
            return null;
        }

        $id = app(FirmaAyarDeposu::class)->oku(self::aktifFirmaId(), 'stok_varsayilan_depo_id', null);
        if ($id && Depo::query()->whereKey((int) $id)->aktif()->exists()) {
            return (int) $id;
        }

        return Depo::query()->aktif()->where('varsayilan_mi', true)->value('id');
    }

    /**
     * Depo ayarlarını form güvenliğine bırakmadan kayıt katmanında da uygular.
     * Depo modülü kapalı firmalarda kayıtlar otomatik olarak aktif MERKEZ
     * deposuna bağlanır; modül açıkken firma depo seçim kuralları uygulanır.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function depoAlanlariniDogrula(array $data, int $firmaId): array
    {
        $ayarDeposu = app(FirmaAyarDeposu::class);
        $modulAktif = (bool) $ayarDeposu->oku($firmaId, 'stok_depo_modulu_aktif_mi', false);
        $depoSecimiZorunlu = (bool) $ayarDeposu->oku($firmaId, 'stok_depo_secimi_zorunlu_mu', false);
        $deposuzIzinli = (bool) $ayarDeposu->oku($firmaId, 'stok_deposuz_izinli_mi', true);
        $depoId = (int) ($data['depo_id'] ?? 0);

        if (! $modulAktif) {
            $data['depo_id'] = (int) (Depo::withoutGlobalScopes()
                ->where('firma_id', $firmaId)
                ->where('kod', 'MERKEZ')
                ->where('aktif_mi', true)
                ->whereNull('deleted_at')
                ->value('id') ?: 0) ?: null;

            return $data;
        }

        if ($depoId < 1) {
            if ($depoSecimiZorunlu || ! $deposuzIzinli) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'depo_id' => 'Bu firma için aktif bir depo seçilmelidir.',
                ]);
            }

            $data['depo_id'] = null;

            return $data;
        }

        if (! Depo::query()
            ->where('firma_id', $firmaId)
            ->whereKey($depoId)
            ->aktif()
            ->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'depo_id' => 'Seçilen depo aktif firmaya ait değil veya aktif değil.',
            ]);
        }

        $data['depo_id'] = $depoId;

        return $data;
    }

    private static function superAdminMi(): bool
    {
        return self::$superAdminMiCache ??= KullaniciRolYardimcisi::superAdminVeyaIsAdmin(Auth::user());
    }

    private static function stokBarkodTablosuVarMi(): bool
    {
        return self::$stokBarkodTablosuVarMiCache ??= SaaSemaYardimcisi::tabloVarMi('stok_barkodlari');
    }

    private static function stokKartiGorselleriTablosuVarMi(): bool
    {
        return self::$stokKartiGorselleriTablosuVarMiCache ??= SaaSemaYardimcisi::tabloVarMi('stok_karti_gorselleri');
    }

    public static function barkodDetaylariGoster(): bool
    {
        return request()->boolean('barkod_detay');
    }

    public static function eTicaretDetaylariGoster(): bool
    {
        return request()->boolean('e_ticaret_detay');
    }

    public static function detayModu(): bool
    {
        return request()->boolean('detay')
            || static::barkodDetaylariGoster()
            || static::eTicaretDetaylariGoster();
    }

    public static function hizliDuzenlemeModu(): bool
    {
        return request()->boolean('hizli') && ! static::detayModu();
    }

    /**
     * @return array<int, string>
     */
    private static function kategoriSecenekleri(?int $firmaId, int $kategoriId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        return self::formSecenekleri('kategori', $firmaId, fn (): array => StokKategorisi::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($kategoriId): void {
                $q->where('aktif_mi', true);
                if ($kategoriId > 0) {
                    $q->orWhere('id', $kategoriId);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all(), $kategoriId);
    }

    /**
     * @return array<int, string>
     */
    private static function kategoriAramaSonuclari(?int $firmaId, string $search, int $kategoriId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return [];
        }

        $aranan = trim($search);

        return StokKategorisi::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($kategoriId): void {
                $q->where('aktif_mi', true);
                if ($kategoriId > 0) {
                    $q->orWhere('id', $kategoriId);
                }
            })
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function markaUreticiSecenekleri(?int $firmaId, string $secili = ''): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        $secili = trim($secili);

        return self::formSecenekleri('marka-uretici', $firmaId, fn (): array => MuhasebeMarkaUretici::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($secili): void {
                $q->where('aktif_mi', true);
                if ($secili !== '') {
                    $q->orWhere('ad', $secili);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'ad')
            ->all(), $secili);
    }

    /**
     * @return array<string, string>
     */
    private static function markaUreticiAramaSonuclari(?int $firmaId, string $search, string $secili = ''): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return [];
        }

        $aranan = trim($search);
        $secili = trim($secili);

        return MuhasebeMarkaUretici::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($secili): void {
                $q->where('aktif_mi', true);
                if ($secili !== '') {
                    $q->orWhere('ad', $secili);
                }
            })
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'ad')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function birimSecenekleri(?int $firmaId): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        $liste = self::formSecenekleri('birim', $firmaId, fn (): array => Birim::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->orderBy('kod')
            ->get(['kod', 'ad'])
            ->mapWithKeys(fn (Birim $b): array => [
                // Kod yalnızca kayıt anahtarıdır; stok kartı formunda kullanıcıya gösterilmez.
                $b->kod => $b->ad ?: $b->kod,
            ])
            ->all());

        return $liste;
    }

    private static function varsayilanBirimKodu(?int $firmaId): ?string
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return null;
        }

        return Birim::query()
            ->where('firma_id', $firmaId)
            ->where('aktif_mi', true)
            ->where('varsayilan_mi', true)
            ->value('kod');
    }

    /**
     * @return array<string, string>
     */
    private static function kdvOraniSecenekleri(?int $firmaId): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        $secenekler = self::formSecenekleri('kdv-orani', $firmaId, function () use ($firmaId): array {
            $liste = [];
            foreach (VergiOrani::query()
                ->gorunurFirmaIle($firmaId)
                ->where('aktif_mi', true)
                ->orderBy('oran')
                ->get(['kod', 'oran']) as $v) {
                /** @var VergiOrani $v */
                $anahtar = self::vergiOranFormAnahtari((float) $v->oran);
                $liste[$anahtar] = $v->kod.' — %'.$v->oran;
            }

            return $liste;
        });

        if ($secenekler === []) {
            $secenekler[self::vergiOranFormAnahtari(0.0)] = '%0';
            $secenekler[self::vergiOranFormAnahtari(20.0)] = '%20';
        }

        return $secenekler;
    }

    /**
     * @return array<int, string>
     */
    private static function tedarikciSecenekleri(?int $firmaId, int $tedarikciId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        return self::formSecenekleri('tedarikci', $firmaId, fn (): array => Cari::query()
            ->where('firma_id', $firmaId)
            ->where(function (Builder $q) use ($tedarikciId): void {
                $q->where('tur', 'tedarikci');
                if ($tedarikciId > 0) {
                    $q->orWhere('id', $tedarikciId);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all(), $tedarikciId);
    }

    /**
     * @return array<int, string>
     */
    private static function markaSecenekleri(?int $firmaId, int $markaId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        return self::formSecenekleri('marka', $firmaId, fn (): array => MuhasebeMarka::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($markaId): void {
                $q->where('aktif_mi', true);
                if ($markaId > 0) {
                    $q->orWhere('id', $markaId);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all(), $markaId);
    }

    /**
     * @return array<int, string>
     */
    private static function modelSecenekleri(?int $firmaId, int $markaId, int $modelId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        if ($markaId < 1) {
            return [];
        }

        return self::formSecenekleri('model', $firmaId, fn (): array => MuhasebeStokModeli::query()
            ->gorunurFirmaIle($firmaId)
            ->where('marka_id', $markaId)
            ->where(function (Builder $q) use ($modelId): void {
                $q->where('aktif_mi', true);
                if ($modelId > 0) {
                    $q->orWhere('id', $modelId);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all(), $markaId.'|'.$modelId);
    }

    /**
     * @return array<int, string>
     */
    private static function tedarikciAramaSonuclari(?int $firmaId, string $search, int $tedarikciId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return [];
        }

        $aranan = trim($search);

        return Cari::query()
            ->where('firma_id', $firmaId)
            ->where(function (Builder $q) use ($tedarikciId): void {
                $q->where('tur', 'tedarikci');
                if ($tedarikciId > 0) {
                    $q->orWhere('id', $tedarikciId);
                }
            })
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    private static function tedarikciBilgileriHtml(int $firmaId, int $cariId): HtmlString
    {
        if ($firmaId < 1 || $cariId < 1) {
            return new HtmlString('<div class="text-sm text-gray-500">Tedarikçi seçildiğinde adres ve vergi bilgileri burada görünür.</div>');
        }

        $cari = Cari::query()
            ->where('firma_id', $firmaId)
            ->whereKey($cariId)
            ->first([
                'id',
                'ad',
                'telefon',
                'gsm',
                'email',
                'adres',
                'ulke',
                'il',
                'ilce',
                'posta_kodu',
                'vergi_dairesi',
                'vergi_no',
                'tc_no',
            ]);

        if (! $cari) {
            return new HtmlString('<div class="text-sm text-danger-600">Cari bilgisi bulunamadı.</div>');
        }

        $adres = collect([
            $cari->adres,
            collect([$cari->ilce, $cari->il])->filter()->implode(' / '),
            $cari->posta_kodu,
            $cari->ulke,
        ])->filter(fn ($deger): bool => filled($deger))
            ->map(fn ($deger): string => e((string) $deger))
            ->implode('<br>');

        $telefon = collect([$cari->telefon, $cari->gsm])
            ->filter()
            ->map(fn ($deger): string => e((string) $deger))
            ->implode(' · ');

        $satir = static function (string $etiket, mixed $deger): string {
            $deger = filled($deger) ? e((string) $deger) : '—';

            return '<div><dt class="text-xs text-gray-500">'.$etiket.'</dt><dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">'.$deger.'</dd></div>';
        };

        return new HtmlString(
            '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">'
            .'<div class="mb-3 font-medium text-gray-950 dark:text-white">'.e((string) $cari->ad).'</div>'
            .'<dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">'
            .'<div><dt class="text-xs text-gray-500">Adres</dt><dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">'.($adres !== '' ? $adres : '—').'</dd></div>'
            .$satir('Telefon', $telefon)
            .$satir('E-posta', $cari->email)
            .$satir('Vergi dairesi', $cari->vergi_dairesi)
            .$satir('Vergi no', $cari->vergi_no)
            .$satir('T.C. kimlik no', $cari->tc_no)
            .'</dl></div>'
        );
    }

    private static function kayitAdi(string $modelSinifi, mixed $id): ?string
    {
        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        $cacheKey = 'etiket|'.$modelSinifi.'|'.$id;
        if (isset(self::$formSecenekCache[$cacheKey][$id])) {
            return self::$formSecenekCache[$cacheKey][$id];
        }

        $ad = $modelSinifi::query()->whereKey($id)->value('ad');

        return is_string($ad) && $ad !== ''
            ? self::$formSecenekCache[$cacheKey][$id] = $ad
            : null;
    }

    /**
     * @return array<int, string>
     */
    private static function tanimAramaSonuclari(?int $firmaId, string $modelSinifi, string $search): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1) {
            return [];
        }

        $aranan = trim($search);

        return $modelSinifi::query()
            ->gorunurFirmaIle($firmaId)
            ->where('aktif_mi', true)
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function modelAramaSonuclari(?int $firmaId, int $markaId, string $search): array
    {
        $firmaId = (int) ($firmaId ?: 0);
        if ($firmaId < 1 || $markaId < 1) {
            return [];
        }

        $aranan = trim($search);

        return MuhasebeStokModeli::query()
            ->gorunurFirmaIle($firmaId)
            ->where('marka_id', $markaId)
            ->where('aktif_mi', true)
            ->when($aranan !== '', fn (Builder $q): Builder => $q->where('ad', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $aranan).'%'))
            ->orderBy('ad')
            ->limit(50)
            ->pluck('ad', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function basitTanimSecenekleri(string $tur, ?int $firmaId, string $modelSinifi, int $seciliId = 0): array
    {
        $firmaId = (int) ($firmaId ?: 0);

        return self::formSecenekleri($tur, $firmaId, fn (): array => $modelSinifi::query()
            ->gorunurFirmaIle($firmaId)
            ->where(function (Builder $q) use ($seciliId): void {
                $q->where('aktif_mi', true);
                if ($seciliId > 0) {
                    $q->orWhere('id', $seciliId);
                }
            })
            ->orderBy('ad')
            ->limit(100)
            ->pluck('ad', 'id')
            ->all(), $seciliId);
    }

    public static function form(Form $form): Form
    {
        $olusturma = $form->getOperation() === 'create';
        $superAdminMi = self::superAdminMi();
        $stokBarkodTablosuVar = ! $olusturma && self::stokBarkodTablosuVarMi();
        $inlineTanimOlusturmaAktif = false;
        $hizliDuzenleme = ! $olusturma && static::hizliDuzenlemeModu();

        if ($hizliDuzenleme) {
            return $form->schema([
                Forms\Components\Select::make('durum')
                    ->label('Durum')
                    ->options([
                        HesapDurumu::Aktif->value => 'Aktif',
                        HesapDurumu::Pasif->value => 'Pasif',
                    ])
                    ->required()
                    ->default(HesapDurumu::Aktif->value),
            ]);
        }

        return $form->schema([
            Forms\Components\Tabs::make('Stok kartı')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Temel')
                        ->schema([
                            Forms\Components\Select::make('firma_id')
                                ->label('Firma')
                                ->options(fn (): array => static::firmaSecenekleri())
                                ->searchable()
                                ->required($superAdminMi)
                                ->visible($superAdminMi)
                                ->live()
                                ->default(fn () => static::aktifFirmaId())
                                ->dehydrated(fn () => $superAdminMi)
                                ->helperText(fn () => $superAdminMi ? null : 'Firma oturumdaki aktif firmadan atanır.'),
                            Forms\Components\TextInput::make('ad')
                                ->label('Ad')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $set('slug', Str::slug((string) $state));
                                }),
                            Forms\Components\Select::make('tur')
                                ->label('Tür')
                                ->options(collect(StokKartiTuru::cases())->mapWithKeys(fn (StokKartiTuru $e) => [$e->value => $e->etiket()]))
                                ->required()
                                ->default(fn () => static::isWebUrunContext() ? StokKartiTuru::ETicaret->value : StokKartiTuru::TicariMal->value)
                                ->disabled(fn () => static::isWebUrunContext())
                                ->live()
                                ->dehydrated(),
                            Forms\Components\TextInput::make('kisa_ad')
                                ->label('Kısa ad')
                                ->maxLength(128),
                            Forms\Components\TextInput::make('barkod')
                                ->label('Barkod')
                                ->maxLength(128),
                            ...($stokBarkodTablosuVar && ! $olusturma && static::barkodDetaylariGoster() ? [
                                Forms\Components\Repeater::make('barkodlar')
                                    ->label('Alternatif Barkodlar')
                                    ->relationship('barkodlar')
                                    ->schema([
                                        Forms\Components\TextInput::make('barkod')
                                            ->label('Barkod')
                                            ->required()
                                            ->maxLength(128),
                                        Forms\Components\Toggle::make('aktif')
                                            ->label('Aktif')
                                            ->default(true),
                                        Forms\Components\Toggle::make('varsayilan_mi')
                                            ->label('Varsayilan')
                                            ->default(false),
                                    ])
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, Get $get): array {
                                        $data['firma_id'] = (int) ($get('firma_id') ?: static::aktifFirmaId());

                                        return $data;
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Get $get): array {
                                        $data['firma_id'] = (int) ($get('firma_id') ?: static::aktifFirmaId());

                                        return $data;
                                    })
                                    ->columns(3)
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull()
                                    ->helperText('Bu barkodlar POS ekraninda ana barkod ile birlikte aranir.'),
                            ] : []),
                            Forms\Components\TextInput::make('seri_no')
                                ->label('Seri No')
                                ->maxLength(128),
                            Forms\Components\TextInput::make('imei_no')
                                ->label('IMEI No')
                                ->maxLength(128),
                            Forms\Components\Select::make('kategori_id')
                                ->label('Kategori')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::kategoriAramaSonuclari(static::firmaId($get), $search, (int) ($get('kategori_id') ?: 0)))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(StokKategorisi::class, $value))
                                ->searchable()
                                ->nullable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): int {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages([
                                            'kategori_id' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                        ]);
                                    }

                                    $kod = trim((string) ($data['kod'] ?? ''));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages([
                                            'kategori_id' => 'Kod ve ad zorunludur.',
                                        ]);
                                    }

                                    $kapsam = $firmaId;
                                    if (StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->where('kod', $kod)
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }

                                    if (StokKategorisi::tenantScopeOlmadan(fn () => StokKategorisi::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->where('ad', $ad)
                                        ->exists())) {
                                        throw ValidationException::withMessages(['ad' => 'Bu ad bu firmada zaten var.']);
                                    }

                                    $kayit = StokKategorisi::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (int) $kayit->getKey();
                                } : null),


                            Forms\Components\Select::make('marka_uretici')
                                ->label('Marka / üretici')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::markaUreticiAramaSonuclari(static::firmaId($get), $search, (string) ($get('marka_uretici') ?: '')))
                                ->getOptionLabelUsing(fn ($value): ?string => is_scalar($value) ? (string) $value : null)
                                ->searchable()
                                ->nullable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): string {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages(['marka_uretici' => 'Önce firma seçin veya aktif firma oturumu açın.']);
                                    }

                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages(['marka_uretici' => 'Kod ve ad zorunludur.']);
                                    }

                                    $kapsam = $firmaId;
                                    if (MuhasebeMarkaUretici::tenantScopeOlmadan(fn () => MuhasebeMarkaUretici::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }

                                    $kayit = MuhasebeMarkaUretici::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (string) $kayit->ad;
                                } : null),

                            Forms\Components\Select::make('durum')
                                ->label('Durum')
                                ->options(collect(HesapDurumu::cases())->mapWithKeys(fn (HesapDurumu $d) => [$d->value => match ($d) {
                                    HesapDurumu::Aktif => 'Aktif',
                                    HesapDurumu::Pasif => 'Pasif',
                                }]))
                                ->required()
                                ->default(HesapDurumu::Aktif->value),
                        ])->columns(2),

                    ...($hizliDuzenleme ? [] : [
                    Forms\Components\Tabs\Tab::make('Fiyat')
                        ->schema([
                            Forms\Components\TextInput::make('satis_fiyati')
                                ->label('Satış fiyatı')
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->prefix('₺'),
                            Forms\Components\TextInput::make('para_birimi')
                                ->label('Para birimi')
                                ->required()
                                ->length(3)
                                ->default('TRY')
                                ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : $state),
                            Forms\Components\TextInput::make('indirimli_fiyat')
                                ->label('İndirimli fiyat')
                                ->numeric()
                                ->default(null)
                                ->prefix('₺'),
                            Forms\Components\TextInput::make('alis_fiyati')
                                ->label('Alış fiyatı')
                                ->numeric()
                                ->default(0)
                                ->prefix('₺'),
                            Forms\Components\Select::make('kdv_orani')
                                ->label('KDV oranı (%)')
                                ->required()
                                ->options(fn (Get $get): array => static::kdvOraniSecenekleri(static::firmaId($get)))
                                ->default(fn () => self::vergiOranFormAnahtari(20.0))
                                ->searchable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                    Forms\Components\TextInput::make('oran')
                                        ->label('Oran (%)')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->maxValue(100),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): string {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages([
                                            'kdv_orani' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                        ]);
                                    }
                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    $oran = isset($data['oran']) ? (float) $data['oran'] : -1.0;
                                    if ($kod === '' || $ad === '' || $oran < 0 || $oran > 100) {
                                        throw ValidationException::withMessages([
                                            'kdv_orani' => 'Kod, ad ve geçerli oran zorunludur.',
                                        ]);
                                    }
                                    $oran = round($oran, 4);
                                    $kapsam = $firmaId;
                                    if (VergiOrani::tenantScopeOlmadan(fn () => VergiOrani::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }
                                    $kayit = VergiOrani::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'oran' => $oran,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return self::vergiOranFormAnahtari((float) $kayit->oran);
                                } : null)
                                ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (float) $state),
                            Forms\Components\TextInput::make('gumruk_orani')
                                ->label('Gümrük oranı (%)')
                                ->numeric()
                                ->default(null)
                                ->suffix('%'),
                        ])->columns(2),

                    Forms\Components\Tabs\Tab::make('Stok')
                        ->schema([
                            Forms\Components\Select::make('olculu_takip_turu')
                                ->label('Stok takip yöntemi')
                                ->options([
                                    'standart' => 'Standart',
                                    'uzunluk' => 'Uzunluk + Adet',
                                    'alan' => 'Alan (m²) + Adet',
                                    'hacim' => 'Hacim (m³) + Adet',
                                    'agirlik' => 'Ağırlık (kg) + Adet',
                                ])
                                ->default('standart')
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    $tur = OlculuStokTakipTuru::tryFrom((string) ($state ?? 'standart'));
                                    $set('birim', $tur?->olculuMu()
                                        ? 'AD'
                                        : static::varsayilanBirimKodu(static::firmaId($get)));
                                })
                                ->required()
                                ->extraAttributes(['style' => 'order: 3'])
                                ->extraFieldWrapperAttributes(['class' => 'stok-takip-yontemi-field'])
                                ->disabled(fn (?StokKarti $record): bool => static::stokYapisiKilitliMi($record))
                                ->helperText(fn (?StokKarti $record): string => static::stokYapisiKilitliMi($record)
                                    ? 'Stok hareketi veya bakiyesi bulunduğu için bu seçim kilitlidir.'
                                    : 'Ölçülü ürünlerde ana birim ve adet eşdeğeri birlikte izlenir.'),
                            Forms\Components\TextInput::make('stok_miktari')
                                ->label('Mevcut stok')
                                ->numeric()
                                ->default(0)
                                ->disabled(fn (Get $get): bool => OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                ->dehydrated(true)
                                ->helperText(fn (Get $get): ?string => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                    ? 'Ölçülü stokta mevcut stok, ölçü satırlarındaki açılış adetlerinin toplamından otomatik hesaplanır.'
                                    : null),
                            Forms\Components\Select::make('olcu_yapisi')
                                ->label('Ölçü yapısı')
                                ->options(['sabit' => 'Sabit ölçü', 'coklu' => 'Birden fazla ölçü'])
                                ->default('sabit')
                                ->disableOptionWhen(fn (string $value): bool => $value === 'coklu')
                                ->visible(fn (Get $get): bool => OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                ->required(fn (Get $get): bool => OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                ->helperText(fn (Get $get): string => ($get('olcu_yapisi') ?? 'sabit') === 'coklu'
                                    ? 'Birden fazla ölçü seçeneği artık kullanıma kapalıdır.'
                                    : 'Sabit ölçüde tek bir boyut veya birim ağırlığı tanımlanır; açılış adedi bu ölçüye göre hesaplanır.')
                                ->disabled(fn (?StokKarti $record): bool => static::stokYapisiKilitliMi($record))
                                ->live(),
                            Forms\Components\Repeater::make('olcu_satirlari')
                                ->label('Ölçü satırları')
                                ->visible(fn (Get $get): bool => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false) && ($get('olcu_yapisi') ?? 'sabit') === 'coklu')
                                ->required(fn (Get $get): bool => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false) && ($get('olcu_yapisi') ?? 'sabit') === 'coklu')
                                ->disabled(fn (?StokKarti $record): bool => static::stokYapisiKilitliMi($record))
                                ->columnSpanFull()
                                ->minItems(1)
                                ->defaultItems(1)
                                ->schema([
                                    Forms\Components\TextInput::make('en')->label('En')->numeric()->visible(fn (Get $get): bool => in_array((string) ($get('../../olculu_takip_turu') ?? ''), ['alan', 'hacim'], true))->required(fn (Get $get): bool => in_array((string) ($get('../../olculu_takip_turu') ?? ''), ['alan', 'hacim'], true)),
                                    Forms\Components\TextInput::make('boy')->label('Boy')->numeric()->visible(fn (Get $get): bool => in_array((string) ($get('../../olculu_takip_turu') ?? ''), ['uzunluk', 'alan', 'hacim'], true))->required(fn (Get $get): bool => in_array((string) ($get('../../olculu_takip_turu') ?? ''), ['uzunluk', 'alan', 'hacim'], true)),
                                    Forms\Components\TextInput::make('olcu_acilis_adet')->label('Açılış adedi')->numeric()->default(0)->required(),
                                    Forms\Components\TextInput::make('yukseklik')->label('Kalınlık-Yükseklik')->numeric()->visible(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'hacim')->required(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'hacim'),
                                    Forms\Components\TextInput::make('bir_adet_agirlik')->label('Bir adet ağırlığı')->numeric()->visible(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'agirlik')->required(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'agirlik'),
                                    Forms\Components\Select::make('agirlik_birimi')->label('Ağırlık birimi')->options(['g' => 'g', 'kg' => 'kg', 'ton' => 'ton'])->default('kg')->visible(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'agirlik')->required(fn (Get $get): bool => ($get('../../olculu_takip_turu') ?? '') === 'agirlik'),
                                ])->columns(3)->addActionLabel('Ölçü ekle'),
                            Forms\Components\Select::make('olcu_giris_birimi')
                                ->label('Ölçü giriş birimi')
                                ->options(['mm' => 'mm', 'cm' => 'cm', 'm' => 'metre'])
                                ->default('cm')
                                ->visible(fn (Get $get): bool => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false) && ($get('olculu_takip_turu') ?? '') !== 'agirlik')
                                ->required(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') !== 'agirlik'),
                            Forms\Components\Select::make('agirlik_birimi')
                                ->label('Ağırlık birimi')
                                ->options(['g' => 'g', 'kg' => 'kg', 'ton' => 'ton'])
                                ->default('kg')
                                ->visible(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'agirlik' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->required(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'agirlik' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit'),
                            Forms\Components\TextInput::make('olcu_en')
                                ->label('En')
                                ->numeric()
                                ->visible(fn (Get $get): bool => in_array((string) ($get('olculu_takip_turu') ?? ''), ['alan', 'hacim'], true) && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->required(fn (Get $get): bool => in_array((string) ($get('olculu_takip_turu') ?? ''), ['alan', 'hacim'], true) && ($get('olcu_yapisi') ?? 'sabit') === 'sabit'),
                            Forms\Components\TextInput::make('olcu_boy')
                                ->label('Boy')
                                ->numeric()
                                ->visible(fn (Get $get): bool => in_array((string) ($get('olculu_takip_turu') ?? ''), ['uzunluk', 'alan', 'hacim'], true) && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->required(fn (Get $get): bool => in_array((string) ($get('olculu_takip_turu') ?? ''), ['uzunluk', 'alan', 'hacim'], true) && ($get('olcu_yapisi') ?? 'sabit') === 'sabit'),
                            Forms\Components\TextInput::make('olcu_yukseklik')
                                ->label('Kalınlık-Yükseklik')
                                ->numeric()
                                ->visible(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'hacim' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->required(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'hacim' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit'),
                            Forms\Components\TextInput::make('olcu_bir_adet_agirlik')
                                ->label('Bir adet ağırlığı')
                                ->numeric()
                                ->visible(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'agirlik' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->required(fn (Get $get): bool => ($get('olculu_takip_turu') ?? '') === 'agirlik' && ($get('olcu_yapisi') ?? 'sabit') === 'sabit'),
                            Forms\Components\TextInput::make('olcu_acilis_adet')
                                ->label('Açılış adedi')
                                ->numeric()
                                ->default(0)
                                ->visible(fn (Get $get): bool => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false) && ($get('olcu_yapisi') ?? 'sabit') === 'sabit')
                                ->helperText('Sabit ölçüde adet girerseniz ana miktar sunucuda hesaplanır.'),
                            Forms\Components\Toggle::make('parcali_kullanima_izin')
                                ->label('Parçalı kullanıma izin ver')
                                ->helperText('Ölçülü ürünün tamamı yerine kesilerek veya bölünerek bir kısmının kullanılabilmesini sağlar. Kapalı olduğunda ürün yalnızca tanımlanan tam ölçü veya adet üzerinden işlem görür.')
                                ->default(false)
                                ->visible(fn (Get $get): bool => OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false),
                            Forms\Components\Select::make('birim')
                                ->label('Birim')
                                ->required()
                                ->extraAttributes(['style' => 'order: 2'])
                                ->options(fn (Get $get): array => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                    ? ['AD' => 'Adet']
                                    : static::birimSecenekleri(static::firmaId($get)))
                                ->selectablePlaceholder(fn (Get $get): bool => ! (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false))
                                ->default(fn (Get $get): ?string => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                    ? 'AD'
                                    : static::varsayilanBirimKodu(static::firmaId($get)))
                                ->afterStateHydrated(function (Forms\Components\Select $component, $state, Get $get): void {
                                    if (blank($state) && (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)) {
                                        $component->state('AD');
                                    }
                                })
                                ->formatStateUsing(fn ($state, Get $get): ?string => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                    ? 'AD'
                                    : ($state ?: null))
                                ->dehydrateStateUsing(fn ($state, Get $get): ?string => (OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                    ? 'AD'
                                    : ($state ?: null))
                                ->disabled(fn (Get $get): bool => OlculuStokTakipTuru::tryFrom((string) ($get('olculu_takip_turu') ?? 'standart'))?->olculuMu() ?? false)
                                ->dehydrated(true)
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): string {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages([
                                            'birim' => 'Önce firma seçin veya aktif firma oturumu açın.',
                                        ]);
                                    }
                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages([
                                            'birim' => 'Kod ve ad zorunludur.',
                                        ]);
                                    }
                                    $kapsam = $firmaId;
                                    if (Birim::tenantScopeOlmadan(fn () => Birim::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }
                                    $kayit = Birim::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (string) $kayit->kod;
                                } : null),
                            Forms\Components\TextInput::make('kritik_seviye_miktar')
                                ->label('Kritik stok seviyesi')
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('minimum_stok')
                                ->label('Minimum stok')
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('maksimum_stok')
                                ->label('Maksimum stok')
                                ->numeric()
                                ->default(null),
                            Forms\Components\Select::make('depo_id')
                                ->label('Stok konumu')
                                ->options(fn (Get $get): array => static::depoSecenekleri(static::firmaId($get)))
                                ->default(fn (): ?int => static::varsayilanDepoId())
                                ->searchable()
                                ->nullable()
                                ->visible(fn (): bool => static::depoModuluAktifMi())
                                ->helperText(fn (): string => static::depoModuluAktifMi()
                                    ? 'Depo seçilmezse stok Deposuz / Genel stok olarak tutulur.'
                                    : 'Depo modülü kapalı; stok Deposuz / Genel stok olarak tutulur.'),
                            Forms\Components\Hidden::make('negative_flag')
                                ->default(false),
                            Forms\Components\Toggle::make('stok_takip')
                                ->label('Sonsuz stok')
                                ->helperText('Açıkken stok miktarı kontrol edilmez.')
                                ->formatStateUsing(fn ($state): bool => ! (bool) $state)
                                ->dehydrateStateUsing(fn ($state): bool => ! (bool) $state)
                                // `stok_takip` veritabanında gerçek stok takibini tutar;
                                // bu alan ise kullanıcıya "Sonsuz stok" olarak ters gösterilir.
                                // Bu nedenle varsayılan kapalı görünüm için gerçek değer true olmalıdır.
                                ->default(true),
                            Forms\Components\Select::make('stok_takip_tipi')
                                ->label('Seri no takibi')
                                ->options([
                                    StokKarti::STOK_TAKIP_TIPI_BASIT => 'Basit stok (önerilen)',
                                    StokKarti::STOK_TAKIP_TIPI_SERI => 'Seri No Barkodu takibi',
                                ])
                                ->default(StokKarti::STOK_TAKIP_TIPI_BASIT)
                                ->selectablePlaceholder(false)
                                ->helperText('Basit stokta ek bilgi istenmez. Seri no takibinde her fiziksel ürün benzersiz seri numarasıyla izlenir.')
                                ->live(),
                            Forms\Components\Textarea::make('seri_nolari')
                                ->label('İlk stok seri numaraları')
                                ->rows(2)
                                ->visible(fn (Get $get): bool => $get('stok_takip_tipi') === StokKarti::STOK_TAKIP_TIPI_SERI)
                                ->helperText('Her satıra bir Seri No Barkodu yazabilirsiniz; ilk stok miktarıyla aynı sayıda olmalıdır.'),
                            Forms\Components\DatePicker::make('garanti_baslangic_tarihi')
                                ->label('Garanti başlangıcı')
                                ->visible(fn (Get $get): bool => $get('stok_takip_tipi') === StokKarti::STOK_TAKIP_TIPI_SERI)
                                ->nullable(),
                            Forms\Components\DatePicker::make('garanti_bitis_tarihi')
                                ->label('Garanti bitişi')
                                ->visible(fn (Get $get): bool => $get('stok_takip_tipi') === StokKarti::STOK_TAKIP_TIPI_SERI)
                                ->nullable(),
                            Forms\Components\Placeholder::make('stok_takip_aciklama')
                                ->label('Seri no takip açıklaması')
                                ->content(function (Get $get): string {
                                    return match ($get('stok_takip_tipi')) {
                                        StokKarti::STOK_TAKIP_TIPI_SERI => 'Seri numarası takibinde her fiziksel ürünün benzersiz seri numarası bulunur. İlk stok seri numaralarını her satıra bir değer gelecek şekilde girin; satış ve servis işlemlerinde ürünün seri numarası üzerinden izlenebilirliği korunur.',
                                        default => '',
                                    };
                                })
                                ->visible(fn (Get $get): bool => in_array($get('stok_takip_tipi'), [
                                    StokKarti::STOK_TAKIP_TIPI_SERI,
                                ], true))
                                ->columnSpanFull(),
                        ])->columns(2),
                    ]),

                    Forms\Components\Tabs\Tab::make('E-Ticaret')
                        ->schema([
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->maxLength(255)
                                ->helperText('Boş bırakılırsa ürün adından otomatik oluşturulur.'),
                            Forms\Components\TextInput::make('seo_title')
                                ->label('SEO Başlık')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('seo_description')
                                ->label('SEO Açıklama')
                                ->rows(3),
                            Forms\Components\TextInput::make('seo_keywords')
                                ->label('SEO Keywords')
                                ->maxLength(255),
                            Forms\Components\TextInput::make('og_baslik')
                                ->label('OG Başlık')
                                ->maxLength(255),
                            Forms\Components\Textarea::make('og_aciklama')
                                ->label('OG Açıklama')
                                ->rows(3),
                            Forms\Components\TextInput::make('og_etiket')
                                ->label('OG Etiket')
                                ->maxLength(255),
                            Forms\Components\FileUpload::make('og_gorsel')
                                ->label('OG görsel')
                                ->disk('public')
                                ->directory('stok/og')
                                ->image()
                                ->visibility('public'),
                            Forms\Components\Repeater::make('gorseller')
                                ->label('Ürün Görselleri')
                                ->relationship()
                                ->orderColumn('sira')
                                ->defaultItems(0)
                                ->collapsible()
                                ->cloneable(fn (string $operation): bool => $operation !== 'create')
                                ->collapsed()
                                ->reorderableWithButtons(fn (string $operation): bool => $operation !== 'create')
                                ->itemLabel(function (array $state): ?string {
                                    $label = trim((string) ($state['alt_metin'] ?? ''));

                                    if ($label !== '') {
                                        return $label;
                                    }

                                    return ! empty($state['kapak_mi']) ? 'Kapak Görsel' : 'Görsel';
                                })
                                ->addActionLabel('Görsel Ekle')
                                ->schema([
                                    Forms\Components\FileUpload::make('dosya_yolu')
                                        ->label('Görsel')
                                        ->disk('public')
                                        ->directory('stok/gallery')
                                        ->image()
                                        ->imageEditor()
                                        ->imageEditorMode(2)
                                        ->visibility('public')
                                        ->panelLayout('integrated')
                                        ->openable()
                                        ->downloadable()
                                        ->previewable(true)
                                        ->required(),
                                    Forms\Components\TextInput::make('alt_metin')
                                        ->label('Alt metin')
                                        ->maxLength(255),
                                    Forms\Components\Toggle::make('kapak_mi')
                                        ->label('Kapak Görsel')
                                        ->default(false),
                                    Forms\Components\Toggle::make('aktif_mi')
                                        ->label('Aktif')
                                        ->default(true),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->helperText('Görselleri tek tek ekleyebilir, önizleyebilir, sıralayabilir ve kapak görsel seçebilirsiniz.'),
                        ])
                        ->columns(2),

                    Forms\Components\Tabs\Tab::make('Diğer')
                        ->schema([
                            Forms\Components\Select::make('tedarikci_id')
                                ->label('Tedarikçi')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tedarikciAramaSonuclari(static::firmaId($get), $search, (int) ($get('tedarikci_id') ?: 0)))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(Cari::class, $value))
                                ->nullable()
                                ->live()
                                ->searchable()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Tedarikçi adı / ünvanı')
                                        ->required()
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('telefon')
                                        ->label('Telefon')
                                        ->tel()
                                        ->maxLength(64),
                                ])
                                ->createOptionUsing(function (array $data, ComponentContainer $form): int {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages([
                                            'tedarikci_id' => 'Önce aktif firma seçilmelidir.',
                                        ]);
                                    }

                                    $kayit = Cari::query()->create([
                                        'firma_id' => $firmaId,
                                        'ad' => trim((string) ($data['ad'] ?? '')),
                                        'kisa_ad' => filled($data['kisa_ad'] ?? null) ? trim((string) $data['kisa_ad']) : null,
                                        'telefon' => filled($data['telefon'] ?? null) ? trim((string) $data['telefon']) : null,
                                        'email' => filled($data['email'] ?? null) ? trim((string) $data['email']) : null,
                                        'tur' => CariTuru::Tedarikci->value,
                                        'durum' => CariDurumu::Aktif->value,
                                        'para_birimi' => 'TRY',
                                    ]);

                                    return (int) $kayit->getKey();
                                }),
                            Forms\Components\Placeholder::make('tedarikci_bilgileri')
                                ->label('Cari bilgileri')
                                ->visible(fn (Get $get): bool => (int) ($get('tedarikci_id') ?: 0) > 0)
                                ->content(fn (Get $get): HtmlString => static::tedarikciBilgileriHtml(
                                    static::firmaId($get),
                                    (int) ($get('tedarikci_id') ?: 0)
                                ))
                                ->columnSpanFull(),
                            Forms\Components\Section::make('Dış sistem kodları')
                                ->schema([
                                    Forms\Components\TextInput::make('sku')
                                        ->label('SKU')
                                        ->maxLength(128),
                                    Forms\Components\TextInput::make('upc')
                                        ->label('UPC')
                                        ->maxLength(32),
                                    Forms\Components\TextInput::make('ean')
                                        ->label('EAN')
                                        ->maxLength(32),
                                    Forms\Components\TextInput::make('gtin')
                                        ->label('GTIN')
                                        ->maxLength(32),
                                    Forms\Components\TextInput::make('mpn')
                                        ->label('MPN')
                                        ->maxLength(128),
                                    Forms\Components\TextInput::make('amazon_asin')
                                        ->label('Amazon ASIN')
                                        ->maxLength(20),
                                    Forms\Components\TextInput::make('fba_kodu')
                                        ->label('Amazon FBA kodu')
                                        ->maxLength(128),
                                ])
                                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                                ->compact()
                                ->columnSpanFull()
                                ->description('Pazar yeri ve dış sistem kodları isteğe bağlıdır.'),

                            Forms\Components\Select::make('marka_id')
                                ->label('Ürün Markaları')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tanimAramaSonuclari(static::firmaId($get), MuhasebeMarka::class, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeMarka::class, $value))
                                ->searchable()
                                ->nullable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): int {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages(['marka_id' => 'Önce firma seçin veya aktif firma oturumu açın.']);
                                    }
                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages(['marka_id' => 'Kod ve ad zorunludur.']);
                                    }
                                    $kapsam = $firmaId;
                                    if (MuhasebeMarka::tenantScopeOlmadan(fn () => MuhasebeMarka::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }
                                    $kayit = MuhasebeMarka::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (int) $kayit->getKey();
                                } : null),

                            Forms\Components\Select::make('model_id')
                                ->label('Ürün Model')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::modelAramaSonuclari(static::firmaId($get), (int) ($get('marka_id') ?: 0), $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeStokModeli::class, $value))
                                ->searchable()
                                ->nullable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): int {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    $markaId = (int) (data_get($form->getRawState(), 'marka_id') ?: 0);
                                    if ($firmaId < 1 || $markaId < 1) {
                                        throw ValidationException::withMessages(['model_id' => 'Önce marka seçin.']);
                                    }
                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages(['model_id' => 'Kod ve ad zorunludur.']);
                                    }
                                    $kapsam = $firmaId;
                                    if (MuhasebeStokModeli::tenantScopeOlmadan(fn () => MuhasebeStokModeli::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->where('marka_id', $markaId)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu marka için zaten var.']);
                                    }
                                    $kayit = MuhasebeStokModeli::query()->create([
                                        'firma_id' => $firmaId,
                                        'marka_id' => $markaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (int) $kayit->getKey();
                                } : null),

                            Forms\Components\Select::make('varyant_id')
                                ->label('Varyant')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tanimAramaSonuclari(static::firmaId($get), MuhasebeVaryant::class, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeVaryant::class, $value))
                                ->searchable()
                                ->nullable()
                                ->createOptionForm($inlineTanimOlusturmaAktif ? [
                                    Forms\Components\TextInput::make('kod')
                                        ->label('Kod')
                                        ->required()
                                        ->maxLength(64),
                                    Forms\Components\TextInput::make('ad')
                                        ->label('Ad')
                                        ->required()
                                        ->maxLength(128),
                                ] : null)
                                ->createOptionUsing($inlineTanimOlusturmaAktif ? function (array $data, ComponentContainer $form): int {
                                    $firmaId = (int) (data_get($form->getRawState(), 'firma_id') ?: static::aktifFirmaId());
                                    if ($firmaId < 1) {
                                        throw ValidationException::withMessages(['varyant_id' => 'Önce firma seçin veya aktif firma oturumu açın.']);
                                    }
                                    $kod = Str::upper(trim((string) ($data['kod'] ?? '')));
                                    $ad = trim((string) ($data['ad'] ?? ''));
                                    if ($kod === '' || $ad === '') {
                                        throw ValidationException::withMessages(['varyant_id' => 'Kod ve ad zorunludur.']);
                                    }
                                    $kapsam = $firmaId;
                                    if (MuhasebeVaryant::tenantScopeOlmadan(fn () => MuhasebeVaryant::query()
                                        ->where('tanim_firma_kapsami', $kapsam)
                                        ->whereRaw('UPPER(kod) = ?', [$kod])
                                        ->exists())) {
                                        throw ValidationException::withMessages(['kod' => 'Bu kod bu firmada zaten var.']);
                                    }
                                    $kayit = MuhasebeVaryant::query()->create([
                                        'firma_id' => $firmaId,
                                        'kod' => $kod,
                                        'ad' => $ad,
                                        'aktif_mi' => true,
                                        'is_sabit' => false,
                                    ]);

                                    return (int) $kayit->getKey();
                                } : null),

                            Forms\Components\Select::make('tasarim_id')
                                ->label('Tasarım')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tanimAramaSonuclari(static::firmaId($get), MuhasebeTasarim::class, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeTasarim::class, $value))
                                ->nullable()
                                ->searchable(),

                            Forms\Components\Select::make('malzeme_turu_id')
                                ->label('Malzeme türü')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tanimAramaSonuclari(static::firmaId($get), MuhasebeMalzemeTuru::class, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeMalzemeTuru::class, $value))
                                ->nullable()
                                ->searchable(),

                            Forms\Components\Select::make('logo_turu_id')
                                ->label('Logo türü')
                                ->getSearchResultsUsing(fn (string $search, Get $get): array => static::tanimAramaSonuclari(static::firmaId($get), MuhasebeLogoTuru::class, $search))
                                ->getOptionLabelUsing(fn ($value): ?string => static::kayitAdi(MuhasebeLogoTuru::class, $value))
                                ->nullable()
                                ->searchable(),

                            Forms\Components\Textarea::make('aciklama')
                                ->label('Açıklama')
                                ->rows(4)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('agirlik')
                                ->label('Kargo paketi ağırlığı (kg)')
                                ->helperText('Kargo desi hesabında paket hacmi yoksa kullanılır.')
                                ->numeric()
                                ->nullable(),
                            Forms\Components\TextInput::make('hacim')
                                ->label('Kargo paketi hacmi (m³)')
                                ->helperText('Kargo desi hesabı için kullanılır. 1 m³ yaklaşık 333 desidir.')
                                ->numeric()
                                ->nullable(),
                            Forms\Components\TextInput::make('kargo_sinifi')
                                ->label('Kargo sınıfı')
                                ->maxLength(64)
                                ->nullable(),
                            Forms\Components\TextInput::make('satis_adedi')
                                ->label('Satış adedi')
                                ->numeric()
                                ->default(0),
                            Forms\Components\TextInput::make('goruntulenme_sayisi')
                                ->label('Görüntülenme')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    public static function resolveRecordRouteBinding(int|string $key): ?Model
    {
        if (filled(request()->route('record')) && static::hizliDuzenlemeModu()) {
            return static::getModel()::query()
                ->select(['id', 'firma_id', 'durum'])
                ->whereKey($key)
                ->first();
        }

        return parent::resolveRecordRouteBinding($key);
    }

    private static function sabitOlcuEtiketi(StokKarti $kayit): string
    {
        if (! ($kayit->olculu_takip_turu instanceof OlculuStokTakipTuru)
            || ! $kayit->olculu_takip_turu->olculuMu()
            || (string) ($kayit->olcu_yapisi ?? 'sabit') !== 'sabit') {
            return '-';
        }

        $olcu = collect($kayit->stokOlculeri ?? [])->first(fn ($item): bool => (bool) $item->aktif_mi);
        if (! $olcu) {
            return '-';
        }

        $fmt = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
        $birim = $olcu->olcu_birimi ?: '';
        $deger = match ($kayit->olculu_takip_turu) {
            OlculuStokTakipTuru::Uzunluk => $fmt($olcu->boy ?: $olcu->bir_adet_ana_miktar),
            OlculuStokTakipTuru::Alan => $fmt($olcu->en).' × '.$fmt($olcu->boy),
            OlculuStokTakipTuru::Hacim => $fmt($olcu->en).' × '.$fmt($olcu->boy).' × '.$fmt($olcu->yukseklik),
            OlculuStokTakipTuru::Agirlik => $fmt($olcu->bir_adet_agirlik).' '.($olcu->agirlik_birimi ?: ''),
            default => '',
        };

        return trim($deger.' '.$birim);
    }

    public static function table(Table $table): Table
    {
        $stokBarkodTablosuVar = self::stokBarkodTablosuVarMi();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($stokBarkodTablosuVar): Builder {
                $query
                    ->select([
                    'id',
                    'firma_id',
                    'ad',
                    'kod',
                    'tur',
                    'kategori_id',
                    'birim',
                    'satis_fiyati',
                    'para_birimi',
                    'kritik_seviye_miktar',
                    'stok_miktari',
                    'olculu_takip_turu',
                    'olcu_yapisi',
                    'parcali_kullanima_izin',
                    'minimum_stok',
                    'durum',
                    'barkod',
                    'created_at',
                    'updated_at',
                    ])
                    ->selectSub("select coalesce(sum(sob.ana_miktar), 0) from stok_olcu_bakiyeleri sob where sob.stok_id = stok_kartlari.id and sob.firma_id = stok_kartlari.firma_id", 'olcu_ana_toplami')
                    ->selectSub("select coalesce(sum(sob.adet_esdegeri), 0) from stok_olcu_bakiyeleri sob where sob.stok_id = stok_kartlari.id and sob.firma_id = stok_kartlari.firma_id", 'olcu_adet_toplami')
                    ->with([
                        'kategori:id,firma_id,ad',
                        'stokOlculeri:id,stok_id,takip_turu,olcu_birimi,en,boy,yukseklik,bir_adet_agirlik,agirlik_birimi,bir_adet_ana_miktar,aktif_mi',
                    ]);

                // Görsel sütunu kapalı olsa bile accessor'ın her satırda ayrı
                // sorgu açmasını engelle; tablo yoksa eski uyumluluk davranışı
                // korunur.
                if (self::stokKartiGorselleriTablosuVarMi()) {
                    $query->with('gorseller:id,stok_karti_id,dosya_yolu,aktif_mi,kapak_mi,sira');
                }

                return $query;
            })
            ->columns([
                Tables\Columns\ViewColumn::make('gorsel')
                    ->label('Görsel')
                    ->view('filament.muhasebe.columns.stok-gorsel')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kod')
                    ->label('Kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ad')
                    ->label('Ad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tur')
                    ->label('Tür')
                    ->formatStateUsing(fn (?StokKartiTuru $state) => $state?->etiket() ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kategori.ad')
                    ->label('Kategori')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('satis_fiyati')
                    ->label('Satış fiyatı')
                    ->money(fn (StokKarti $kayit) => $kayit->para_birimi ?: 'TRY')
                    ->sortable(),
                Tables\Columns\TextColumn::make('birim')
                    ->label('Birim')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kritik_seviye_miktar')
                    ->label('Kritik seviye')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stok_miktari')
                    ->label('Mevcut miktar')
                    ->formatStateUsing(function ($state, StokKarti $kayit): string {
                        $deger = $kayit->olculu_takip_turu instanceof OlculuStokTakipTuru
                            && $kayit->olculu_takip_turu->olculuMu()
                            ? $kayit->olcu_adet_toplami
                            : $state;

                        return str_replace('.', ',', rtrim(rtrim(bcadd((string) ($deger ?? '0'), '0', 8), '0'), '.'));
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('olculu_takip_turu')
                    ->label('Stok takip yöntemi')
                    ->formatStateUsing(fn (?OlculuStokTakipTuru $state): string => match ($state) {
                        OlculuStokTakipTuru::Uzunluk => 'Uzunluk + Adet',
                        OlculuStokTakipTuru::Alan => 'Alan (m²) + Adet',
                        OlculuStokTakipTuru::Hacim => 'Hacim (m³) + Adet',
                        OlculuStokTakipTuru::Agirlik => 'Ağırlık + Adet',
                        default => 'Standart',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('sabit_olcu')
                    ->label('Sabit ölçü')
                    ->state(fn (StokKarti $kayit): string => static::sabitOlcuEtiketi($kayit))
                    ->color(fn (string $state): string => $state === '-' ? 'gray' : 'info')
                    ->tooltip(fn (StokKarti $kayit): string => static::sabitOlcuEtiketi($kayit))
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('parcali_kullanima_izin')
                    ->label('Parçalı kullanım')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (StokKarti $kayit): string => (bool) $kayit->parcali_kullanima_izin
                        ? 'Parçalı kullanıma izin veriliyor.'
                        : 'Parçalı kullanıma izin verilmiyor.'),
                Tables\Columns\TextColumn::make('olcu_ana_toplami')
                    ->label('Toplam ölçü')
                    ->formatStateUsing(function ($state, StokKarti $kayit): string {
                        if (! ($kayit->olculu_takip_turu instanceof OlculuStokTakipTuru && $kayit->olculu_takip_turu->olculuMu())) {
                            return '-';
                        }

                        $deger = str_replace('.', ',', rtrim(rtrim(bcadd((string) ($state ?? '0'), '0', 8), '0'), '.'));
                        $birim = match ($kayit->olculu_takip_turu) {
                            OlculuStokTakipTuru::Uzunluk => 'm',
                            OlculuStokTakipTuru::Alan => 'm²',
                            OlculuStokTakipTuru::Hacim => 'm³',
                            OlculuStokTakipTuru::Agirlik => 'kg',
                            default => '',
                        };

                        return trim($deger.' '.$birim);
                    }),
                Tables\Columns\TextColumn::make('minimum_stok')
                    ->label('Minimum stok')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('durum')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (?HesapDurumu $state) => match ($state) {
                        HesapDurumu::Aktif => 'Aktif',
                        HesapDurumu::Pasif => 'Pasif',
                        default => '-',
                    })
                    ->color(fn (?HesapDurumu $state) => match ($state) {
                        HesapDurumu::Aktif => 'success',
                        HesapDurumu::Pasif => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('barkod')
                    ->label('Barkod')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                ...($stokBarkodTablosuVar ? [
                    Tables\Columns\TextColumn::make('barkodlar_count')
                        ->label('Alt Barkod')
                        ->counts('barkodlar')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ] : []),
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
                    ->options(collect(StokKartiTuru::cases())->mapWithKeys(fn (StokKartiTuru $e) => [$e->value => $e->etiket()]))
                    ->placeholder('Tümü'),
                Tables\Filters\SelectFilter::make('durum')
                    ->label('Durum')
                    ->options([
                        HesapDurumu::Aktif->value => 'Aktif',
                        HesapDurumu::Pasif->value => 'Pasif',
                    ])
                    ->placeholder('Tümü'),
                Tables\Filters\SelectFilter::make('olculu_takip_turu')
                    ->label('Takip yöntemi')
                    ->options([
                        'standart' => 'Standart', 'uzunluk' => 'Uzunluk', 'alan' => 'Alan', 'hacim' => 'Hacim', 'agirlik' => 'Ağırlık',
                    ])
                    ->placeholder('Tümü'),
                Tables\Filters\SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'ad')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Sil')
                    ->visible(fn (StokKarti $record): bool => ! DB::table('stok_hareketleri')->where('stok_id', (int) $record->getKey())->exists())
                    ->tooltip(fn (StokKarti $record): ?string => DB::table('stok_hareketleri')->where('stok_id', (int) $record->getKey())->exists()
                        ? 'Stok hareketi bulunduğu için silinemez; pasife alabilirsiniz.'
                        : null),
                Tables\Actions\Action::make('durumDegistir')
                    ->label(fn (StokKarti $record): string => $record->durum === HesapDurumu::Aktif ? 'Pasifleştir' : 'Aktifleştir')
                    ->icon(fn (StokKarti $record): string => $record->durum === HesapDurumu::Aktif ? 'heroicon-o-archive-box' : 'heroicon-o-arrow-path')
                    ->color(fn (StokKarti $record): string => $record->durum === HesapDurumu::Aktif ? 'warning' : 'success')
                    ->action(fn (StokKarti $record): bool => (bool) $record->update([
                        'durum' => $record->durum === HesapDurumu::Aktif ? HesapDurumu::Pasif : HesapDurumu::Aktif,
                    ])),
            ])
            ->bulkActions([])
            ->paginated([10, 20, 50, 100, 1000, 'all']);
    }


    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::isWebUrunContext()) {
            $query->where('tur', StokKartiTuru::ETicaret->value);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokKartlari::route('/'),
            'create' => Pages\CreateStokKarti::route('/create'),
            'view' => Pages\ViewStokKarti::route('/{record}'),
            'edit' => Pages\EditStokKarti::route('/{record}/edit'),
        ];
    }
}





