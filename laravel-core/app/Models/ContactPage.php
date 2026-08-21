<?php

namespace App\Models;

use App\Support\SaaSemaYardimcisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContactPage extends Model
{
    private const INSTANCE_CACHE_KEY = 'contact_page.instance';
    private static ?self $runtimeInstance = null;

    protected $table = 'contact_pages';

    protected $fillable = [
        'meta_title',
        'meta_description',
        'meta_keywords',
        'header_heading',
        'section_heading',
        'section_subheading',
        'section_intro',
        'phone',
        'email',
        'address',
        'map_url',
        'form_heading',
        'form_intro',
        'whatsapp_url',
        'instagram_url',
        'linkedin_url',
        'pinterest_url',
        'twitter_url',
        'facebook_url',
    ];

    /**
     * Tek satır kullanıldığı için singleton kaydı döndürür (yoksa varsayılanla oluşturur).
     */
    public static function instance(): self
    {
        $tabloVarMi = SaaSemaYardimcisi::tabloVarMi('contact_pages');

        if (self::$runtimeInstance instanceof self && (! $tabloVarMi || self::$runtimeInstance->exists)) {
            return self::$runtimeInstance;
        }

        if (! $tabloVarMi) {
            return self::$runtimeInstance = new self(self::varsayilanlar());
        }

        self::$runtimeInstance = Cache::remember(self::INSTANCE_CACHE_KEY, 3600, static function (): self {
            $defaults = self::varsayilanlar();

            return self::firstOrCreate([], $defaults);
        });

        return self::$runtimeInstance;
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::$runtimeInstance = null;
            Cache::forget(self::INSTANCE_CACHE_KEY);
        });
        static::deleted(function (): void {
            self::$runtimeInstance = null;
            Cache::forget(self::INSTANCE_CACHE_KEY);
        });
    }

    /**
     * @return array<string, string>
     */
    private static function varsayilanlar(): array
    {
        $defaults = [
            'meta_title' => 'İletişim | Yalova Kamera Sistemleri',
            'meta_description' => 'Yalova kamera kurulumu konusunda uzman ekibimiz. Güvenlik kamerası ve alarm sistemi kurulumu, servis ve bakım hizmetlerinde yılların deneyimi.',
            'meta_keywords' => 'yalova kamera kurulumu telefon, yalova güvenlik sistemi ara, yalova kamera fiyatları, yalova alarm sistemi yapan firmalar',
            'header_heading' => 'İletişim',
            'section_heading' => 'İletişim',
            'section_subheading' => 'Güvenliğinizi bizimle sağlayın',
            'section_intro' => 'Sorularınız mı var veya güvenlik sistemleri hakkında bilgi mi almak istiyorsunuz? Ekibimiz size yardımcı olmaktan memnuniyet duyar.',
            'phone' => '0 (226) 352 07 24',
            'email' => 'info@yalovakamera.com',
            'address' => 'Çiftlikköy / Yalova',
            'map_url' => 'https://www.google.com/maps?q=Yalova%20Çiftlikköy&output=embed',
            'form_heading' => 'Mesaj Gönder',
            'form_intro' => 'Güvenlik sistemleri hakkında bilgi almak için bize mesaj gönderebilirsiniz.',
        ];

        foreach ([
            'whatsapp_url' => '',
            'instagram_url' => '',
            'linkedin_url' => '',
            'pinterest_url' => '',
            'twitter_url' => '',
            'facebook_url' => '',
        ] as $key => $value) {
            if (! SaaSemaYardimcisi::tabloVarMi('contact_pages') || SaaSemaYardimcisi::kolonVarMi('contact_pages', $key)) {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}
