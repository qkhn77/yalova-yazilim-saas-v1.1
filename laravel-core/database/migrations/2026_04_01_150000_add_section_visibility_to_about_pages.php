<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('about_pages', function (Blueprint $table) {
            $table->boolean('show_header_section')->default(true)->after('meta_keywords');
            $table->boolean('show_about_section')->default(true)->after('show_header_section');
            $table->boolean('show_mission_vision_section')->default(true)->after('show_about_section');
            $table->boolean('show_why_choose_section')->default(true)->after('show_mission_vision_section');
            $table->boolean('show_commitment_section')->default(true)->after('show_why_choose_section');
            $table->boolean('show_expertise_section')->default(true)->after('show_commitment_section');
            $table->boolean('show_what_we_do_section')->default(true)->after('show_expertise_section');
            $table->boolean('show_team_section')->default(true)->after('show_what_we_do_section');
            $table->boolean('show_support_section')->default(true)->after('show_team_section');
            $table->boolean('show_testimonials_section')->default(true)->after('show_support_section');
            $table->boolean('show_cta_section')->default(true)->after('show_testimonials_section');
            $table->boolean('show_faq_section')->default(true)->after('show_cta_section');
        });
    }

    public function down(): void
    {
        Schema::table('about_pages', function (Blueprint $table) {
            $table->dropColumn([
                'show_header_section',
                'show_about_section',
                'show_mission_vision_section',
                'show_why_choose_section',
                'show_commitment_section',
                'show_expertise_section',
                'show_what_we_do_section',
                'show_team_section',
                'show_support_section',
                'show_testimonials_section',
                'show_cta_section',
                'show_faq_section',
            ]);
        });
    }
};
