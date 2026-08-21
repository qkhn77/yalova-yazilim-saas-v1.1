@php
    $footerContact = \App\Models\ContactPage::instance();
    $footerSettings = \App\Models\Setting::getMany([
        'footer_title' => 'Yalova Kamera Sistemleri',
        'footer_description' => __('front.footer.default_description'),
        'newsletter_title' => 'Abone Ol',
        'newsletter_description' => __('front.footer.newsletter_hint'),
        'footer_logo' => '',
        'site_title' => config('app.name'),
        'copyright_text' => '© ' . date('Y') . ' Yalova Kamera Sistemleri. Tum haklari saklidir.',
    ]);
    $footerTitle = $footerSettings['footer_title'];
    $footerDescription = $footerSettings['footer_description'];
    $newsletterTitle = $footerSettings['newsletter_title'];
    $newsletterDescription = $footerSettings['newsletter_description'];
    $newsletterRecaptchaEtkin = \App\Support\RecaptchaAyarlari::etkinMi();
    $newsletterRecaptchaSiteKey = \App\Support\RecaptchaAyarlari::siteKey();
    $footerLogo = $footerSettings['footer_logo'];
    $footerLogoUrl = $footerLogo
        ? (str_starts_with($footerLogo, 'settings/') ? asset('uploads/' . ltrim($footerLogo, '/')) : asset($footerLogo))
        : asset('theme/yalovakamera/images/footer-logo.svg');
    $newsletterErrors = $errors->newsletter ?? new \Illuminate\Support\ViewErrorBag();
    $footerBilgiSayfalari = collect();
    $footerPolicyPages = ['support' => null, 'privacy' => null, 'terms' => null];
    if (\Illuminate\Support\Facades\Schema::hasTable('info_pages')) {
        $footerBilgiSayfalari = \Illuminate\Support\Facades\Cache::remember('front.footer.info_pages', 600, function () {
            return \App\Models\BilgiSayfa::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->limit(8)
                ->get(['title', 'slug']);
        });
        $footerPolicyPages = \Illuminate\Support\Facades\Cache::remember('front.footer.policy_pages', 600, function () {
            return [
                'support' => \App\Models\BilgiSayfa::query()->where('is_active', true)->where('slug', 'destek')->first(['slug']),
                'privacy' => \App\Models\BilgiSayfa::query()->where('is_active', true)->where('slug', 'gizlilik-politikasi')->first(['slug']),
                'terms' => \App\Models\BilgiSayfa::query()->where('is_active', true)->whereIn('slug', ['kullanim-sartlari', 'kullanim-kosullari'])->first(['slug']),
            ];
        });
    }
@endphp

