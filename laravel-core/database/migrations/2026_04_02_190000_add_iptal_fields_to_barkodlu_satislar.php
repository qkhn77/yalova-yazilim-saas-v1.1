<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satislar')) {
            return;
        }

        Schema::table('muhasebe_barkodlu_satislar', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_barkodlu_satislar', 'durum')) {
                $table->string('durum', 32)->default('tamamlandi')->after('genel_toplam');
                $table->index(['firma_id', 'durum'], 'muhasebe_barkodlu_satislar_firma_durum_idx');
            }

            if (! Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_tarihi')) {
                $table->dateTime('iptal_tarihi')->nullable()->after('durum');
            }

            if (! Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_nedeni')) {
                $table->text('iptal_nedeni')->nullable()->after('iptal_tarihi');
            }

            if (! Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_eden_id')) {
                $table->foreignId('iptal_eden_id')->nullable()->after('iptal_nedeni')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satislar')) {
            return;
        }

        Schema::table('muhasebe_barkodlu_satislar', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_eden_id')) {
                $table->dropConstrainedForeignId('iptal_eden_id');
            }
            if (Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_nedeni')) {
                $table->dropColumn('iptal_nedeni');
            }
            if (Schema::hasColumn('muhasebe_barkodlu_satislar', 'iptal_tarihi')) {
                $table->dropColumn('iptal_tarihi');
            }
            if (Schema::hasColumn('muhasebe_barkodlu_satislar', 'durum')) {
                $table->dropIndex('muhasebe_barkodlu_satislar_firma_durum_idx');
                $table->dropColumn('durum');
            }
        });
    }
};

