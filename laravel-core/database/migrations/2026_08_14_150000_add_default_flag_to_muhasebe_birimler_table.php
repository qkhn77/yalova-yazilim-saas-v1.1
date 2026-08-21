<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('muhasebe_birimler', 'varsayilan_mi')) {
            Schema::table('muhasebe_birimler', function (Blueprint $table): void {
                $table->boolean('varsayilan_mi')->default(false)->after('aktif_mi');
                $table->index(['firma_id', 'varsayilan_mi']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('muhasebe_birimler', 'varsayilan_mi')) {
            Schema::table('muhasebe_birimler', function (Blueprint $table): void {
                $table->dropIndex('muhasebe_birimler_firma_id_varsayilan_mi_index');
                $table->dropColumn('varsayilan_mi');
            });
        }
    }
};
