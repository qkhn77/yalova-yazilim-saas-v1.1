<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * contact_pages tablosuna whatsapp_url ve facebook_url ekler.
     * Tablo eski migration ile oluşturulduysa bu migration çalıştırılmalı.
     */
    public function up(): void
    {
        Schema::table('contact_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_pages', 'whatsapp_url')) {
                $table->string('whatsapp_url')->nullable()->after('form_intro');
            }
            if (!Schema::hasColumn('contact_pages', 'facebook_url')) {
                $table->string('facebook_url')->nullable()->after('twitter_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_pages', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_url', 'facebook_url']);
        });
    }
};
