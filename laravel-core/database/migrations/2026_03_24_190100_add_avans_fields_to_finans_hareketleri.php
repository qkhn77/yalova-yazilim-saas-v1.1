<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table) {
            $table->decimal('kullanilan_tutar', 18, 2)->default(0)->after('tutar');
            $table->decimal('avans_tutar', 18, 2)->default(0)->after('kullanilan_tutar');
        });
    }

    public function down(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table) {
            $table->dropColumn(['kullanilan_tutar', 'avans_tutar']);
        });
    }
};
