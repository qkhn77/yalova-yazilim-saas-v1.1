<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('menu_items', 'title')) {
                $table->string('title')->nullable()->after('parent_id');
            }
            if (! Schema::hasColumn('menu_items', 'route_name')) {
                $table->string('route_name')->nullable()->after('url');
            }
            if (! Schema::hasColumn('menu_items', 'target_type')) {
                $table->string('target_type', 20)->default('same_tab')->after('route_name');
            }
            if (! Schema::hasColumn('menu_items', 'icon')) {
                $table->string('icon')->nullable()->after('target_type');
            }
            if (! Schema::hasColumn('menu_items', 'css_class')) {
                $table->string('css_class')->nullable()->after('icon');
            }
            if (! Schema::hasColumn('menu_items', 'menu_location')) {
                $table->string('menu_location', 30)->default('primary')->after('css_class');
            }
        });

        if (Schema::hasColumn('menu_items', 'label')) {
            DB::table('menu_items')
                ->whereNull('title')
                ->update(['title' => DB::raw('label')]);
        }

        DB::table('menu_items')
            ->whereNull('menu_location')
            ->update(['menu_location' => 'primary']);

        DB::table('menu_items')
            ->whereNull('target_type')
            ->update(['target_type' => 'same_tab']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            foreach (['menu_location', 'css_class', 'icon', 'target_type', 'route_name', 'title'] as $column) {
                if (Schema::hasColumn('menu_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

