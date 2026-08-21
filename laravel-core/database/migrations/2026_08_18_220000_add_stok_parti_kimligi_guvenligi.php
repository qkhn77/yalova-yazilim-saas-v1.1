<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            return;
        }

        $cakismalar = DB::table('stok_parcalari')
            ->select('firma_id', 'parca_kodu')
            ->groupBy('firma_id', 'parca_kodu')
            ->havingRaw('COUNT(DISTINCT stok_id) > 1')
            ->get();
        if ($cakismalar->isNotEmpty()) {
            $ornek = $cakismalar->first();
            throw new RuntimeException("Parti kimliği kurulamadı: firma {$ornek->firma_id} içindeki {$ornek->parca_kodu} birden fazla stok kartında kullanılıyor.");
        }

        Schema::create('stok_parti_kimlikleri', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
            $table->string('parca_kodu', 128);
            $table->timestamps();

            $table->unique(['firma_id', 'parca_kodu'], 'stok_parti_kimlik_firma_no_uniq');
            $table->unique(['id', 'stok_id', 'parca_kodu'], 'stok_parti_kimlik_bilesik_uniq');
        });

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            $table->unsignedBigInteger('parti_kimligi_id')->nullable()->after('id');
        });

        DB::table('stok_parcalari')
            ->select('firma_id', 'stok_id', 'parca_kodu')
            ->distinct()
            ->orderBy('firma_id')
            ->orderBy('stok_id')
            ->orderBy('parca_kodu')
            ->get()
            ->each(function (object $parti): void {
                $kimlikId = DB::table('stok_parti_kimlikleri')->insertGetId([
                    'firma_id' => $parti->firma_id,
                    'stok_id' => $parti->stok_id,
                    'parca_kodu' => $parti->parca_kodu,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('stok_parcalari')
                    ->where('firma_id', $parti->firma_id)
                    ->where('stok_id', $parti->stok_id)
                    ->where('parca_kodu', $parti->parca_kodu)
                    ->update(['parti_kimligi_id' => $kimlikId]);
            });

        Schema::table('stok_parcalari', function (Blueprint $table): void {
            $table->unsignedBigInteger('parti_kimligi_id')->nullable(false)->change();
            $table->foreign(['parti_kimligi_id', 'stok_id', 'parca_kodu'], 'stok_parcalari_kimlik_bilesik_fk')
                ->references(['id', 'stok_id', 'parca_kodu'])
                ->on('stok_parti_kimlikleri')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        if (Schema::hasColumn('stok_parcalari', 'ust_parca_id')) {
            Schema::table('stok_parcalari', function (Blueprint $table): void {
                $table->dropForeign(['ust_parca_id']);
                $table->foreign('ust_parca_id')->references('id')->on('stok_parcalari')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('stok_parcalari')) {
            Schema::dropIfExists('stok_parti_kimlikleri');

            return;
        }

        if (Schema::hasColumn('stok_parcalari', 'ust_parca_id')) {
            Schema::table('stok_parcalari', function (Blueprint $table): void {
                $table->dropForeign(['ust_parca_id']);
                $table->foreign('ust_parca_id')->references('id')->on('stok_parcalari')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('stok_parcalari', 'parti_kimligi_id')) {
            Schema::table('stok_parcalari', function (Blueprint $table): void {
                $table->dropForeign('stok_parcalari_kimlik_bilesik_fk');
                $table->dropColumn('parti_kimligi_id');
            });
        }
        Schema::dropIfExists('stok_parti_kimlikleri');
    }
};
