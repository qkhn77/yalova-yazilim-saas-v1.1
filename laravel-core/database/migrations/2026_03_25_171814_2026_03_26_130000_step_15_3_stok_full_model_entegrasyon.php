<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('stok_kartlari', 'seri_no')) {
                $table->string('seri_no', 128)->nullable()->after('barkod');
            }
            if (! Schema::hasColumn('stok_kartlari', 'imei_no')) {
                $table->string('imei_no', 128)->nullable()->after('seri_no');
            }

            if (! Schema::hasColumn('stok_kartlari', 'maksimum_stok')) {
                $table->decimal('maksimum_stok', 18, 4)->nullable()->after('minimum_stok');
            }
            if (! Schema::hasColumn('stok_kartlari', 'depo_id')) {
                $table->unsignedBigInteger('depo_id')->nullable()->after('maksimum_stok');
            }

            if (! Schema::hasColumn('stok_kartlari', 'indirimli_fiyat')) {
                $table->decimal('indirimli_fiyat', 18, 2)->nullable()->after('satis_fiyati');
            }
            if (! Schema::hasColumn('stok_kartlari', 'gumruk_orani')) {
                $table->decimal('gumruk_orani', 6, 2)->nullable()->after('kdv_orani');
            }

            if (! Schema::hasColumn('stok_kartlari', 'slug')) {
                $table->string('slug', 255)->nullable()->after('ad');
            }
            if (! Schema::hasColumn('stok_kartlari', 'gorsel')) {
                $table->string('gorsel', 255)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('stok_kartlari', 'galeri')) {
                $table->json('galeri')->nullable()->after('gorsel');
            }

            if (! Schema::hasColumn('stok_kartlari', 'seo_title')) {
                $table->string('seo_title', 255)->nullable()->after('galeri');
            }
            if (! Schema::hasColumn('stok_kartlari', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
            if (! Schema::hasColumn('stok_kartlari', 'seo_keywords')) {
                $table->string('seo_keywords', 255)->nullable()->after('seo_description');
            }

            if (! Schema::hasColumn('stok_kartlari', 'og_gorsel')) {
                $table->string('og_gorsel', 255)->nullable()->after('seo_keywords');
            }
            if (! Schema::hasColumn('stok_kartlari', 'og_baslik')) {
                $table->string('og_baslik', 255)->nullable()->after('og_gorsel');
            }
            if (! Schema::hasColumn('stok_kartlari', 'og_aciklama')) {
                $table->text('og_aciklama')->nullable()->after('og_baslik');
            }
            if (! Schema::hasColumn('stok_kartlari', 'og_etiket')) {
                $table->string('og_etiket', 255)->nullable()->after('og_aciklama');
            }

            // Ürün geniş alanları (tanımlarla FK)
            if (! Schema::hasColumn('stok_kartlari', 'marka_id')) {
                $table->foreignId('marka_id')
                    ->nullable()
                    ->constrained('muhasebe_markalar')
                    ->nullOnDelete()
                    ->after('tur');
            }
            if (! Schema::hasColumn('stok_kartlari', 'model_id')) {
                $table->foreignId('model_id')
                    ->nullable()
                    ->constrained('muhasebe_modeller')
                    ->nullOnDelete()
                    ->after('marka_id');
            }
            if (! Schema::hasColumn('stok_kartlari', 'tasarim_id')) {
                $table->foreignId('tasarim_id')
                    ->nullable()
                    ->constrained('muhasebe_tasarimlar')
                    ->nullOnDelete()
                    ->after('model_id');
            }
            if (! Schema::hasColumn('stok_kartlari', 'malzeme_turu_id')) {
                $table->foreignId('malzeme_turu_id')
                    ->nullable()
                    ->constrained('muhasebe_malzeme_turleri')
                    ->nullOnDelete()
                    ->after('tasarim_id');
            }
            if (! Schema::hasColumn('stok_kartlari', 'logo_turu_id')) {
                $table->foreignId('logo_turu_id')
                    ->nullable()
                    ->constrained('muhasebe_logo_turleri')
                    ->nullOnDelete()
                    ->after('malzeme_turu_id');
            }
            if (! Schema::hasColumn('stok_kartlari', 'varyant_id')) {
                $table->foreignId('varyant_id')
                    ->nullable()
                    ->constrained('muhasebe_varyantlar')
                    ->nullOnDelete()
                    ->after('logo_turu_id');
            }

            if (! Schema::hasColumn('stok_kartlari', 'tedarikci_id')) {
                $table->foreignId('tedarikci_id')
                    ->nullable()
                    ->constrained('cariler')
                    ->nullOnDelete()
                    ->after('varyant_id');
            }

            // Teknik / e-ticaret ölçüler
            if (! Schema::hasColumn('stok_kartlari', 'agirlik')) {
                $table->decimal('agirlik', 10, 2)->nullable()->after('tedarikci_id');
            }
            if (! Schema::hasColumn('stok_kartlari', 'hacim')) {
                $table->decimal('hacim', 10, 3)->nullable()->after('agirlik');
            }
            if (! Schema::hasColumn('stok_kartlari', 'kargo_sinifi')) {
                $table->string('kargo_sinifi', 64)->nullable()->after('hacim');
            }

            if (! Schema::hasColumn('stok_kartlari', 'satis_adedi')) {
                $table->unsignedInteger('satis_adedi')->default(0)->after('kargo_sinifi');
            }
            if (! Schema::hasColumn('stok_kartlari', 'goruntulenme_sayisi')) {
                $table->unsignedInteger('goruntulenme_sayisi')->default(0)->after('satis_adedi');
            }

            // Slug unique (firma bazlı)
            $table->unique(['firma_id', 'slug'], 'stok_kartlari_firma_id_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('stok_kartlari')) {
            return;
        }

        Schema::table('stok_kartlari', function (Blueprint $table): void {
            foreach ([
                'goruntulenme_sayisi',
                'satis_adedi',
                'kargo_sinifi',
                'hacim',
                'agirlik',
                'tedarikci_id',
                'varyant_id',
                'logo_turu_id',
                'malzeme_turu_id',
                'tasarim_id',
                'model_id',
                'marka_id',
                'og_etiket',
                'og_aciklama',
                'og_baslik',
                'og_gorsel',
                'seo_keywords',
                'seo_description',
                'seo_title',
                'galeri',
                'gorsel',
                'slug',
                'gumruk_orani',
                'indirimli_fiyat',
                'depo_id',
                'maksimum_stok',
                'imei_no',
                'seri_no',
            ] as $kolon) {
                if (Schema::hasColumn('stok_kartlari', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }

            if (Schema::hasColumn('stok_kartlari', 'slug')) {
                $table->dropUnique('stok_kartlari_firma_id_slug_unique');
            }
        });
    }
};
