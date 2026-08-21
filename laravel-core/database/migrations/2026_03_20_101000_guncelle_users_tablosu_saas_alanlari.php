<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'email')) {
                try {
                    $table->dropUnique('users_email_unique');
                } catch (\Throwable) {
                    // index yoksa sessizce devam
                }
                $table->index('email', 'users_email_index');
            }

            if (! Schema::hasColumn('users', 'kullanici_adi')) {
                $table->string('kullanici_adi')->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('users', 'ad_soyad')) {
                $table->string('ad_soyad')->nullable()->after('kullanici_adi');
            }
            if (! Schema::hasColumn('users', 'super_admin_mi')) {
                $table->boolean('super_admin_mi')->default(false)->index()->after('password');
            }
            if (! Schema::hasColumn('users', 'son_giris_tarihi')) {
                $table->timestamp('son_giris_tarihi')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach (['kullanici_adi', 'ad_soyad', 'super_admin_mi', 'son_giris_tarihi', 'deleted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

