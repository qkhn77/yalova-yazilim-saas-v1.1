<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->muhasebeParaBirimleriGuncelle();
        $this->stokKategorileriGuncelle();
        $this->yeniTanimTablolariniOlustur();
    }

    public function down(): void
    {
        $this->yeniTanimTablolariniKaldir();

        if (Schema::hasTable('stok_kategorileri') && Schema::hasColumn('stok_kategorileri', 'tanim_firma_kapsami')) {
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->dropUnique(['tanim_firma_kapsami', 'kod']);
                $table->dropUnique(['tanim_firma_kapsami', 'ad']);
            });
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->dropForeign(['firma_id']);
            });
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->unsignedBigInteger('firma_id')->nullable(false)->change();
            });
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->foreign('firma_id')->references('id')->on('firmalar')->cascadeOnDelete();
                $table->unique(['firma_id', 'kod']);
                $table->unique(['firma_id', 'ad']);
            });
            Schema::table('stok_kategorileri', function (Blueprint $table): void {
                $table->dropColumn(['is_sabit', 'tanim_firma_kapsami']);
            });
        }

        if (Schema::hasTable('muhasebe_para_birimleri') && Schema::hasColumn('muhasebe_para_birimleri', 'tanim_firma_kapsami')) {
            Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
                $table->dropUnique(['tanim_firma_kapsami', 'kod']);
            });
            Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
                $table->dropForeign(['firma_id']);
            });
            Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
                $table->unsignedBigInteger('firma_id')->nullable(false)->change();
            });
            Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
                $table->foreign('firma_id')->references('id')->on('firmalar')->cascadeOnDelete();
                $table->unique(['firma_id', 'kod']);
            });
            Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
                $table->dropColumn(['is_sabit', 'tanim_firma_kapsami']);
            });
        }
    }

    private function muhasebeParaBirimleriGuncelle(): void
    {
        if (! Schema::hasTable('muhasebe_para_birimleri')) {
            return;
        }

        if (Schema::hasColumn('muhasebe_para_birimleri', 'is_sabit')) {
            return;
        }

        Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
            $table->boolean('is_sabit')->default(false);
            $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
        });

        DB::table('muhasebe_para_birimleri')->update([
            'tanim_firma_kapsami' => DB::raw('COALESCE(firma_id, 0)'),
        ]);

        Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
            $table->dropForeign(['firma_id']);
        });

        Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
            $table->dropUnique(['firma_id', 'kod']);
        });

        Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
            $table->foreignId('firma_id')->nullable()->change();
        });

        Schema::table('muhasebe_para_birimleri', function (Blueprint $table): void {
            $table->foreign('firma_id')->references('id')->on('firmalar')->cascadeOnDelete();
            $table->unique(['tanim_firma_kapsami', 'kod']);
            $table->index(['firma_id', 'is_sabit']);
        });

        $simdi = now();
        $var = DB::table('muhasebe_para_birimleri')
            ->whereNull('firma_id')
            ->where('kod', 'TRY')
            ->exists();
        if (! $var) {
            DB::table('muhasebe_para_birimleri')->insert([
                'firma_id' => null,
                'kod' => 'TRY',
                'ad' => 'Türk Lirası',
                'aktif_mi' => true,
                'is_sabit' => true,
                'tanim_firma_kapsami' => 0,
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ]);
        }
    }

    private function stokKategorileriGuncelle(): void
    {
        if (! Schema::hasTable('stok_kategorileri')) {
            return;
        }

        if (Schema::hasColumn('stok_kategorileri', 'is_sabit')) {
            return;
        }

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            $table->boolean('is_sabit')->default(false);
            $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
        });

        DB::table('stok_kategorileri')->update([
            'tanim_firma_kapsami' => DB::raw('COALESCE(firma_id, 0)'),
        ]);

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            $table->dropForeign(['firma_id']);
        });

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            $table->dropUnique(['firma_id', 'kod']);
            $table->dropUnique(['firma_id', 'ad']);
        });

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            $table->foreignId('firma_id')->nullable()->change();
        });

        Schema::table('stok_kategorileri', function (Blueprint $table): void {
            $table->foreign('firma_id')->references('id')->on('firmalar')->cascadeOnDelete();
            $table->unique(['tanim_firma_kapsami', 'kod']);
            $table->unique(['tanim_firma_kapsami', 'ad']);
            $table->index(['firma_id', 'is_sabit']);
        });
    }

    private function standartTanimSemasi(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('firma_id')->nullable()->constrained('firmalar')->cascadeOnDelete();
        $table->boolean('is_sabit')->default(false);
        $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
        $table->string('kod', 64);
        $table->string('ad', 191);
        $table->boolean('aktif_mi')->default(true);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['tanim_firma_kapsami', 'kod']);
        $table->index(['firma_id', 'is_sabit']);
    }

    private function yeniTanimTablolariniOlustur(): void
    {
        if (! Schema::hasTable('muhasebe_birimler')) {
            Schema::create('muhasebe_birimler', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_vergi_oranlari')) {
            Schema::create('muhasebe_vergi_oranlari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->nullable()->constrained('firmalar')->cascadeOnDelete();
                $table->boolean('is_sabit')->default(false);
                $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
                $table->string('kod', 64);
                $table->string('ad', 191);
                $table->decimal('oran', 8, 4)->default(0);
                $table->boolean('aktif_mi')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tanim_firma_kapsami', 'kod']);
                $table->index(['firma_id', 'is_sabit']);
            });
        }

        if (! Schema::hasTable('muhasebe_cari_gruplari')) {
            Schema::create('muhasebe_cari_gruplari', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_odeme_yontemleri')) {
            Schema::create('muhasebe_odeme_yontemleri', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_markalar')) {
            Schema::create('muhasebe_markalar', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_modeller')) {
            Schema::create('muhasebe_modeller', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->nullable()->constrained('firmalar')->cascadeOnDelete();
                $table->boolean('is_sabit')->default(false);
                $table->unsignedBigInteger('tanim_firma_kapsami')->default(0);
                $table->foreignId('marka_id')->constrained('muhasebe_markalar')->cascadeOnDelete();
                $table->string('kod', 64);
                $table->string('ad', 191);
                $table->boolean('aktif_mi')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tanim_firma_kapsami', 'marka_id', 'kod']);
                $table->index(['firma_id', 'is_sabit']);
                $table->index('marka_id');
            });
        }

        if (! Schema::hasTable('muhasebe_tasarimlar')) {
            Schema::create('muhasebe_tasarimlar', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_malzeme_turleri')) {
            Schema::create('muhasebe_malzeme_turleri', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_logo_turleri')) {
            Schema::create('muhasebe_logo_turleri', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }

        if (! Schema::hasTable('muhasebe_varyantlar')) {
            Schema::create('muhasebe_varyantlar', function (Blueprint $table): void {
                $this->standartTanimSemasi($table);
            });
        }
    }

    private function yeniTanimTablolariniKaldir(): void
    {
        foreach ([
            'muhasebe_varyantlar',
            'muhasebe_logo_turleri',
            'muhasebe_malzeme_turleri',
            'muhasebe_tasarimlar',
            'muhasebe_modeller',
            'muhasebe_markalar',
            'muhasebe_odeme_yontemleri',
            'muhasebe_cari_gruplari',
            'muhasebe_vergi_oranlari',
            'muhasebe_birimler',
        ] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
