<?php

namespace App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\StokKartiKaynagi;
use App\Models\Muhasebe\MuhasebeMarka;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcusu;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditStokKarti extends EditRecord
{
    protected static string $resource = StokKartiKaynagi::class;

    protected static ?string $title = 'Stok düzenle';

    public function getTitle(): string|Htmlable
    {
        return static::getResource()::isWebUrunContext() ? 'Ürün düzenle' : 'Stok düzenle';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ölçü değerleri kart tablosunda değil, stok_olculeri alt kaydında
        // tutulur. Sabit ölçülü kart düzenlenirken ilk aktif ölçüyü forma taşır.
        $olcu = StokOlcusu::withoutGlobalScopes()
            ->where('stok_id', (int) $this->record->getKey())
            ->where('aktif_mi', true)
            ->orderBy('id')
            ->first();

        if ($olcu) {
            $data['olcu_giris_birimi'] = $olcu->olcu_birimi;
            $data['olcu_en'] = $olcu->en;
            $data['olcu_boy'] = $olcu->boy;
            $data['olcu_yukseklik'] = $olcu->yukseklik;
            $data['olcu_bir_adet_agirlik'] = $olcu->bir_adet_agirlik;
            $data['agirlik_birimi'] = $olcu->agirlik_birimi;
        }

        $firmaId = (int) ($data['firma_id'] ?? $this->record->firma_id ?? 0);
        $markaId = (int) ($data['marka_id'] ?? 0);
        $markaUretici = trim((string) ($data['marka_uretici'] ?? ''));

        if ($markaId < 1 && $firmaId > 0 && $markaUretici !== '') {
            $bulunanMarkaId = MuhasebeMarka::query()
                ->gorunurFirmaIle($firmaId)
                ->whereRaw('LOWER(ad) = ?', [mb_strtolower($markaUretici)])
                ->value('id');

            if (! $bulunanMarkaId) {
                $kod = $this->markaKoduUret($firmaId, $markaUretici);

                $kayit = MuhasebeMarka::query()->create([
                    'firma_id' => $firmaId,
                    'kod' => $kod,
                    'ad' => $markaUretici,
                    'aktif_mi' => true,
                    'is_sabit' => false,
                ]);

                $bulunanMarkaId = (int) $kayit->getKey();
            }

            if ($bulunanMarkaId) {
                $data['marka_id'] = (int) $bulunanMarkaId;
            }
        }

        return $data;
    }

    private function markaKoduUret(int $firmaId, string $markaAdi): string
    {
        $temiz = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($markaAdi)));
        $temel = trim($temiz) !== '' ? Str::substr($temiz, 0, 18) : 'MARKA';
        $kod = 'MRK-'.$temel;
        $sira = 1;

        while (MuhasebeMarka::tenantScopeOlmadan(fn () => MuhasebeMarka::query()
            ->where('tanim_firma_kapsami', $firmaId)
            ->whereRaw('UPPER(kod) = ?', [$kod])
            ->exists())) {
            $kod = 'MRK-'.$temel.'-'.$sira;
            $sira++;
        }

        return $kod;
    }

    protected function getHeaderActions(): array
    {
        $resource = static::getResource();
        $recordKey = (int) $this->record->getKey();
        $detayModu = $resource::detayModu();
        $hizliDuzenleme = $resource::hizliDuzenlemeModu();

        $detayParametreleri = $resource::isWebUrunContext()
            ? ['detay' => 1, 'barkod_detay' => 1, 'e_ticaret_detay' => 1]
            : ['detay' => 1, 'barkod_detay' => 1];

        $anaUrl = $resource::getUrl('edit', ['record' => $recordKey]);
        $detayUrl = $anaUrl.'?'.http_build_query($detayParametreleri);

        if ($hizliDuzenleme) {
            return [
                Actions\Action::make('tamDuzenle')
                    ->label('Tüm alanları aç')
                    ->icon('heroicon-o-document-text')
                    ->url($anaUrl)
                    ->color('gray'),
            ];
        }

        if (! $detayModu) {
            return [
                Actions\Action::make('hizliDuzenle')
                    ->label('Hızlı düzenle')
                    ->icon('heroicon-o-bolt')
                    ->url($anaUrl.'?hizli=1')
                    ->color('gray'),
                Actions\Action::make('detayliDuzenle')
                    ->label('Detayları aç')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->url($detayUrl)
                    ->color('gray'),
            ];
        }

        return [
            ...parent::getHeaderActions(),
            Actions\Action::make('hizliDuzenle')
                ->label('Hızlı düzenle')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url($anaUrl.'?hizli=1'),
        ];
    }

    protected function getFormActions(): array
    {
        if (static::getResource()::detayModu()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $kullanici = Auth::user();
        $super = $kullanici && StokKarti::kullaniciSuperAdminMi($kullanici);

        if (! $super) {
            $aktif = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
            if ($aktif < 1 || (int) $this->record->firma_id !== $aktif) {
                abort(403);
            }
            $data['firma_id'] = $aktif;
        } else {
            $data['firma_id'] = (int) ($data['firma_id'] ?? $this->record->firma_id);
            if ($data['firma_id'] < 1) {
                throw ValidationException::withMessages(['firma_id' => 'Firma geçersiz.']);
            }
        }

        if (! static::getResource()::detayModu()) {
            $alanlar = [
                'firma_id',
                'kod',
                'sku',
                'upc',
                'ean',
                'gtin',
                'mpn',
                'amazon_asin',
                'fba_kodu',
                'ad',
                'kisa_ad',
                'slug',
                'barkod',
                'seri_no',
                'imei_no',
                'tur',
                'kategori_kodu',
                'kategori_id',
                'birim',
                'alis_fiyati',
                'satis_fiyati',
                'indirimli_fiyat',
                'para_birimi',
                'kdv_orani',
                'gumruk_orani',
                'kritik_seviye_miktar',
                'aciklama',
                'durum',
                'stok_takip',
                'minimum_stok',
                'maksimum_stok',
                'stok_miktari',
                'rezerve_miktar',
                'depo_id',
                'marka_id',
                'marka_uretici',
                'model_id',
                'tasarim_id',
                'malzeme_turu_id',
                'logo_turu_id',
                'varyant_id',
                'tedarikci_id',
                'agirlik',
                'hacim',
                'kargo_sinifi',
                'satis_adedi',
                'goruntulenme_sayisi',
                'seo_title',
                'seo_description',
                'seo_keywords',
                'og_gorsel',
                'og_baslik',
                'og_aciklama',
                'og_etiket',
                'guncel_birim_maliyet',
                'stok_degeri',
                'son_giris_maliyeti',
                'son_hareket_tarihi',
                'negative_flag',
            ];

            $mevcut = StokKarti::query()
                ->whereKey($this->record->getKey())
                ->first($alanlar);

            if ($mevcut) {
                $mevcutVeri = array_intersect_key($mevcut->getAttributes(), array_flip($alanlar));
                $data = array_replace($mevcutVeri, $data);
            }
        }

        $kod = trim((string) ($data['kod'] ?? $this->record->kod ?? ''));
        if ($kod === '') {
            throw ValidationException::withMessages(['kod' => 'Kod zorunludur.']);
        }
        $data['kod'] = $kod;
        if (! StokKartiKaynagi::kodBenzersizMi((int) $data['firma_id'], $kod, (int) $this->record->getKey())) {
            throw ValidationException::withMessages(['kod' => 'Bu kod bu firma için zaten kullanılıyor.']);
        }

        $barkod = trim((string) ($data['barkod'] ?? ''));
        if ($barkod !== '') {
            $varMi = StokKarti::query()
                ->where('firma_id', (int) $data['firma_id'])
                ->whereRaw('UPPER(barkod) = ?', [strtoupper($barkod)])
                ->whereKeyNot((int) $this->record->getKey())
                ->exists();

            if ($varMi) {
                throw ValidationException::withMessages(['barkod' => 'Bu barkod bu firmada zaten kullanılıyor.']);
            }
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = Str::slug((string) ($data['ad'] ?? ''));
        }
        $data['slug'] = $slug !== '' ? $slug : null;
        if ($data['slug']) {
            $slugVarMi = StokKarti::query()
                ->where('firma_id', (int) $data['firma_id'])
                ->where('slug', $data['slug'])
                ->whereKeyNot((int) $this->record->getKey())
                ->exists();

            if ($slugVarMi) {
                throw ValidationException::withMessages(['slug' => 'Bu slug bu firmada zaten kullanılıyor.']);
            }
        }

        $kategori = StokKartiKaynagi::kategoriDegerleriniHazirla((int) $data['firma_id'], (int) ($data['kategori_id'] ?? 0));
        $data['kategori_id'] = $kategori['kategori_id'];
        $data['kategori_kodu'] = $kategori['kategori_kodu'];
        $data = StokKartiKaynagi::depoAlanlariniDogrula($data, (int) $data['firma_id']);

        if (array_key_exists('marka_id', $data) || array_key_exists('marka_uretici', $data)) {
            $markaId = (int) ($data['marka_id'] ?? 0);
            if ($markaId > 0) {
                $markaAdi = MuhasebeMarka::query()
                    ->gorunurFirmaIle((int) $data['firma_id'])
                    ->whereKey($markaId)
                    ->value('ad');

                if (is_string($markaAdi) && trim($markaAdi) !== '') {
                    $data['marka_uretici'] = trim($markaAdi);
                }
            } else {
                $data['marka_uretici'] = trim((string) ($data['marka_uretici'] ?? '')) ?: null;
            }
        }

        if (static::getResource()::isWebUrunContext()) {
            $data['tur'] = \App\Muhasebe\Enumlar\StokKartiTuru::ETicaret->value;
        }

        // Parti alanları stok kartında değil, stok hareketi sırasında tutulur.
        unset($data['parca_kodu'], $data['uretim_tarihi'], $data['son_kullanma_tarihi'], $data['seri_nolari'], $data['garanti_baslangic_tarihi'], $data['garanti_bitis_tarihi']);

        return $data;
    }

    protected function afterSave(): void
    {
        Log::channel((string) config('muhasebe.stok.log_channel', 'muhasebe'))->info('stok_karti.guncelle', [
            'stok_id' => (int) $this->record->getKey(),
            'firma_id' => (int) $this->record->firma_id,
            'degisen_alanlar' => array_keys($this->record->getChanges()),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
