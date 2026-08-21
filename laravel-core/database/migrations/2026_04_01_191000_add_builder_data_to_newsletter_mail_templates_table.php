<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_mail_templates', function (Blueprint $table): void {
            $table->longText('builder_data')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_mail_templates', function (Blueprint $table): void {
            $table->dropColumn('builder_data');
        });
    }
};
