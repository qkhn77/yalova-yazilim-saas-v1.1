<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_kullanici_adresleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('ecommerce_kullanici_adresleri', 'vergi_dairesi')) {
                $table->string('vergi_dairesi', 128)->nullable()->after('telefon');
            }

            if (! Schema::hasColumn('ecommerce_kullanici_adresleri', 'vergi_no')) {
                $table->string('vergi_no', 32)->nullable()->after('vergi_dairesi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_kullanici_adresleri', function (Blueprint $table): void {
            if (Schema::hasColumn('ecommerce_kullanici_adresleri', 'vergi_no')) {
                $table->dropColumn('vergi_no');
            }

            if (Schema::hasColumn('ecommerce_kullanici_adresleri', 'vergi_dairesi')) {
                $table->dropColumn('vergi_dairesi');
            }
        });
    }
};
