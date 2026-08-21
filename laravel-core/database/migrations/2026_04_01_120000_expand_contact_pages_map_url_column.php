<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('contact_pages', 'map_url')) {
                $table->text('map_url')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('contact_pages', 'map_url')) {
                $table->string('map_url')->nullable()->change();
            }
        });
    }
};
