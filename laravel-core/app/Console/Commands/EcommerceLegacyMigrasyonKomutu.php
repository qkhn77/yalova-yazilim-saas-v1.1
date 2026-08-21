<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Ecommerce\Siparis;
use App\Muhasebe\Enumlar\StokKartiTuru;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EcommerceLegacyMigrasyonKomutu extends Command
{
    protected $signature = 'ecommerce:migrate-legacy
        {--firma_id= : Hedef firma id (zorunlu)}
        {--legacy-orders-table=orders : Legacy siparis tablosu (opsiyonel)}
        {--apply : Dry-run yerine veritabani yazimi yap}';

    protected $description = 'Faz-1 legacy urun/kategori/siparis ozet migrasyonu (varsayilan dry-run).';

    public function handle(): int
    {
        $firmaId = (int) $this->option('firma_id');
        if ($firmaId <= 0) {
            $this->error('--firma_id zorunludur ve 0\'dan buyuk olmalidir.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;
        $legacyOrdersTable = trim((string) $this->option('legacy-orders-table'));

        $this->info(sprintf(
            'E-ticaret legacy migrasyon basladi | firma_id=%d | mod=%s',
            $firmaId,
            $dryRun ? 'dry-run' : 'apply'
        ));

        $rapor = [
            'kategori' => ['toplam' => 0, 'yeni' => 0, 'atlanan' => 0, 'hata' => 0, 'parent_guncelleme' => 0],
            'urun' => ['toplam' => 0, 'yeni' => 0, 'atlanan' => 0, 'hata' => 0],
            'siparis_ozet' => ['toplam' => 0, 'yeni' => 0, 'atlanan' => 0, 'hata' => 0, 'kaynak_var' => false],
        ];

        $runner = function () use ($firmaId, $dryRun, $legacyOrdersTable, &$rapor): void {
            $kategoriMap = $this->kategorileriTasima($firmaId, $dryRun, $rapor['kategori']);
            $this->urunleriTasima($firmaId, $dryRun, $kategoriMap, $rapor['urun']);
            $this->siparisOzetTasima($firmaId, $dryRun, $legacyOrdersTable, $rapor['siparis_ozet']);
        };

        if ($dryRun) {
            $runner();
        } else {
            DB::transaction($runner);
        }

        $this->newLine();
        $this->line('--- Migrasyon Ozeti ---');
        $this->line(sprintf(
            'Kategori | toplam:%d yeni:%d atlanan:%d parent_guncelleme:%d hata:%d',
            $rapor['kategori']['toplam'],
            $rapor['kategori']['yeni'],
            $rapor['kategori']['atlanan'],
            $rapor['kategori']['parent_guncelleme'],
            $rapor['kategori']['hata'],
        ));
        $this->line(sprintf(
            'Urun     | toplam:%d yeni:%d atlanan:%d hata:%d',
            $rapor['urun']['toplam'],
            $rapor['urun']['yeni'],
            $rapor['urun']['atlanan'],
            $rapor['urun']['hata'],
        ));
        $this->line(sprintf(
            'Siparis  | kaynak:%s toplam:%d yeni:%d atlanan:%d hata:%d',
            $rapor['siparis_ozet']['kaynak_var'] ? 'var' : 'yok',
            $rapor['siparis_ozet']['toplam'],
            $rapor['siparis_ozet']['yeni'],
            $rapor['siparis_ozet']['atlanan'],
            $rapor['siparis_ozet']['hata'],
        ));
        $this->newLine();
        $this->info('Not: Canli gecis oncesi dry-run raporu + freeze window + rollback plani zorunlu tutulmalidir.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string,int>  $rapor
     * @return array<int,int> legacy_category_id => stok_kategori_id
     */
    private function kategorileriTasima(int $firmaId, bool $dryRun, array &$rapor): array
    {
        $map = [];
        $legacyKategoriler = ProductCategory::query()->orderBy('id')->get();
        $rapor['toplam'] = $legacyKategoriler->count();

        foreach ($legacyKategoriler as $kategori) {
            try {
                $legacyId = (string) $kategori->id;
                $mevcut = DB::table('stok_kategorileri')
                    ->where('firma_id', $firmaId)
                    ->where('legacy_id', $legacyId)
                    ->first();

                if ($mevcut) {
                    $rapor['atlanan']++;
                    $map[(int) $kategori->id] = (int) $mevcut->id;
                    continue;
                }

                $kod = $this->benzersizKategoriKodu($firmaId, 'LEG-CAT-'.(int) $kategori->id);
                $satir = [
                    'firma_id' => $firmaId,
                    'parent_id' => null,
                    'kod' => $kod,
                    'ad' => Str::limit((string) $kategori->name, 128, ''),
                    'slug' => Str::slug((string) $kategori->slug ?: (string) $kategori->name),
                    'legacy_id' => $legacyId,
                    'aciklama' => $kategori->description,
                    'aktif_mi' => (bool) $kategori->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($dryRun) {
                    $rapor['yeni']++;
                    continue;
                }

                $id = (int) DB::table('stok_kategorileri')->insertGetId($satir);
                $map[(int) $kategori->id] = $id;
                $rapor['yeni']++;
            } catch (\Throwable) {
                $rapor['hata']++;
            }
        }

        // Parent hiyerarsisi baglama (second pass)
        foreach ($legacyKategoriler as $kategori) {
            $parentLegacyId = (int) ($kategori->parent_id ?? 0);
            if ($parentLegacyId <= 0) {
                continue;
            }

            $mevcutId = $map[(int) $kategori->id] ?? (int) (DB::table('stok_kategorileri')
                ->where('firma_id', $firmaId)
                ->where('legacy_id', (string) $kategori->id)
                ->value('id') ?? 0);
            $parentId = $map[$parentLegacyId] ?? (int) (DB::table('stok_kategorileri')
                ->where('firma_id', $firmaId)
                ->where('legacy_id', (string) $parentLegacyId)
                ->value('id') ?? 0);

            if ($mevcutId <= 0 || $parentId <= 0 || $mevcutId === $parentId) {
                continue;
            }

            if ($dryRun) {
                $rapor['parent_guncelleme']++;
                continue;
            }

            DB::table('stok_kategorileri')
                ->where('id', $mevcutId)
                ->where('firma_id', $firmaId)
                ->update(['parent_id' => $parentId, 'updated_at' => now()]);
            $rapor['parent_guncelleme']++;
        }

        return $map;
    }

    /**
     * @param  array<int,int>  $kategoriMap
     * @param  array<string,int>  $rapor
     */
    private function urunleriTasima(int $firmaId, bool $dryRun, array $kategoriMap, array &$rapor): void
    {
        $legacyUrunler = Product::query()->orderBy('id')->get();
        $rapor['toplam'] = $legacyUrunler->count();

        foreach ($legacyUrunler as $urun) {
            try {
                $legacyId = (string) $urun->id;
                $mevcut = DB::table('stok_kartlari')
                    ->where('firma_id', $firmaId)
                    ->where('legacy_id', $legacyId)
                    ->exists();

                if ($mevcut) {
                    $rapor['atlanan']++;
                    continue;
                }

                $kategoriId = null;
                $kategoriKod = null;
                $legacyKategoriId = (int) ($urun->category_id ?? 0);
                if ($legacyKategoriId > 0) {
                    $kategoriId = $kategoriMap[$legacyKategoriId]
                        ?? DB::table('stok_kategorileri')
                            ->where('firma_id', $firmaId)
                            ->where('legacy_id', (string) $legacyKategoriId)
                            ->value('id');
                    if ($kategoriId) {
                        $kategoriKod = DB::table('stok_kategorileri')->where('id', $kategoriId)->value('kod');
                    }
                }

                $kod = $this->benzersizStokKodu($firmaId, (string) ($urun->sku ?: 'LEG-PRD-'.(int) $urun->id));
                $stokMiktari = $urun->stock_status === Product::STOCK_OUT_OF_STOCK ? 0 : 1;
                $satisFiyati = (float) ($urun->discounted_price ?? 0) > 0
                    ? (float) $urun->discounted_price
                    : (float) ($urun->price ?? 0);

                $satir = [
                    'firma_id' => $firmaId,
                    'kod' => Str::limit($kod, 64, ''),
                    'ad' => Str::limit((string) $urun->name, 255, ''),
                    'kisa_ad' => Str::limit((string) ($urun->short_description ?? ''), 128, ''),
                    'slug' => Str::slug((string) ($urun->slug ?: $urun->name ?: 'urun')),
                    'legacy_id' => $legacyId,
                    'barkod' => null,
                    'tur' => StokKartiTuru::ETicaret->value,
                    'kategori_kodu' => $kategoriKod,
                    'kategori_id' => $kategoriId,
                    'birim' => 'AD',
                    'alis_fiyati' => 0,
                    'satis_fiyati' => $satisFiyati,
                    'indirimli_fiyat' => (float) ($urun->discounted_price ?? 0),
                    'para_birimi' => 'TRY',
                    'kdv_orani' => 20,
                    'kritik_seviye_miktar' => 2,
                    'aciklama' => $urun->description,
                    'durum' => (bool) $urun->is_active ? 'aktif' : 'pasif',
                    'stok_takip' => true,
                    'stok_miktari' => $stokMiktari,
                    'minimum_stok' => 0,
                    'maksimum_stok' => 0,
                    'marka_uretici' => Str::limit((string) ($urun->brand ?? ''), 120, ''),
                    'seo_title' => $urun->seo_title,
                    'seo_description' => $urun->seo_description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($dryRun) {
                    $rapor['yeni']++;
                    continue;
                }

                DB::table('stok_kartlari')->insert($satir);
                $rapor['yeni']++;
            } catch (\Throwable) {
                $rapor['hata']++;
            }
        }
    }

    /**
     * @param  array<string,int|bool>  $rapor
     */
    private function siparisOzetTasima(int $firmaId, bool $dryRun, string $legacyOrdersTable, array &$rapor): void
    {
        if ($legacyOrdersTable === '' || ! Schema::hasTable($legacyOrdersTable)) {
            $rapor['kaynak_var'] = false;
            return;
        }

        $rapor['kaynak_var'] = true;
        $legacySiparisler = DB::table($legacyOrdersTable)->orderBy('id')->get();
        $rapor['toplam'] = $legacySiparisler->count();

        foreach ($legacySiparisler as $legacy) {
            try {
                $legacyId = (string) ($legacy->id ?? '');
                if ($legacyId === '') {
                    $rapor['hata']++;
                    continue;
                }

                $mevcut = Siparis::query()
                    ->where('firma_id', $firmaId)
                    ->where('legacy_id', $legacyId)
                    ->exists();
                if ($mevcut) {
                    $rapor['atlanan']++;
                    continue;
                }

                $siparisNo = $this->benzersizSiparisNo($firmaId, 'LEG-'.str_pad($legacyId, 8, '0', STR_PAD_LEFT));
                $durum = $this->legacySiparisDurumunuEsle((string) ($legacy->status ?? ''));
                $genelToplam = (float) ($legacy->total_amount ?? 0);

                if ($dryRun) {
                    $rapor['yeni']++;
                    continue;
                }

                Siparis::query()->create([
                    'siparis_no' => $siparisNo,
                    'legacy_id' => $legacyId,
                    'firma_id' => $firmaId,
                    'kullanici_id' => null,
                    'musteri_ad_soyad' => Str::limit((string) ($legacy->customer_name ?? 'Legacy Musteri'), 255, ''),
                    'musteri_email' => $legacy->customer_email ?? null,
                    'musteri_telefon' => (string) ($legacy->customer_phone ?? ''),
                    'teslimat_adresi' => (string) ($legacy->shipping_address ?? 'Legacy siparis adresi'),
                    'notlar' => 'Legacy siparis ozet aktarimi',
                    'para_birimi' => Str::upper((string) ($legacy->currency ?? 'TRY')),
                    'ara_toplam' => $genelToplam,
                    'kdv_toplam' => 0,
                    'indirim_toplami' => 0,
                    'genel_toplam' => $genelToplam,
                    'durum' => $durum,
                ]);
                $rapor['yeni']++;
            } catch (\Throwable) {
                $rapor['hata']++;
            }
        }
    }

    private function legacySiparisDurumunuEsle(string $legacyDurum): string
    {
        return match (strtolower(trim($legacyDurum))) {
            'pending', 'new' => Siparis::DURUM_ONAY_BEKLIYOR,
            'paid', 'confirmed' => Siparis::DURUM_ONAYLANDI_YENI,
            'shipped', 'in_transit' => Siparis::DURUM_GONDERILDI,
            'delivered', 'completed' => Siparis::DURUM_TESLIM_EDILDI,
            'cancelled', 'canceled' => Siparis::DURUM_IPTAL_EDILDI,
            'refund_requested' => Siparis::DURUM_IADE_TALEBI,
            'refunded' => Siparis::DURUM_IADE_EDILDI,
            default => Siparis::DURUM_DETAY_BEKLEYEN,
        };
    }

    private function benzersizKategoriKodu(int $firmaId, string $baz): string
    {
        $kod = Str::upper(Str::slug($baz, '-'));
        $i = 1;
        while (DB::table('stok_kategorileri')->where('firma_id', $firmaId)->where('kod', $kod)->exists()) {
            $kod = Str::limit(Str::upper(Str::slug($baz, '-')), 58, '').'-'.$i;
            $i++;
        }

        return Str::limit($kod, 64, '');
    }

    private function benzersizStokKodu(int $firmaId, string $baz): string
    {
        $kod = Str::upper(Str::replace(' ', '-', trim($baz)));
        if ($kod === '') {
            $kod = 'LEG-PRD';
        }
        $i = 1;
        while (DB::table('stok_kartlari')->where('firma_id', $firmaId)->where('kod', $kod)->exists()) {
            $kod = Str::limit(Str::upper(Str::replace(' ', '-', trim($baz))), 58, '').'-'.$i;
            if ($kod === '-'.$i) {
                $kod = 'LEG-PRD-'.$i;
            }
            $i++;
        }

        return Str::limit($kod, 64, '');
    }

    private function benzersizSiparisNo(int $firmaId, string $baz): string
    {
        $no = Str::upper(Str::replace(' ', '-', trim($baz)));
        if ($no === '') {
            $no = 'LEG-ORDER';
        }
        $i = 1;
        while (Siparis::query()->where('firma_id', $firmaId)->where('siparis_no', $no)->exists()) {
            $no = Str::limit($baz, 24, '').'-'.$i;
            $i++;
        }

        return Str::limit($no, 32, '');
    }
}

