<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            if (! Schema::hasColumn('siparisler', 'kampanya_id')) {
                $table->unsignedBigInteger('kampanya_id')->nullable()->after('durum');
                $table->index('kampanya_id');
            }

            if (! Schema::hasColumn('siparisler', 'kampanya_adi')) {
                $table->string('kampanya_adi', 180)->nullable()->after('kampanya_id');
            }

            if (! Schema::hasColumn('siparisler', 'kupon_kodu')) {
                $table->string('kupon_kodu', 64)->nullable()->after('kampanya_adi');
                $table->index('kupon_kodu');
            }

            if (! Schema::hasColumn('siparisler', 'indirim_toplami')) {
                $table->decimal('indirim_toplami', 14, 2)->default(0)->after('kdv_toplam');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            if (Schema::hasColumn('siparisler', 'kampanya_id')) {
                $table->dropIndex(['kampanya_id']);
                $table->dropColumn('kampanya_id');
            }

            if (Schema::hasColumn('siparisler', 'kampanya_adi')) {
                $table->dropColumn('kampanya_adi');
            }

            if (Schema::hasColumn('siparisler', 'kupon_kodu')) {
                $table->dropIndex(['kupon_kodu']);
                $table->dropColumn('kupon_kodu');
            }

            if (Schema::hasColumn('siparisler', 'indirim_toplami')) {
                $table->dropColumn('indirim_toplami');
            }
        });
    }
};