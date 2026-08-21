<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->stokKartlariSlugAlaniniHardenEt();
        $this->stokKategorileriSlugAlaniniEkle();
    }

    public function down(): void
    {
        if (Schema::hasTable('stok_kartlari')) {
            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('stok_kartlari', function (Blueprint $table): void {
                    if (Schema::hasColumn('stok_kartlari', 'slug')) {
                        $table->string('slug', 255)->nullable()->change();
                    }
                });
            }
        }

        if (Schema::hasTable('stok_kategorileri')) {
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                if (Schema::hasColumn('stok_kategorileri', 'slug')) {
                    if ($this->indexVarMi('stok_kategorileri', 'stok_kategorileri_tanim_firma_slug_unique')) {
                        $table->dropUnique('stok_kategorileri_tanim_firma_slug_unique');
                    }
                    $table->dropColumn('slug');
                }
            });
        }
    }

    private function stokKartlariSlugAlaniniHardenEt(): void
    {
        if (! Schema::hasTable('stok_kartlari') || ! Schema::hasColumn('stok_kartlari', 'slug')) {
            return;
        }

        $stoklar = DB::table('stok_kartlari')
            ->select(['id', 'firma_id', 'ad', 'slug'])
            ->orderBy('id')
            ->get();

        foreach ($stoklar as $stok) {
            $slug = trim((string) ($stok->slug ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) ($stok->ad ?? ''));
            }
            if ($slug === '') {
                $slug = 'urun';
            }

            $base = $slug;
            $index = 1;
            while ($this->stokSlugCakisiyor((int) $stok->id, (int) $stok->firma_id, $slug)) {
                $slug = $base.'-'.$index;
                $index++;
            }

            DB::table('stok_kartlari')
                ->where('id', $stok->id)
                ->update(['slug' => $slug]);
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('stok_kartlari', function (Blueprint $table): void {
                $table->string('slug', 255)->nullable(false)->change();
            });
        }
    }

    private function stokKategorileriSlugAlaniniEkle(): void
    {
        if (! Schema::hasTable('stok_kategorileri')) {
            return;
        }

        if (! Schema::hasColumn('stok_kategorileri', 'slug')) {
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->string('slug', 255)->nullable()->after('ad');
            });
        }

        $kategoriler = DB::table('stok_kategorileri')
            ->select(['id', 'tanim_firma_kapsami', 'ad', 'kod', 'slug'])
            ->orderBy('id')
            ->get();

        foreach ($kategoriler as $kategori) {
            $slug = trim((string) ($kategori->slug ?? ''));
            if ($slug === '') {
                $slug = Str::slug((string) ($kategori->ad ?? ''));
            }
            if ($slug === '') {
                $slug = Str::slug((string) ($kategori->kod ?? ''));
            }
            if ($slug === '') {
                $slug = 'kategori';
            }

            $base = $slug;
            $index = 1;
            while ($this->kategoriSlugCakisiyor((int) $kategori->id, (int) $kategori->tanim_firma_kapsami, $slug)) {
                $slug = $base.'-'.$index;
                $index++;
            }

            DB::table('stok_kategorileri')
                ->where('id', $kategori->id)
                ->update(['slug' => $slug]);
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->string('slug', 255)->nullable(false)->change();
            });
        }

        if (! $this->indexVarMi('stok_kategorileri', 'stok_kategorileri_tanim_firma_slug_unique')) {
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->unique(['tanim_firma_kapsami', 'slug'], 'stok_kategorileri_tanim_firma_slug_unique');
            });
        }
    }

    private function stokSlugCakisiyor(int $id, int $firmaId, string $slug): bool
    {
        return DB::table('stok_kartlari')
            ->where('id', '!=', $id)
            ->where('firma_id', $firmaId)
            ->where('slug', $slug)
            ->exists();
    }

    private function kategoriSlugCakisiyor(int $id, int $firmaKapsami, string $slug): bool
    {
        return DB::table('stok_kategorileri')
            ->where('id', '!=', $id)
            ->where('tanim_firma_kapsami', $firmaKapsami)
            ->where('slug', $slug)
            ->exists();
    }

    private function indexVarMi(string $tablo, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$tablo}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select('SHOW INDEX FROM '.$tablo.' WHERE Key_name = ?', [$index]);

        return ! empty($rows);
    }
};
