<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restoran_menu_kategorileri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('sube_id')->nullable()->constrained('subeler')->nullOnDelete();
            $table->string('ad');
            $table->string('slug', 128);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firma_id', 'slug']);
            $table->index(['firma_id', 'sube_id', 'aktif_mi']);
        });

        Schema::create('restoran_menu_urunleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('restoran_menu_kategorileri')->nullOnDelete();
            $table->foreignId('stok_karti_id')->nullable()->constrained('stok_kartlari')->nullOnDelete();
            $table->string('ad');
            $table->text('aciklama')->nullable();
            $table->decimal('fiyat', 18, 2)->default(0);
            $table->decimal('kdv_orani', 5, 2)->default(0);
            $table->string('gorsel_yolu')->nullable();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->boolean('qr_menu_gorunur_mu')->default(true)->index();
            $table->boolean('stokta_var_mi')->default(true)->index();
            $table->unsignedInteger('siralama')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firma_id', 'kategori_id', 'aktif_mi']);
            $table->index(['firma_id', 'qr_menu_gorunur_mu', 'stokta_var_mi'], 'restoran_menu_qr_gorunum_idx');
            $table->index(['firma_id', 'stok_karti_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restoran_menu_urunleri');
        Schema::dropIfExists('restoran_menu_kategorileri');
    }
};