<footer class="main-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="main-footer-box">
                    <div class="footer-logo">
                        @if($frontTheme->is('software') && ! $footerLogo)
                            <div class="software-footer-brand"><span class="software-brand-mark" aria-hidden="true">&lt;/&gt;</span><strong>{{ $footerSettings['site_title'] }}</strong></div>
                        @else
                            <img src="{{ $footerLogoUrl }}" alt="{{ $footerSettings['site_title'] }}" loading="lazy" decoding="async">
                        @endif
                    </div>

                    <div class="footer-contact-details">
                        <div class="footer-contact-item">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-phone.svg') }}" alt="" loading="lazy" decoding="async">
                            </div>
                            <div class="footer-contact-item-content">
                                <p>{{ __('front.footer.phone') }}</p>
                                <h3>
                                    @if (! empty($footerContact->phone))
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $footerContact->phone) }}">{{ $footerContact->phone }}</a>
                                    @else
                                        -
                                    @endif
                                </h3>
                            </div>
                        </div>

                        <div class="footer-contact-item">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-mail.svg') }}" alt="" loading="lazy" decoding="async">
                            </div>
                            <div class="footer-contact-item-content">
                                <p>{{ __('front.footer.email') }}</p>
                                <h3>
                                    @if (! empty($footerContact->email))
                                        <a href="mailto:{{ $footerContact->email }}">{{ $footerContact->email }}</a>
                                    @else
                                        -
                                    @endif
                                </h3>
                            </div>
                        </div>

                        <div class="footer-contact-item">
                            <div class="icon-box">
                                <img src="{{ asset('theme/yalovakamera/images/icon-location.svg') }}" alt="" loading="lazy" decoding="async">
                            </div>
                            <div class="footer-contact-item-content">
                                <p>{{ __('front.footer.address') }}</p>
                                <h3>{{ $footerContact->address ?: '-' }}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="about-footer">
                    <div class="footer-links">
                        <h3>{{ $footerTitle }}</h3>
                        <p>{{ $footerDescription }}</p>
                    </div>

                    <div class="footer-social-links">
                        <ul>
                            @if (! empty($footerContact->instagram_url))
                                <li><a href="{{ $footerContact->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                            @endif
                            @if (! empty($footerContact->pinterest_url))
                                <li><a href="{{ $footerContact->pinterest_url }}" target="_blank" rel="noopener" aria-label="Pinterest"><i class="fa-brands fa-pinterest-p"></i></a></li>
                            @endif
                            @if (! empty($footerContact->twitter_url))
                                <li><a href="{{ $footerContact->twitter_url }}" target="_blank" rel="noopener" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a></li>
                            @endif
                            @if (! empty($footerContact->facebook_url))
                                <li><a href="{{ $footerContact->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                            @endif
                            @if (! empty($footerContact->linkedin_url))
                                <li><a href="{{ $footerContact->linkedin_url }}" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a></li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <div class="footer-links">
                    <h3>{{ __('front.footer.quick_menu') }}</h3>
                    <ul>
                        <li><a href="{{ route('home') }}">{{ __('front.menu.home') }}</a></li>
                        <li><a href="{{ route('about') }}">{{ __('front.menu.about') }}</a></li>
                        <li><a href="{{ route('services.index') }}">{{ __('front.menu.services') }}</a></li>
                        <li><a href="{{ route('blog.index') }}">{{ __('front.menu.blogs') }}</a></li>
                        <li><a href="{{ route('contact') }}">{{ __('front.menu.contact') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <div class="footer-links">
                    <h3>{{ __('front.footer.info_pages') }}</h3>
                    <ul>
                        @forelse ($footerBilgiSayfalari as $footerBilgi)
                            <li>
                                <a href="{{ route('information.show', $footerBilgi->slug) }}">
                                    {{ $footerBilgi->title }}
                                </a>
                            </li>
                        @empty
                            <li><a href="{{ route('information.index') }}">{{ __('front.footer.info_center') }}</a></li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="newsletter-form footer-links">
                    <h3>{{ $newsletterTitle }}</h3>
                    <p>{{ $newsletterDescription }}</p>

                    @if (session('newsletter_success'))
                        <div class="alert alert-success mb-3">{{ session('newsletter_success') }}</div>
                    @endif

                    @if (session('newsletter_error'))
                        <div class="alert alert-danger mb-3">{{ session('newsletter_error') }}</div>
                    @endif

                    <div class="form-group">
                        <input
                            type="email"
                            id="newsletterTriggerEmail"
                            class="form-control"
                            placeholder="{{ __('front.footer.newsletter_email_placeholder') }}"
                            value="{{ old('newsletter_email') }}"
                            required
                        >
                        <button type="button" class="newsletter-btn" id="newsletterTriggerButton" aria-label="{{ __('front.footer.newsletter_subscribe_aria') }}">
                            <i class="fa-regular fa-paper-plane"></i>
                        </button>
                    </div>

                    <small style="display:block; margin-top: 10px; color:#8a8a8a;">
                        {{ __('front.footer.newsletter_hint') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-copyright">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="footer-copyright-text">
                        <p>{{ $footerSettings['copyright_text'] }}</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="footer-privacy-policy">
                        @php
                            $supportPage = $footerPolicyPages['support'] ?? null;
                            $privacyPage = $footerPolicyPages['privacy'] ?? null;
                            $termsPage = $footerPolicyPages['terms'] ?? null;
                        @endphp
                        <ul>
                            <li><a href="{{ $supportPage ? route('information.show', $supportPage->slug) : route('information.index') }}">{{ __('front.footer.support') }}</a></li>
                            <li><a href="{{ $privacyPage ? route('information.show', $privacyPage->slug) : route('information.index') }}">{{ __('front.footer.privacy_policy') }}</a></li>
                            <li><a href="{{ $termsPage ? route('information.show', $termsPage->slug) : route('information.index') }}">{{ __('front.footer.terms') }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<div id="newsletterModal" class="newsletter-modal" aria-hidden="true" data-open-on-load="{{ $newsletterErrors->any() ? '1' : '0' }}">
    <div class="newsletter-modal__backdrop"></div>
    <div class="newsletter-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="newsletterModalTitle">
        <button type="button" class="newsletter-modal__close" id="newsletterModalClose" aria-label="{{ __('front.footer.close') }}">×</button>
        <h4 id="newsletterModalTitle">{{ __('front.footer.complete_subscription') }}</h4>
        <p>{{ __('front.footer.subscription_verify_text') }}</p>

        @if ($newsletterErrors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0">
                    @foreach ($newsletterErrors->all() as $newsletterError)
                        <li>{{ $newsletterError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="newsletterModalForm" action="{{ route('newsletter.subscribe') }}" method="POST">
            @csrf
            <div class="newsletter-modal__field">
                <label for="newsletterModalEmail">{{ __('front.footer.newsletter_email_label') }}</label>
                <input
                    type="email"
                    id="newsletterModalEmail"
                    name="newsletter_email"
                    value="{{ old('newsletter_email') }}"
                    readonly
                    required
                >
            </div>

            @if ($newsletterRecaptchaEtkin)
                <div class="newsletter-modal__captcha">
                    <div class="g-recaptcha" data-sitekey="{{ $newsletterRecaptchaSiteKey }}"></div>
                </div>
            @endif

            <button type="submit" class="btn-default newsletter-modal__submit">{{ __('front.footer.complete_subscription') }}</button>
        </form>
    </div>
</div>

@if ($newsletterRecaptchaEtkin)
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
