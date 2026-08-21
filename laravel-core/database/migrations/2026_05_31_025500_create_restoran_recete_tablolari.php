<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_adisyon_kalemleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('restoran_adisyon_kalemleri', 'menu_urunu_id')) {
                $table->foreignId('menu_urunu_id')
                    ->nullable()
                    ->after('adisyon_id')
                    ->constrained('restoran_menu_urunleri')
                    ->nullOnDelete();

                $table->index(['firma_id', 'menu_urunu_id'], 'restoran_adisyon_kalem_menu_idx');
            }
        });

        Schema::create('restoran_receteleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('menu_urunu_id')->constrained('restoran_menu_urunleri')->cascadeOnDelete();
            $table->string('ad')->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'menu_urunu_id'], 'restoran_recete_menu_unique');
            $table->index(['firma_id', 'aktif_mi']);
        });

        Schema::create('restoran_recete_kalemleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('recete_id')->constrained('restoran_receteleri')->cascadeOnDelete();
            $table->foreignId('stok_karti_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->decimal('miktar', 18, 4);
            $table->decimal('fire_orani', 5, 2)->default(0);
            $table->text('notlar')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'recete_id', 'stok_karti_id'], 'restoran_recete_kalem_stok_unique');
            $table->index(['firma_id', 'stok_karti_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restoran_recete_kalemleri');
        Schema::dropIfExists('restoran_receteleri');

        Schema::table('restoran_adisyon_kalemleri', function (Blueprint $table): void {
            if (Schema::hasColumn('restoran_adisyon_kalemleri', 'menu_urunu_id')) {
                $table->dropIndex('restoran_adisyon_kalem_menu_idx');
                $table->dropConstrainedForeignId('menu_urunu_id');
            }
        });
    }
};
