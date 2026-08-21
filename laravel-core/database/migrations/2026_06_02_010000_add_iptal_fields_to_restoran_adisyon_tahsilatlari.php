<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restoran_adisyon_tahsilatlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('restoran_adisyon_tahsilatlari', 'iptal_finans_hareketi_id')) {
                $table->foreignId('iptal_finans_hareketi_id')
                    ->nullable()
                    ->after('finans_hareketi_id')
                    ->constrained('finans_hareketleri')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('restoran_adisyon_tahsilatlari', 'iptal_at')) {
                $table->dateTime('iptal_at')->nullable()->after('tahsilat_at');
            }

            if (! Schema::hasColumn('restoran_adisyon_tahsilatlari', 'iptal_notu')) {
                $table->text('iptal_notu')->nullable()->after('notlar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restoran_adisyon_tahsilatlari', function (Blueprint $table): void {
            if (Schema::hasColumn('restoran_adisyon_tahsilatlari', 'iptal_finans_hareketi_id')) {
                $table->dropConstrainedForeignId('iptal_finans_hareketi_id');
            }

            foreach (['iptal_at', 'iptal_notu'] as $kolon) {
                if (Schema::hasColumn('restoran_adisyon_tahsilatlari', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }
        });
    }
};
