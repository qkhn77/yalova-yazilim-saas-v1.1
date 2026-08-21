<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('masraflar', 'belge_yolu')) {
            Schema::table('masraflar', function (Blueprint $table): void {
                $table->string('belge_yolu', 500)->nullable()->after('notlar');
                $table->string('belge_adi', 255)->nullable()->after('belge_yolu');
                $table->string('belge_mime', 120)->nullable()->after('belge_adi');
                $table->unsignedBigInteger('belge_boyutu')->nullable()->after('belge_mime');
                $table->foreignId('belge_yukleyen_kullanici_id')->nullable()->after('belge_boyutu')->constrained('users')->nullOnDelete();
                $table->index(['firma_id', 'belge_yolu'], 'masraf_belge_firma_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('masraflar', 'belge_yolu')) {
            Schema::table('masraflar', function (Blueprint $table): void {
                $table->dropForeign(['belge_yukleyen_kullanici_id']);
                $table->dropIndex('masraf_belge_firma_idx');
                $table->dropColumn(['belge_yolu', 'belge_adi', 'belge_mime', 'belge_boyutu', 'belge_yukleyen_kullanici_id']);
            });
        }
    }
};
