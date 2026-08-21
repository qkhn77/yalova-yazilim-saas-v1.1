<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            $table->string('kargo_firmasi', 120)->nullable()->after('odeme_deneme_sayisi');
            $table->string('takip_no', 120)->nullable()->after('kargo_firmasi');
            $table->date('kargo_tarihi')->nullable()->after('takip_no');
            $table->date('teslim_tarihi')->nullable()->after('kargo_tarihi');
            $table->text('iptal_nedeni')->nullable()->after('teslim_tarihi');
            $table->text('ic_not')->nullable()->after('iptal_nedeni');
            $table->text('musteri_notu')->nullable()->after('ic_not');
            $table->text('operasyon_notu')->nullable()->after('musteri_notu');
        });

        Schema::create('siparis_gecmisleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siparis_id')->constrained('siparisler')->cascadeOnDelete();
            $table->foreignId('kullanici_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('olay', 64)->index();
            $table->text('aciklama')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['siparis_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siparis_gecmisleri');

        Schema::table('siparisler', function (Blueprint $table): void {
            $table->dropColumn([
                'kargo_firmasi',
                'takip_no',
                'kargo_tarihi',
                'teslim_tarihi',
                'iptal_nedeni',
                'ic_not',
                'musteri_notu',
                'operasyon_notu',
            ]);
        });
    }
};
