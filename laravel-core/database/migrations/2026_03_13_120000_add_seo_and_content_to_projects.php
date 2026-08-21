<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'short_description')) {
                $table->string('short_description', 165)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('projects', 'content')) {
                $table->longText('content')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'content']);
        });
    }
};
