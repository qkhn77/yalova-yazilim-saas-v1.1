<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'meta_keywords')) {
                $table->string('meta_keywords', 500)->nullable()->after('short_description');
            }
        });

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'meta_keywords')) {
                $table->string('meta_keywords', 500)->nullable()->after('short_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'meta_keywords')) {
                $table->dropColumn('meta_keywords');
            }
        });

        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'meta_keywords')) {
                $table->dropColumn('meta_keywords');
            }
        });
    }
};
