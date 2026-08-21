<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_pages', function (Blueprint $table) {
            $table->id();

            // Meta (utf8mb4 satır boyutu için varchar yerine text / kısa string)
            $table->string('meta_title', 180)->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            // Sayfa başlığı / üst alan
            $table->string('header_h1', 180)->nullable();
            $table->string('about_heading', 180)->nullable();
            $table->string('about_subheading_span', 150)->nullable();
            $table->string('about_subheading_rest', 150)->nullable();
            $table->longText('about_paragraph')->nullable();
            $table->string('about_experience_heading', 150)->nullable();
            $table->string('about_contact_prompt', 150)->nullable();
            $table->string('about_phone_text', 120)->nullable();
            $table->string('about_contact_button_text', 120)->nullable();

            // About görseller (yol/url — satır dışı text)
            $table->text('about_img_1')->nullable();
            $table->text('about_circle_icon')->nullable();
            $table->text('about_img_2')->nullable();
            $table->text('about_experience_image')->nullable();
            $table->text('about_icon_experience')->nullable();
            $table->text('about_icon_contact')->nullable();

            // Misyon & Vizyon
            $table->text('mission_vision_image')->nullable();
            $table->string('mission_heading', 150)->nullable();
            $table->longText('mission_text')->nullable();
            $table->string('vision_heading', 150)->nullable();
            $table->longText('vision_text')->nullable();
            $table->string('goal_heading', 150)->nullable();
            $table->longText('goal_text')->nullable();
            $table->text('icon_mission')->nullable();
            $table->text('icon_vision')->nullable();
            $table->text('icon_goal')->nullable();

            // Neden Bizi Tercih Etmelisiniz?
            $table->string('why_choose_heading', 180)->nullable();
            $table->string('why_choose_subheading_span', 150)->nullable();
            $table->string('why_choose_subheading_rest', 150)->nullable();
            $table->text('why_choose_icon_1')->nullable();
            $table->string('why_choose_item_1_title', 120)->nullable();
            $table->longText('why_choose_item_1_text')->nullable();
            $table->text('why_choose_icon_2')->nullable();
            $table->string('why_choose_item_2_title', 120)->nullable();
            $table->longText('why_choose_item_2_text')->nullable();
            $table->text('why_choose_icon_3')->nullable();
            $table->string('why_choose_item_3_title', 120)->nullable();
            $table->longText('why_choose_item_3_text')->nullable();
            $table->text('why_choose_icon_4')->nullable();
            $table->string('why_choose_item_4_title', 120)->nullable();
            $table->longText('why_choose_item_4_text')->nullable();
            $table->text('why_choose_image')->nullable();

            // Taahhüdümüz
            $table->string('commitment_heading', 180)->nullable();
            $table->string('commitment_subheading_span', 150)->nullable();
            $table->string('commitment_subheading_rest', 150)->nullable();
            $table->longText('commitment_paragraph')->nullable();
            $table->string('satisfy_client_text', 150)->nullable();
            $table->string('commitment_counter_item_1', 120)->nullable();
            $table->string('commitment_counter_item_2', 120)->nullable();
            $table->string('commitment_counter_item_3', 120)->nullable();
            $table->string('commitment_list_item_1', 200)->nullable();
            $table->string('commitment_list_item_2', 200)->nullable();
            $table->text('commitment_image_1')->nullable();
            $table->text('satisfy_client_img_1')->nullable();
            $table->text('satisfy_client_img_2')->nullable();
            $table->text('satisfy_client_img_3')->nullable();
            $table->text('satisfy_client_img_4')->nullable();
            $table->text('satisfy_client_img_5')->nullable();
            $table->text('commitment_image_2')->nullable();

            // Uzmanlığımız
            $table->string('expertise_heading', 180)->nullable();
            $table->string('expertise_subheading_span', 150)->nullable();
            $table->string('expertise_subheading_rest', 150)->nullable();
            $table->longText('expertise_paragraph')->nullable();
            $table->text('expertise_icon_1')->nullable();
            $table->string('expertise_item_1_title', 120)->nullable();
            $table->longText('expertise_item_1_text')->nullable();
            $table->text('expertise_icon_2')->nullable();
            $table->string('expertise_item_2_title', 120)->nullable();
            $table->longText('expertise_item_2_text')->nullable();
            $table->string('expertise_counter_label', 120)->nullable();
            $table->string('expertise_counter_list_item_1', 200)->nullable();
            $table->string('expertise_counter_list_item_2', 200)->nullable();
            $table->text('expertise_image')->nullable();
            $table->text('expertise_phone_icon')->nullable();
            $table->string('expertise_contact_heading', 150)->nullable();
            $table->string('expertise_phone_text', 120)->nullable();

            // Ne Yapıyoruz?
            $table->string('what_we_do_heading', 180)->nullable();
            $table->string('what_we_do_subheading_span', 150)->nullable();
            $table->string('what_we_do_subheading_rest', 150)->nullable();
            $table->longText('what_we_do_paragraph_1')->nullable();
            $table->longText('what_we_do_paragraph_2')->nullable();
            $table->text('need_help_icon')->nullable();
            $table->string('need_help_text', 200)->nullable();
            $table->string('what_we_do_phone_text', 120)->nullable();
            $table->text('what_we_counter_icon_1')->nullable();
            $table->string('what_we_counter_label_1', 120)->nullable();
            $table->text('what_we_counter_icon_2')->nullable();
            $table->string('what_we_counter_label_2', 120)->nullable();
            $table->text('what_we_image')->nullable();

            // Uzman Ekibimiz
            $table->string('team_heading', 180)->nullable();
            $table->string('team_subheading_span', 150)->nullable();
            $table->string('team_subheading_rest', 150)->nullable();
            $table->longText('team_paragraph')->nullable();
            $table->string('team_button_text', 120)->nullable();

            $table->text('team_img_1')->nullable();
            $table->string('team_title_1', 120)->nullable();
            $table->text('team_text_1')->nullable();
            $table->text('team_img_2')->nullable();
            $table->string('team_title_2', 120)->nullable();
            $table->text('team_text_2')->nullable();
            $table->text('team_img_3')->nullable();
            $table->string('team_title_3', 120)->nullable();
            $table->text('team_text_3')->nullable();
            $table->text('team_img_4')->nullable();
            $table->string('team_title_4', 120)->nullable();
            $table->text('team_text_4')->nullable();

            // Teknik Destek
            $table->string('support_heading', 180)->nullable();
            $table->string('support_subheading_span', 150)->nullable();
            $table->string('support_subheading_rest', 150)->nullable();
            $table->longText('support_paragraph')->nullable();
            $table->text('support_image_1')->nullable();
            $table->text('support_image_2')->nullable();
            $table->text('support_circle_icon')->nullable();
            $table->text('support_icon_1')->nullable();
            $table->string('support_item_1_title', 120)->nullable();
            $table->longText('support_item_1_text')->nullable();
            $table->text('support_icon_2')->nullable();
            $table->string('support_item_2_title', 120)->nullable();
            $table->longText('support_item_2_text')->nullable();
            $table->string('support_button_text', 120)->nullable();

            // Müşteri Yorumları
            $table->string('testimonials_heading', 180)->nullable();
            $table->string('testimonials_subheading_span', 150)->nullable();
            $table->string('testimonials_subheading_rest', 150)->nullable();
            $table->text('testimonial_quote_icon')->nullable();
            $table->text('testimonial_author_img_1')->nullable();
            $table->string('testimonial_name_1', 100)->nullable();
            $table->string('testimonial_role_1', 120)->nullable();
            $table->longText('testimonial_text_1')->nullable();
            $table->text('testimonial_author_img_2')->nullable();
            $table->string('testimonial_name_2', 100)->nullable();
            $table->string('testimonial_role_2', 120)->nullable();
            $table->longText('testimonial_text_2')->nullable();
            $table->text('testimonial_author_img_3')->nullable();
            $table->string('testimonial_name_3', 100)->nullable();
            $table->string('testimonial_role_3', 120)->nullable();
            $table->longText('testimonial_text_3')->nullable();

            // CTA: İletişim
            $table->string('cta_heading', 180)->nullable();
            $table->string('cta_subheading_span', 150)->nullable();
            $table->string('cta_subheading_rest', 150)->nullable();
            $table->longText('cta_paragraph')->nullable();
            $table->text('cta_phone_icon')->nullable();
            $table->string('cta_phone_label', 120)->nullable();
            $table->string('cta_phone_text', 120)->nullable();
            $table->text('cta_mail_icon')->nullable();
            $table->string('cta_mail_label', 120)->nullable();
            $table->string('cta_mail_text', 150)->nullable();
            $table->text('cta_image')->nullable();

            // Sık Sorulan Sorular
            $table->string('faqs_heading', 180)->nullable();
            $table->string('faqs_subheading_span', 150)->nullable();
            $table->string('faqs_subheading_rest', 150)->nullable();
            $table->longText('faqs_paragraph')->nullable();
            $table->string('faqs_list_item_1', 200)->nullable();
            $table->string('faqs_list_item_2', 200)->nullable();
            $table->string('faqs_list_item_3', 200)->nullable();
            $table->text('faq_image')->nullable();
            $table->text('faq_q_1')->nullable();
            $table->longText('faq_a_1')->nullable();
            $table->text('faq_q_2')->nullable();
            $table->longText('faq_a_2')->nullable();
            $table->text('faq_q_3')->nullable();
            $table->longText('faq_a_3')->nullable();
            $table->text('faq_q_4')->nullable();
            $table->longText('faq_a_4')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_pages');
    }
};
