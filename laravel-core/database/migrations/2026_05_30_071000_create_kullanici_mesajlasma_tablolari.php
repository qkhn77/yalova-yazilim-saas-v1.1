<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kullanici_mesaj_konulari')) {
            Schema::create('kullanici_mesaj_konulari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
                $table->foreignId('olusturan_id')->constrained('users')->cascadeOnDelete();
                $table->string('baslik');
                $table->string('oncelik', 20)->default('normal')->index();
                $table->string('durum', 20)->default('acik')->index();
                $table->foreignId('son_mesaj_id')->nullable();
                $table->timestamp('son_mesaj_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['firma_id', 'son_mesaj_at']);
                $table->index(['olusturan_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('kullanici_mesajlari')) {
            Schema::create('kullanici_mesajlari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('konu_id')->constrained('kullanici_mesaj_konulari')->cascadeOnDelete();
                $table->foreignId('gonderen_id')->constrained('users')->cascadeOnDelete();
                $table->text('mesaj');
                $table->json('ekler')->nullable();
                $table->boolean('sistem_mesaji_mi')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['konu_id', 'created_at']);
                $table->index(['gonderen_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('kullanici_mesaj_katilimcilari')) {
            Schema::create('kullanici_mesaj_katilimcilari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('konu_id')->constrained('kullanici_mesaj_konulari')->cascadeOnDelete();
                $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('son_okuma_at')->nullable();
                $table->boolean('favori_mi')->default(false);
                $table->boolean('arsivlendi_mi')->default(false);
                $table->boolean('sessize_alindi_mi')->default(false);
                $table->timestamps();

                $table->unique(['konu_id', 'kullanici_id'], 'kul_mesaj_katilimci_unique');
                $table->index(['kullanici_id', 'arsivlendi_mi']);
            });
        }

        if (! Schema::hasTable('kullanici_bildirimleri')) {
            Schema::create('kullanici_bildirimleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
                $table->foreignId('kullanici_id')->constrained('users')->cascadeOnDelete();
                $table->string('kaynak_turu')->nullable();
                $table->unsignedBigInteger('kaynak_id')->nullable();
                $table->string('baslik');
                $table->text('mesaj')->nullable();
                $table->string('seviye', 20)->default('bilgi')->index();
                $table->timestamp('okundu_at')->nullable()->index();
                $table->string('aksiyon_url')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index(['kullanici_id', 'okundu_at']);
                $table->index(['firma_id', 'created_at']);
                $table->index(['kaynak_turu', 'kaynak_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kullanici_bildirimleri');
        Schema::dropIfExists('kullanici_mesaj_katilimcilari');
        Schema::dropIfExists('kullanici_mesajlari');
        Schema::dropIfExists('kullanici_mesaj_konulari');
    }
};
