<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'meta_keywords')) {
                $table->string('meta_keywords', 500)->nullable()->after('excerpt');
            }
            if (! Schema::hasColumn('posts', 'og_title')) {
                $table->string('og_title', 100)->nullable()->after('content');
            }
            if (! Schema::hasColumn('posts', 'og_description')) {
                $table->string('og_description', 500)->nullable()->after('og_title');
            }
            if (! Schema::hasColumn('posts', 'og_image')) {
                $table->string('og_image')->nullable()->after('og_description');
            }
            if (! Schema::hasColumn('posts', 'meta_robots')) {
                $table->string('meta_robots', 50)->default('index,follow')->after('og_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['meta_keywords', 'og_title', 'og_description', 'og_image', 'meta_robots']);
        });
    }
};
