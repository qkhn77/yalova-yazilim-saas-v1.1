<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AboutPage extends Model
{
    private const INSTANCE_CACHE_KEY = 'about_page.instance';
    private const FIRST_CACHE_KEY = 'about_page.first';

    private static ?self $runtimeInstance = null;
    private static bool $runtimeFirstLoaded = false;
    private static ?self $runtimeFirst = null;

    protected $table = 'about_pages';

    protected $fillable = [
        // Meta
        'meta_title',
        'meta_description',
        'meta_keywords',

        // Section visibility
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

        // Header
        'header_h1',

        // About Us
        'about_heading',
        'about_subheading_span',
        'about_subheading_rest',
        'about_paragraph',
        'about_experience_heading',
        'about_contact_prompt',
        'about_phone_text',
        'about_contact_button_text',

        // About images
        'about_img_1',
        'about_circle_icon',
        'about_img_2',
        'about_experience_image',
        'about_icon_experience',
        'about_icon_contact',

        // Mission & Vision
        'mission_vision_image',
        'mission_heading',
        'mission_text',
        'vision_heading',
        'vision_text',
        'goal_heading',
        'goal_text',
        'icon_mission',
        'icon_vision',
        'icon_goal',

        // Why choose
        'why_choose_heading',
        'why_choose_subheading_span',
        'why_choose_subheading_rest',
        'why_choose_icon_1',
        'why_choose_item_1_title',
        'why_choose_item_1_text',
        'why_choose_icon_2',
        'why_choose_item_2_title',
        'why_choose_item_2_text',
        'why_choose_icon_3',
        'why_choose_item_3_title',
        'why_choose_item_3_text',
        'why_choose_icon_4',
        'why_choose_item_4_title',
        'why_choose_item_4_text',
        'why_choose_image',

        // Commitment
        'commitment_heading',
        'commitment_subheading_span',
        'commitment_subheading_rest',
        'commitment_paragraph',
        'satisfy_client_text',
        'commitment_counter_item_1',
        'commitment_counter_item_2',
        'commitment_counter_item_3',
        'commitment_list_item_1',
        'commitment_list_item_2',
        'commitment_image_1',
        'satisfy_client_img_1',
        'satisfy_client_img_2',
        'satisfy_client_img_3',
        'satisfy_client_img_4',
        'satisfy_client_img_5',
        'commitment_image_2',

        // Expertise
        'expertise_heading',
        'expertise_subheading_span',
        'expertise_subheading_rest',
        'expertise_paragraph',
        'expertise_icon_1',
        'expertise_item_1_title',
        'expertise_item_1_text',
        'expertise_icon_2',
        'expertise_item_2_title',
        'expertise_item_2_text',
        'expertise_counter_label',
        'expertise_counter_list_item_1',
        'expertise_counter_list_item_2',
        'expertise_image',
        'expertise_phone_icon',
        'expertise_contact_heading',
        'expertise_phone_text',

        // What we do
        'what_we_do_heading',
        'what_we_do_subheading_span',
        'what_we_do_subheading_rest',
        'what_we_do_paragraph_1',
        'what_we_do_paragraph_2',
        'need_help_icon',
        'need_help_text',
        'what_we_do_phone_text',
        'what_we_counter_icon_1',
        'what_we_counter_label_1',
        'what_we_counter_icon_2',
        'what_we_counter_label_2',
        'what_we_image',

        // Team
        'team_heading',
        'team_subheading_span',
        'team_subheading_rest',
        'team_paragraph',
        'team_button_text',
        'team_img_1',
        'team_title_1',
        'team_text_1',
        'team_img_2',
        'team_title_2',
        'team_text_2',
        'team_img_3',
        'team_title_3',
        'team_text_3',
        'team_img_4',
        'team_title_4',
        'team_text_4',

        // Support
        'support_heading',
        'support_subheading_span',
        'support_subheading_rest',
        'support_paragraph',
        'support_image_1',
        'support_image_2',
        'support_circle_icon',
        'support_icon_1',
        'support_item_1_title',
        'support_item_1_text',
        'support_icon_2',
        'support_item_2_title',
        'support_item_2_text',
        'support_button_text',

        // Testimonials
        'testimonials_heading',
        'testimonials_subheading_span',
        'testimonials_subheading_rest',
        'testimonial_quote_icon',
        'testimonial_author_img_1',
        'testimonial_name_1',
        'testimonial_role_1',
        'testimonial_text_1',
        'testimonial_author_img_2',
        'testimonial_name_2',
        'testimonial_role_2',
        'testimonial_text_2',
        'testimonial_author_img_3',
        'testimonial_name_3',
        'testimonial_role_3',
        'testimonial_text_3',

        // CTA
        'cta_heading',
        'cta_subheading_span',
        'cta_subheading_rest',
        'cta_paragraph',
        'cta_phone_icon',
        'cta_phone_label',
        'cta_phone_text',
        'cta_mail_icon',
        'cta_mail_label',
        'cta_mail_text',
        'cta_image',

        // FAQs
        'faqs_heading',
        'faqs_subheading_span',
        'faqs_subheading_rest',
        'faqs_paragraph',
        'faqs_list_item_1',
        'faqs_list_item_2',
        'faqs_list_item_3',
        'faq_image',
        'faq_q_1',
        'faq_a_1',
        'faq_q_2',
        'faq_a_2',
        'faq_q_3',
        'faq_a_3',
        'faq_q_4',
        'faq_a_4',

        'is_active',
        'sort_order',
    ];

    /**
     * Editör alanları için metinlerin varsayılan değerleri.
     * Görseller null kalır; front tarafı theme fallback kullanır.
     */
    public static function defaults(): array
    {
        return [
            'meta_title' => 'Hakkımızda | Yalova Kamera Sistemleri',
            'meta_description' => 'Yalova Kamera Sistemleri olarak güvenlik kamerası ve alarm sistemlerinde keşif, kurulum, bakım ve onarım hizmeti sunuyoruz.',
            'meta_keywords' => 'yalova kamera kurulumu, güvenlik kameraları, alarm sistemleri yalova, kamera kurulumu',

            'header_h1' => 'Hakımızda',

            // About Us
            'about_heading' => 'Hakkımızda',
            'about_subheading_span' => 'Yalova’da güvenliğinizi',
            'about_subheading_rest' => 'profesyonel çözümlerle koruyoruz',
            'about_paragraph' => 'Yalova Kamera Sistemleri olarak Yalova ilinde kamera ve alarm sistemleri satış, kurulum, projelendirme, bakım ve onarım servisi hizmetleri vermekteyiz. Ev, iş yeri, ofis, mağaza, apartman ve sanayi alanları için ihtiyaca özel güvenlik çözümleri sunuyor; kaliteli ürün, uzman işçilik ve hızlı teknik destek anlayışıyla çalışıyoruz.',
            'about_experience_heading' => 'Müşteri memnuniyeti odaklı, garantili ve profesyonel güvenlik sistemleri hizmeti sunuyoruz',
            'about_contact_prompt' => 'Bize Hemen Ulaşın',
            'about_phone_text' => '0226 352 07 24',
            'about_contact_button_text' => 'İletişime Geçin',

            // Mission & Vision
            'mission_heading' => 'Misyonumuz',
            'mission_text' => 'Müşterilerimize ihtiyaçlarına en uygun kamera ve alarm sistemlerini sunarak yaşam ve çalışma alanlarını daha güvenli hale getirmek; kaliteli ürün, doğru projelendirme ve güvenilir teknik servis ile kalıcı memnuniyet sağlamaktır.',
            'vision_heading' => 'Vizyonumuz',
            'vision_text' => 'Yalova’da güvenlik sistemleri alanında güvenilirliği, teknik yeterliliği ve hizmet kalitesiyle öne çıkan, müşterilerin ilk tercih ettiği lider firmalardan biri olmaktır.',
            'goal_heading' => 'Hedefimiz',
            'goal_text' => 'Her projede maksimum verim, uzun ömürlü sistem kullanımı ve hızlı servis desteği sunarak bireysel ve kurumsal müşterilerimize eksiksiz güvenlik çözümleri sağlamaktır.',

            // Why choose
            'why_choose_heading' => 'Neden Bizi Tercih Etmelisiniz?',
            'why_choose_subheading_span' => 'Uzman ekip,',
            'why_choose_subheading_rest' => 'kaliteli ürün ve güvenilir servis',
            'why_choose_item_1_title' => 'Hızlı Teknik Destek',
            'why_choose_item_1_text' => 'Kurulum sonrası bakım, arıza tespiti ve onarım süreçlerinde hızlı ve çözüm odaklı destek sağlıyoruz.',
            'why_choose_item_2_title' => 'İhtiyaca Özel Çözümler',
            'why_choose_item_2_text' => 'Ev, iş yeri, apartman, mağaza ve fabrika gibi farklı alanlara özel projelendirme yapıyoruz.',
            'why_choose_item_3_title' => 'Uzaktan Erişim',
            'why_choose_item_3_text' => 'Mobil cihazlardan izleme imkânı sunan modern kamera sistemleri ile her yerden kontrol sağlayabilirsiniz.',
            'why_choose_item_4_title' => 'Garantili Hizmet',
            'why_choose_item_4_text' => 'Tüm hizmetlerimiz garantili olup müşteri memnuniyeti odaklı çalışma prensibiyle sunulmaktadır.',

            // Commitment
            'commitment_heading' => 'Taahhüdümüz',
            'commitment_subheading_span' => 'Güvenlikte kaliteyi',
            'commitment_subheading_rest' => 've sürekliliği sağlıyoruz',
            'commitment_paragraph' => 'Yalova Kamera Sistemleri olarak her projede doğru keşif, doğru ürün seçimi ve doğru montaj ilkesiyle hareket ediyoruz. Sadece satış değil, satış sonrası teknik destek ve sürdürülebilir hizmet anlayışı da sunuyoruz.',
            'satisfy_client_text' => 'Memnun Müşteri ve Tamamlanan Güvenlik Çözümü',
            'commitment_counter_item_1' => 'Kurulum Hizmeti',
            'commitment_counter_item_2' => 'Teknik Servis Desteği',
            'commitment_counter_item_3' => 'Müşteri Memnuniyeti',
            'commitment_list_item_1' => 'Kaliteli ürün, profesyonel kurulum ve garantili hizmet sunuyoruz.',
            'commitment_list_item_2' => 'Kamera, alarm ve güvenlik sistemlerinde uzun ömürlü çözümler sağlıyoruz.',

            // Expertise
            'expertise_heading' => 'Uzmanlığımız',
            'expertise_subheading_span' => 'Akıllı güvenlik çözümleri',
            'expertise_subheading_rest' => 'ile maksimum koruma',
            'expertise_paragraph' => 'Projeye uygun kamera sistemleri, alarm altyapıları, kayıt cihazları, görüntüleme çözümleri ve bakım servisleri ile kapsamlı güvenlik hizmetleri sunuyoruz.',
            'expertise_item_1_title' => 'Modern Teknoloji',
            'expertise_item_1_text' => 'Güncel güvenlik sistemleri ile yüksek performanslı çözümler sunuyoruz.',
            'expertise_item_2_title' => 'Profesyonel Uygulama',
            'expertise_item_2_text' => 'Keşif, projelendirme, montaj ve servis süreçlerini titizlikle yürütüyoruz.',
            'expertise_counter_label' => 'Destek ve Servis Yaklaşımı',
            'expertise_counter_list_item_1' => 'Kamera sistemleri kurulumu',
            'expertise_counter_list_item_2' => 'Alarm sistemleri teknik servis hizmeti',
            'expertise_contact_heading' => 'Hemen Arayın',
            'expertise_phone_text' => '0226 352 07 24',

            // What we do
            'what_we_do_heading' => 'Ne Yapıyoruz?',
            'what_we_do_subheading_span' => 'Güvenlik ve izleme',
            'what_we_do_subheading_rest' => 'sistemlerinde profesyonel hizmet veriyoruz',
            'what_we_do_paragraph_1' => 'Yalova Kamera Sistemleri olarak güvenlik kamerası ve alarm sistemleri satışından kurulumuna, projelendirmeden bakım ve onarıma kadar tüm süreçlerde profesyonel hizmet vermekteyiz.',
            'what_we_do_paragraph_2' => 'Her mekânın güvenlik ihtiyacının farklı olduğunu biliyor, buna göre özel çözümler geliştiriyoruz. Kullandığımız sistemler yüksek görüntü kalitesi, güvenilir performans ve kolay kullanım avantajı sunar. Amacımız müşterilerimize uzun ömürlü, verimli ve sorunsuz güvenlik altyapısı sağlamaktır.',
            'need_help_text' => 'Hizmetlerimiz Hakkında Bilgi Alın',
            'what_we_do_phone_text' => '0226 352 07 24',
            'what_we_counter_label_1' => 'Teknik Destek Yaklaşımı',
            'what_we_counter_label_2' => 'Tamamlanan Hizmet',

            // Team
            'team_heading' => 'Uzman Ekibimiz',
            'team_subheading_span' => 'Güvenliğiniz için çalışan',
            'team_subheading_rest' => 'profesyonel kadro',
            'team_paragraph' => 'Kurulum, bakım, onarım ve teknik destek süreçlerinde deneyimli ekibimizle Yalova’da profesyonel güvenlik sistemleri hizmeti sunuyoruz.',
            'team_button_text' => 'Tüm Ekibi Gör',
            'team_title_1' => 'Teknik Destek Ekibi',
            'team_text_1' => 'Kurulum ve Servis Uzmanı',
            'team_title_2' => 'Projelendirme Ekibi',
            'team_text_2' => 'Güvenlik Sistemleri Uzmanı',
            'team_title_3' => 'Bakım Onarım Ekibi',
            'team_text_3' => 'Sistem Kontrol Uzmanı',
            'team_title_4' => 'Müşteri Destek Ekibi',
            'team_text_4' => 'Destek ve Koordinasyon',

            // Support
            'support_heading' => 'Teknik Destek',
            'support_subheading_span' => 'Her zaman ulaşılabilir',
            'support_subheading_rest' => 'güvenilir teknik servis',
            'support_paragraph' => 'Sistemlerinizin sorunsuz çalışması için bakım, kontrol, arıza tespiti ve onarım hizmetlerini profesyonel şekilde sunuyoruz.',
            'support_item_1_title' => 'Kamera Sistemi Kurulumu',
            'support_item_1_text' => 'İç ve dış mekânlara uygun profesyonel kamera sistemleri kurulum yapıyoruz.',
            'support_item_2_title' => 'Alarm Sistemleri Servisi',
            'support_item_2_text' => 'Alarm sistemleri için kurulum, kontrol, bakım ve onarım desteği veriyoruz.',
            'support_button_text' => 'Bizimle İletişime Geçin',

            // Testimonials
            'testimonials_heading' => 'Müşteri Yorumları',
            'testimonials_subheading_span' => 'Müşterilerimizin',
            'testimonials_subheading_rest' => 'bize duyduğu güven',
            'testimonial_name_1' => 'Ahmet Y.',
            'testimonial_role_1' => 'İşyeri Sahibi',
            'testimonial_text_1' => 'İşyerimiz için kamera ve alarm sistemi kurulumu yaptırdık. Hem kurulum süreci hem de sonrasında verilen destekten çok memnun kaldık. Güvenilir ve profesyonel bir firma.',
            'testimonial_name_2' => 'Mehmet K.',
            'testimonial_role_2' => 'Apartman Yöneticisi',
            'testimonial_text_2' => 'Apartmanımız için yapılan kamera sistemi kurulumu çok başarılı oldu. Görüntü kalitesi çok iyi, ekip ilgili ve işini düzgün yapıyor. Tavsiye ederim.',
            'testimonial_name_3' => 'Ayşe T.',
            'testimonial_role_3' => 'Ev Sahibi',
            'testimonial_text_3' => 'Evimize kurulan güvenlik sistemi sayesinde içimiz çok daha rahat. Sorularımıza hızlı cevap verildi, montaj temiz ve düzenli yapıldı. Müşteri memnuniyeti gerçekten ön planda.',

            // CTA
            'cta_heading' => 'İletişim',
            'cta_subheading_span' => 'Güvenliğiniz için',
            'cta_subheading_rest' => 'doğru adres: Yalova Kamera Sistemleri',
            'cta_paragraph' => 'Kamera ve alarm sistemleri hakkında bilgi almak, keşif talep etmek veya teklif istemek için hemen bizimle iletişime geçin.',
            'cta_phone_label' => 'Telefon Numarası',
            'cta_phone_text' => '0226 352 07 24',
            'cta_mail_label' => 'E-posta Adresi',
            'cta_mail_text' => 'info@yalovakamera.com',

            // FAQs
            'faqs_heading' => 'Sık Sorulan Sorular',
            'faqs_subheading_span' => 'Merak edilen',
            'faqs_subheading_rest' => 'sorular ve cevaplar',
            'faqs_paragraph' => 'Kamera ve alarm sistemleri, kurulum süreçleri, bakım hizmetleri ve teknik destek hakkında sık sorulan soruların cevaplarını burada bulabilirsiniz.',
            'faqs_list_item_1' => 'Yüksek çözünürlüklü görüntü sistemleri',
            'faqs_list_item_2' => 'Uzaktan izleme ve kontrol çözümleri',
            'faqs_list_item_3' => 'Profesyonel kurulum ve teknik servis desteği',

            'faq_q_1' => 'Hangi tür kamera sistemleri sunuyorsunuz?',
            'faq_a_1' => 'İhtiyaca göre iç ve dış mekân güvenlik kameraları, kayıt cihazları, IP kamera sistemleri ve uzaktan izleme destekli çözümler sunuyoruz.',
            'faq_q_2' => 'Kamera görüntülerine telefondan erişebilir miyim?',
            'faq_a_2' => 'Evet. Kurulan sisteme göre cep telefonu, tablet veya bilgisayar üzerinden canlı izleme ve geçmiş kayıtları görüntüleme imkânı sunulmaktadır.',
            'faq_q_3' => 'Kurulum hizmeti veriyor musunuz?',
            'faq_a_3' => 'Evet. Satışını yaptığımız tüm sistemler için keşif, projelendirme, kurulum ve devreye alma hizmeti veriyoruz.',
            'faq_q_4' => 'Bakım ve onarım servisi sağlıyor musunuz?',
            'faq_a_4' => 'Evet. Mevcut kamera ve alarm sistemleri için arıza tespiti, periyodik bakım, onarım ve sistem iyileştirme hizmetleri sunuyoruz.',

            'show_header_section' => true,
            'show_about_section' => true,
            'show_mission_vision_section' => true,
            'show_why_choose_section' => true,
            'show_commitment_section' => true,
            'show_expertise_section' => true,
            'show_what_we_do_section' => true,
            'show_team_section' => true,
            'show_support_section' => true,
            'show_testimonials_section' => true,
            'show_cta_section' => true,
            'show_faq_section' => true,

            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Singleton kayıt.
     */
    public static function instance(): self
    {
        if (self::$runtimeInstance instanceof self) {
            return self::$runtimeInstance;
        }

        return self::$runtimeInstance = Cache::remember(
            self::INSTANCE_CACHE_KEY,
            3600,
            fn (): self => static::firstOrCreate([], static::defaults())
        );
    }

    public static function cached(): ?self
    {
        if (self::$runtimeFirstLoaded) {
            return self::$runtimeFirst;
        }

        self::$runtimeFirstLoaded = true;
        self::$runtimeFirst = Cache::remember(
            self::FIRST_CACHE_KEY,
            3600,
            fn (): ?self => static::query()->first()
        );

        return self::$runtimeFirst;
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            self::$runtimeInstance = null;
            self::$runtimeFirstLoaded = false;
            self::$runtimeFirst = null;
            Cache::forget(self::INSTANCE_CACHE_KEY);
            Cache::forget(self::FIRST_CACHE_KEY);
        });

        static::deleted(static function (): void {
            self::$runtimeInstance = null;
            self::$runtimeFirstLoaded = false;
            self::$runtimeFirst = null;
            Cache::forget(self::INSTANCE_CACHE_KEY);
            Cache::forget(self::FIRST_CACHE_KEY);
        });
    }
}

