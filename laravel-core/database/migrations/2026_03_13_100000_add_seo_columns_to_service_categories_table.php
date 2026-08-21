<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('service_categories', 'meta_title')) {
                $table->string('meta_title', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('service_categories', 'meta_description')) {
                $table->string('meta_description', 165)->nullable()->after('description');
            }
            if (! Schema::hasColumn('service_categories', 'meta_keywords')) {
                $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords']);
        });
    }
};
