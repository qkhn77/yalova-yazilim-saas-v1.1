@extends('front.layouts.app')

@section('title','Hakkımızda | Yalova Kamera Sistemleri')
@section('meta_description','Yalova kamera kurulumu konusunda uzman ekibimiz. Güvenlik kamerası ve alarm sistemi kurulumu, servis ve bakım hizmetlerinde yılların deneyimi.')
@section('meta_keywords','yalova kamera kurulumu, yalova güvenlik sistemi, yalova kamera fiyatları, yalova alarm sistemi')

@php
    \App\Helpers\BreadcrumbHelper::clear();
    \App\Helpers\BreadcrumbHelper::add('Anasayfa', '/');
    \App\Helpers\BreadcrumbHelper::add('Servisler');
@endphp

@section('content')
@php
    $about = rescue(fn () => \App\Models\AboutPage::cached(), null, report: false);

    $defaults = \App\Models\AboutPage::defaults();

    $val = function (string $key) use ($about, $defaults): string {
        $v = $about?->{$key};
        if ($v === null || $v === '') {
            return (string) ($defaults[$key] ?? '');
        }
        return (string) $v;
    };

    $tel = function (string $phone): string {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        return 'tel:' . $digits;
    };

    $img = function (string $field, string $defaultFile) use ($about): string {
        if ($about && !empty($about->{$field})) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($about->{$field});
        }
        return asset('theme/yalovakamera/images/' . $defaultFile);
    };

    $visible = function (string $key) use ($about, $defaults): bool {
        $value = $about?->{$key};
        if ($value === null || $value === '') {
            $value = $defaults[$key] ?? true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    };
@endphp
    @if($visible('show_header_section'))
    <div class="page-header">
        <div class="container">


            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ $val('header_h1') }}</h1>
             {!! \App\Helpers\BreadcrumbHelper::render() !!}
            </div>

        </div>
    </div>
    @endif

    <!-- Preloader Start -->
	<div class="preloader">
		<div class="loading-container">
			<div class="loading"></div>
            @php
                $loaderLogo = \App\Models\Setting::get('loader_logo');
                $loaderLogoUrl = $loaderLogo
                    ? (str_starts_with($loaderLogo, 'settings/') ? asset('uploads/' . ltrim($loaderLogo, '/')) : asset($loaderLogo))
                    : asset('theme/yalovakamera/images/loader.svg');
            @endphp
			<div id="loading-icon"><img src="{{ $loaderLogoUrl }}" alt=""></div>
		</div>
	</div>
	<!-- Preloader End -->

	<!-- Header End -->



    @if($visible('show_about_section'))
    <!-- About Us Section Start -->
    <div class="about-us page-about-us">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <!-- About Us Images Start -->
                    <div class="about-us-images">
                        <div class="about-img-1">
                            <figure class="image-anime reveal">
                                <img src="{{ $img('about_img_1', 'about-img-1.jpg') }}" alt="Yalova Kamera Sistemleri">
                            </figure>

                            <div class="company-experience-circle">
                                <img src="{{ $img('about_circle_icon', 'experience-circle.svg') }}" alt="">
                            </div>
                        </div>

                        <div class="about-img-2">
                            <figure class="image-anime reveal">
                                <img src="{{ $img('about_img_2', 'about-img-2.jpg') }}" alt="Güvenlik Kamera Sistemleri">
                            </figure>
                        </div>
                    </div>
                    <!-- About Us Images End -->
                </div>

                <div class="col-lg-6">
                    <div class="about-us-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('about_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('about_subheading_span') }}</span> {{ $val('about_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('about_paragraph') }}</p>
                        </div>

                        <div class="about-experience-box wow fadeInUp" data-wow-delay="0.6s">
                            <div class="about-experience-image">
                                <figure class="image-anime reveal">
                                    <img src="{{ $img('about_experience_image', 'about-experience-image.jpg') }}" alt="Tecrübeli Güvenlik Hizmetleri">
                                </figure>
                            </div>

                            <div class="about-experience-item">
                                <div class="icon-box">
                                    <img src="{{ $img('about_icon_experience', 'icon-about-experience.svg') }}" alt="">
                                </div>
                                <div class="about-experience-content">
                                    <h3>{{ $val('about_experience_heading') }}</h3>
                                </div>
                            </div>
                        </div>

                        <div class="about-us-body wow fadeInUp" data-wow-delay="0.8s">
                            <div class="about-contact-box">
                                <div class="icon-box">
                                    <img src="{{ $img('about_icon_contact', 'icon-about-contact.svg') }}" alt="">
                                </div>
                                <div class="about-contact-box-content">
                                    <p>{{ $val('about_contact_prompt') }}</p>
                                    <h3><a href="{{ $tel($val('about_phone_text')) }}">{{ $val('about_phone_text') }}</a></h3>
                                </div>
                            </div>

                            <div class="about-us-btn">
                                <a href="contact.html" class="btn-default">{{ $val('about_contact_button_text') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- About Us Section End -->
    @endif

    @if($visible('show_mission_vision_section'))
    <!-- Our Mission Vision Section Start -->
    <div class="our-mission-vision dark-section">
        <div class="container-fluid">
            <div class="row no-gutters">
                <div class="col-lg-6">
                    <div class="mission-vision-image">
                        <figure class="image-anime">
                            <img src="{{ $img('mission_vision_image', 'mission-vision-img.jpg') }}" alt="Misyon ve Vizyon">
                        </figure>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mission-vision-content">
                        <div class="mission-vision-item wow fadeInUp" data-wow-delay="0.2s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_mission', 'icon-mission.svg') }}" alt="">
                            </div>
                            <div class="mission-vision-item-content">
                                <h3>{{ $val('mission_heading') }}</h3>
                                <p>{{ $val('mission_text') }}</p>
                            </div>
                        </div>

                        <div class="mission-vision-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_vision', 'icon-vision.svg') }}" alt="">
                            </div>
                            <div class="mission-vision-item-content">
                                <h3>{{ $val('vision_heading') }}</h3>
                                <p>{{ $val('vision_text') }}</p>
                            </div>
                        </div>

                        <div class="mission-vision-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_goal', 'icon-goal.svg') }}" alt="">
                            </div>
                            <div class="mission-vision-item-content">
                                <h3>{{ $val('goal_heading') }}</h3>
                                <p>{{ $val('goal_text') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Mission Vision Section End -->
    @endif

    @if($visible('show_why_choose_section'))
    <!-- Why Choose Us Section Start -->
    <div class="why-choose-us">
        <div class="container">
            <div class="row section-row align-items-center">
                <div class="col-lg-12">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ $val('why_choose_heading') }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('why_choose_subheading_span') }}</span> {{ $val('why_choose_subheading_rest') }}</h2>
                    </div>
                </div>
            </div>

            <div class="row align-items-center">
                <div class="col-lg-3">
                    <div class="why-choose-box">
                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ $img('why_choose_icon_1', 'icon-why-choose-1.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $val('why_choose_item_1_title') }}</h3>
                                <p>{{ $val('why_choose_item_1_text') }}</p>
                            </div>
                        </div>

                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="icon-box">
                                <img src="{{ $img('why_choose_icon_2', 'icon-why-choose-2.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $val('why_choose_item_2_title') }}</h3>
                                <p>{{ $val('why_choose_item_2_text') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="why-choose-image">
                        <figure>
                            <img src="{{ $img('why_choose_image', 'why-choose-image.png') }}" alt="Yalova Kamera Sistemleri Hizmetleri">
                        </figure>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="why-choose-box">
                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ $img('why_choose_icon_3', 'icon-why-choose-3.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $val('why_choose_item_3_title') }}</h3>
                                <p>{{ $val('why_choose_item_3_text') }}</p>
                            </div>
                        </div>

                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="icon-box">
                                <img src="{{ $img('why_choose_icon_4', 'icon-why-choose-4.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $val('why_choose_item_4_title') }}</h3>
                                <p>{{ $val('why_choose_item_4_text') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Why Choose Us Section End -->
    @endif

    @if($visible('show_commitment_section'))
    <!-- Our Commitment Section Start -->
    <div class="our-commitment">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="our-commitment-images">
                        <div class="our-commitment-img-box">
                            <div class="commitment-image-1">
                                <figure class="image-anime reveal">
                                    <img src="{{ $img('commitment_image_1', 'commitment-image-1.jpg') }}" alt="Profesyonel Güvenlik Hizmetleri">
                                </figure>
                            </div>

                            <div class="satisfy-client-box">
                                <div class="satisfy-client-content">
                                    <h2><span class="counter">100</span>+</h2>
                                    <p>{{ $val('satisfy_client_text') }}</p>
                                </div>

                                <div class="satisfy-client-images">
                                    <div class="satisfy-client-image">
                                        <figure class="image-anime reveal">
                                            <img src="{{ $img('satisfy_client_img_1', 'satisfy-client-img-1.jpg') }}" alt="">
                                        </figure>
                                    </div>
                                    <div class="satisfy-client-image">
                                        <figure class="image-anime reveal">
                                            <img src="{{ $img('satisfy_client_img_2', 'satisfy-client-img-2.jpg') }}" alt="">
                                        </figure>
                                    </div>
                                    <div class="satisfy-client-image">
                                        <figure class="image-anime reveal">
                                            <img src="{{ $img('satisfy_client_img_3', 'satisfy-client-img-3.jpg') }}" alt="">
                                        </figure>
                                    </div>
                                    <div class="satisfy-client-image">
                                        <figure class="image-anime reveal">
                                            <img src="{{ $img('satisfy_client_img_4', 'satisfy-client-img-4.jpg') }}" alt="">
                                        </figure>
                                    </div>
                                    <div class="satisfy-client-image">
                                        <figure class="image-anime reveal">
                                            <img src="{{ $img('satisfy_client_img_5', 'satisfy-client-img-5.jpg') }}" alt="">
                                        </figure>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="our-commitment-img-box">
                            <div class="commitment-image-2">
                                <figure class="image-anime reveal">
                                    <img src="{{ $img('commitment_image_2', 'commitment-image-2.jpg') }}" alt="Kamera ve Alarm Sistemleri">
                                </figure>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="our-commitment-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('commitment_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('commitment_subheading_span') }}</span> {{ $val('commitment_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('commitment_paragraph') }}</p>
                        </div>
                    </div>

                    <div class="commitment-counter-box">
                        <div class="commitment-counter-item">
                            <h2><span class="counter">100</span>+</h2>
                            <p>{{ $val('commitment_counter_item_1') }}</p>
                        </div>

                        <div class="commitment-counter-item">
                            <h2><span class="counter">100</span>+</h2>
                            <p>{{ $val('commitment_counter_item_2') }}</p>
                        </div>

                        <div class="commitment-counter-item">
                            <h2><span class="counter">100</span>+</h2>
                            <p>{{ $val('commitment_counter_item_3') }}</p>
                        </div>
                    </div>

                    <div class="commitment-list wow fadeInUp" data-wow-delay="0.6s">
                        <ul>
                            <li>{{ $val('commitment_list_item_1') }}</li>
                            <li>{{ $val('commitment_list_item_2') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Commitment Section End -->
    @endif

    @if($visible('show_expertise_section'))
    <!-- Our Expertise Section Start -->
    <div class="our-expertise">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="our-expertise-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('expertise_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('expertise_subheading_span') }}</span> {{ $val('expertise_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('expertise_paragraph') }}</p>
                        </div>

                        <div class="our-expertise-body">
                            <div class="expertise-item-box">
                                <div class="expertise-item">
                                    <div class="icon-box">
                                        <img src="{{ $img('expertise_icon_1', 'icon-expertise-item-1.svg') }}" alt="">
                                    </div>
                                    <div class="expertise-item-content">
                                        <h3>{{ $val('expertise_item_1_title') }}</h3>
                                        <p>{{ $val('expertise_item_1_text') }}</p>
                                    </div>
                                </div>

                                <div class="expertise-item">
                                    <div class="icon-box">
                                        <img src="{{ $img('expertise_icon_2', 'icon-expertise-item-2.svg') }}" alt="">
                                    </div>
                                    <div class="expertise-item-content">
                                        <h3>{{ $val('expertise_item_2_title') }}</h3>
                                        <p>{{ $val('expertise_item_2_text') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="expertise-counter-box">
                                <div class="expertise-counter-content">
                                    <h2><span class="counter">24</span>/7</h2>
                                    <p>{{ $val('expertise_counter_label') }}</p>
                                </div>

                                <div class="expertise-counter-list">
                                    <ul>
                                        <li>{{ $val('expertise_counter_list_item_1') }}</li>
                                        <li>{{ $val('expertise_counter_list_item_2') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="our-expertise-image">
                        <figure class="image-anime reveal">
                            <img src="{{ $img('expertise_image', 'expertise-image.jpg') }}" alt="Uzman Güvenlik Çözümleri">
                        </figure>

                        <div class="expertise-contact-box">
                            <div class="icon-box">
                                <img src="{{ $img('expertise_phone_icon', 'icon-phone.svg') }}" alt="">
                            </div>
                            <div class="expertise-contact-content">
                                <h3>{{ $val('expertise_contact_heading') }}</h3>
                                <p><a href="{{ $tel($val('expertise_phone_text')) }}">{{ $val('expertise_phone_text') }}</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Expertise Section End -->
    @endif

    @if($visible('show_what_we_do_section'))
    <!-- What We Do Section Start -->
    <div class="what-we-do dark-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="what-we-do-content">
                        <div class="section-title section-title-center">
                            <h3 class="wow fadeInUp">{{ $val('what_we_do_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('what_we_do_subheading_span') }}</span> {{ $val('what_we_do_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('what_we_do_paragraph_1') }}</p>
                            <p class="wow fadeInUp" data-wow-delay="0.6s">{{ $val('what_we_do_paragraph_2') }}</p>
                        </div>

                        <div class="about-need-help wow fadeInUp" data-wow-delay="0.8s">
                            <div class="icon-box">
                                <img src="{{ $img('need_help_icon', 'icon-need-help.svg') }}" alt="">
                            </div>
                            <div class="need-help-content">
                                <p>{{ $val('need_help_text') }}</p>
                                <h3><a href="{{ $tel($val('what_we_do_phone_text')) }}">{{ $val('what_we_do_phone_text') }}</a></h3>
                            </div>
                        </div>
                    </div>                    
                </div>

                <div class="col-lg-6">
                    <div class="what-we-counter-image">                       
                        <div class="what-we-counter-box">
                            <div class="what-we-counter-item">
                                <div class="icon-box">
                                    <img src="{{ $img('what_we_counter_icon_1', 'icon-what-we-counter-1.svg') }}" alt="">
                                </div>
                                <div class="what-we-counter-item-content">
                                    <h3><span class="counter">24</span>/7</h3>
                                    <p>{{ $val('what_we_counter_label_1') }}</p>
                                </div>
                            </div>

                            <div class="what-we-counter-item">
                                <div class="icon-box">
                                    <img src="{{ $img('what_we_counter_icon_2', 'icon-what-we-counter-2.svg') }}" alt="">
                                </div>
                                <div class="what-we-counter-item-content">
                                    <h3><span class="counter">100</span>+</h3>
                                    <p>{{ $val('what_we_counter_label_2') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="what-we-image">
                            <figure>
                                <img src="{{ $img('what_we_image', 'what-we-image.jpg') }}" alt="Güvenlik Çözümleri">
                            </figure>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- What We Do Section End -->
    @endif

    @if($visible('show_team_section'))
    <!-- Our Team Section Start -->
    <div class="our-team">
        <div class="container">
            <div class="row section-row align-items-center">
                <div class="col-lg-6">
                    <div class="section-title">
                        <h3 class="wow fadeInUp">{{ $val('team_heading') }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('team_subheading_span') }}</span> {{ $val('team_subheading_rest') }}</h2>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="section-content-btn">
                        <div class="section-title-content wow fadeInUp" data-wow-delay="0.4s">
                            <p>{{ $val('team_paragraph') }}</p>
                        </div>
    
                        <div class="section-btn wow fadeInUp" data-wow-delay="0.6s">
                            <a href="team.html" class="btn-default">{{ $val('team_button_text') }}</a>
                        </div>
                    </div>   
                </div>
            </div>

            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="team-item wow fadeInUp" data-wow-delay="0.25s">
                        <div class="team-image">
                            <a href="team-single.html" data-cursor-text="Görüntüle">
                                <figure class="image-anime">
                                    <img src="{{ $img('team_img_1', 'team-1.jpg') }}" alt="">
                                </figure>
                            </a>
                
                            <div class="team-social-icon">
                                <ul>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-facebook-f"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-instagram"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-x-twitter"></i></a></li>
                                </ul>
                            </div>
                        </div>
                
                        <div class="team-content">
                            <h3><a href="team-single.html">{{ $val('team_title_1') }}</a></h3>
                            <p>{{ $val('team_text_1') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="team-item wow fadeInUp" data-wow-delay="0.5s">
                        <div class="team-image">
                            <a href="team-single.html" data-cursor-text="Görüntüle">
                                <figure class="image-anime">
                                    <img src="{{ $img('team_img_2', 'team-2.jpg') }}" alt="">
                                </figure>
                            </a>
                
                            <div class="team-social-icon">
                                <ul>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-facebook-f"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-instagram"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-x-twitter"></i></a></li>
                                </ul>
                            </div>
                        </div>
                
                        <div class="team-content">
                            <h3><a href="team-single.html">{{ $val('team_title_2') }}</a></h3>
                            <p>{{ $val('team_text_2') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="team-item wow fadeInUp" data-wow-delay="0.75s">
                        <div class="team-image">
                            <a href="team-single.html" data-cursor-text="Görüntüle">
                                <figure class="image-anime">
                                    <img src="{{ $img('team_img_3', 'team-3.jpg') }}" alt="">
                                </figure>
                            </a>
                
                            <div class="team-social-icon">
                                <ul>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-facebook-f"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-instagram"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-x-twitter"></i></a></li>
                                </ul>
                            </div>
                        </div>
                
                        <div class="team-content">
                            <h3><a href="team-single.html">{{ $val('team_title_3') }}</a></h3>
                            <p>{{ $val('team_text_3') }}</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="team-item wow fadeInUp" data-wow-delay="1s">
                        <div class="team-image">
                            <a href="team-single.html" data-cursor-text="Görüntüle">
                                <figure class="image-anime">
                                    <img src="{{ $img('team_img_4', 'team-4.jpg') }}" alt="">
                                </figure>
                            </a>
                
                            <div class="team-social-icon">
                                <ul>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-facebook-f"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-instagram"></i></a></li>
                                    <li><a href="#" class="social-icon"><i class="fa-brands fa-x-twitter"></i></a></li>
                                </ul>
                            </div>
                        </div>
                
                        <div class="team-content">
                            <h3><a href="team-single.html">{{ $val('team_title_4') }}</a></h3>
                            <p>{{ $val('team_text_4') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Team Section End -->    
    @endif

    @if($visible('show_support_section'))
    <!-- Our Support Section Start -->
    <div class="our-support about-support">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="our-support-images">
                        <div class="our-support-image box-1">
                            <figure class="image-anime reveal">
                                <img src="{{ $img('support_image_1', 'support-image-1.jpg') }}" alt="">
                            </figure>
                        </div>
                        <div class="our-support-image box-2">
                            <figure class="image-anime reveal">
                                <img src="{{ $img('support_image_2', 'support-image-2.jpg') }}" alt="">
                            </figure>
                        </div>
                        <div class="our-support-circle">
                            <a href="contact.html"><img src="{{ $img('support_circle_icon', 'contact-now-circle-2.svg') }}" alt=""></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="our-support-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('support_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('support_subheading_span') }}</span> {{ $val('support_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('support_paragraph') }}</p>
                        </div>

                        <div class="our-support-body wow fadeInUp" data-wow-delay="0.6s">
                            <div class="support-item">
                                <div class="icon-box">
                                    <img src="{{ $img('support_icon_1', 'icon-support-item-1.svg') }}" alt="">
                                </div>
                                <div class="support-item-content">
                                    <h3>{{ $val('support_item_1_title') }}</h3>
                                    <p>{{ $val('support_item_1_text') }}</p>
                                </div>
                            </div>

                            <div class="support-item">
                                <div class="icon-box">
                                    <img src="{{ $img('support_icon_2', 'icon-support-item-2.svg') }}" alt="">
                                </div>
                                <div class="support-item-content">
                                    <h3>{{ $val('support_item_2_title') }}</h3>
                                    <p>{{ $val('support_item_2_text') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="our-support-btn wow fadeInUp" data-wow-delay="0.8s">
                            <a href="contact.html" class="btn-default">{{ $val('support_button_text') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Support Section End -->
    @endif

    @if($visible('show_testimonials_section'))
    <!-- Our Testimonials Section Start -->
    <div class="our-testimonials">
        <div class="container">
            <div class="row section-row">
                <div class="col-lg-12">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ $val('testimonials_heading') }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('testimonials_subheading_span') }}</span> {{ $val('testimonials_subheading_rest') }}</h2>
                    </div>
                </div>               
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="testimonial-slider">
                        <div class="swiper">
                            <div class="swiper-wrapper" data-cursor-text="Sürükle">
                                <div class="swiper-slide">
                                    <div class="testimonial-item">
                                        <div class="testimonial-header">
                                            <div class="testimonial-author-box">
                                                <div class="author-image">
                                                    <figure class="image-anime">
                                                        <img src="{{ $img('testimonial_author_img_1', 'author-1.jpg') }}" alt="">
                                                    </figure>
                                                </div>
                                                <div class="author-content">
                                                    <h3>{{ $val('testimonial_name_1') }}</h3>
                                                    <p>{{ $val('testimonial_role_1') }}</p>
                                                </div>  
                                            </div>        
                                            <div class="testimonial-quote">
                                                <img src="{{ $img('testimonial_quote_icon', 'testimonial-quote.svg') }}" alt="">
                                            </div>
                                        </div>
                                        <div class="testimonial-rating">
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>                                   
                                        </div>                              
                                        <div class="testimonial-content">
                                            <p>"{{ $val('testimonial_text_1') }}"</p>
                                        </div>                              
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="testimonial-item">
                                        <div class="testimonial-header">
                                            <div class="testimonial-author-box">
                                                <div class="author-image">
                                                    <figure class="image-anime">
                                                        <img src="{{ $img('testimonial_author_img_2', 'author-2.jpg') }}" alt="">
                                                    </figure>
                                                </div>
                                                <div class="author-content">
                                                    <h3>{{ $val('testimonial_name_2') }}</h3>
                                                    <p>{{ $val('testimonial_role_2') }}</p>
                                                </div>  
                                            </div>        
                                            <div class="testimonial-quote">
                                                <img src="{{ $img('testimonial_quote_icon', 'testimonial-quote.svg') }}" alt="">
                                            </div>
                                        </div>
                                        <div class="testimonial-rating">
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>                                   
                                        </div>                                   
                                        <div class="testimonial-content">
                                            <p>"{{ $val('testimonial_text_2') }}"</p>
                                        </div>                              
                                    </div>
                                </div>

                                <div class="swiper-slide">
                                    <div class="testimonial-item">
                                        <div class="testimonial-header">
                                            <div class="testimonial-author-box">
                                                <div class="author-image">
                                                    <figure class="image-anime">
                                                        <img src="{{ $img('testimonial_author_img_3', 'author-3.jpg') }}" alt="">
                                                    </figure>
                                                </div>
                                                <div class="author-content">
                                                    <h3>{{ $val('testimonial_name_3') }}</h3>
                                                    <p>{{ $val('testimonial_role_3') }}</p>
                                                </div>  
                                            </div>        
                                            <div class="testimonial-quote">
                                                <img src="{{ $img('testimonial_quote_icon', 'testimonial-quote.svg') }}" alt="">
                                            </div>
                                        </div>
                                        <div class="testimonial-rating">
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>
                                            <i class="fa-solid fa-star"></i>                                   
                                        </div>                           
                                        <div class="testimonial-content">
                                            <p>"{{ $val('testimonial_text_3') }}"</p>
                                        </div>                              
                                    </div>
                                </div>
                            </div>
                            <div class="testimonial-btn">
                                <div class="testimonial-button-prev"></div>
                                <div class="testimonial-button-next"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our Testimonials Section End -->
    @endif

    @if($visible('show_cta_section'))
    <!-- CTA Box Section Start -->
    <div class="cta-box dark-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="cta-box-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('cta_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('cta_subheading_span') }}</span> {{ $val('cta_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('cta_paragraph') }}</p>
                        </div>

                        <div class="cta-box-body wow fadeInUp" data-wow-delay="0.6s">
                            <div class="cta-box-item">
                                <div class="icon-box">
                                    <img src="{{ $img('cta_phone_icon', 'icon-phone.svg') }}" alt="">
                                </div>
                                <div class="cta-box-item-content">
                                    <p>{{ $val('cta_phone_label') }}</p>
                                    <h3><a href="{{ $tel($val('cta_phone_text')) }}">{{ $val('cta_phone_text') }}</a></h3>
                                </div>
                            </div>

                            <div class="cta-box-item">
                                <div class="icon-box">
                                    <img src="{{ $img('cta_mail_icon', 'icon-mail.svg') }}" alt="">
                                </div>
                                <div class="cta-box-item-content">
                                    <p>{{ $val('cta_mail_label') }}</p>
                                    <h3><a href="mailto:{{ $val('cta_mail_text') }}">{{ $val('cta_mail_text') }}</a></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="cta-box-image">
                        <img src="{{ $img('cta_image', 'cta-box-image.png') }}" alt="İletişim">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- CTA Box Section End -->
    @endif

    @if($visible('show_faq_section'))
    <!-- Our FAQs Section Start -->
    <div class="our-faqs about-our-faqs">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="our-faqs-content">
                        <div class="section-title">
                            <h3 class="wow fadeInUp">{{ $val('faqs_heading') }}</h3>
                            <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $val('faqs_subheading_span') }}</span> {{ $val('faqs_subheading_rest') }}</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $val('faqs_paragraph') }}</p>
                        </div>

                        <div class="our-faqs-list wow fadeInUp" data-wow-delay="0.6s">
                            <ul>
                                <li>{{ $val('faqs_list_item_1') }}</li>
                                <li>{{ $val('faqs_list_item_2') }}</li>
                                <li>{{ $val('faqs_list_item_3') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="faq-accordion" id="accordion">
                        <div class="accordion-item wow fadeInUp">
                            <h2 class="accordion-header" id="heading1">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                    {{ $val('faq_q_1') }}
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse" aria-labelledby="heading1" data-bs-parent="#accordion">
                                <div class="accordion-body">
                                    <p>{{ $val('faq_a_1') }}</p>
                                    <img src="{{ $img('faq_image', 'faqs-accordion-img.jpg') }}" alt="">
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item wow fadeInUp" data-wow-delay="0.2s">
                            <h2 class="accordion-header" id="heading2">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                    {{ $val('faq_q_2') }}
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse show" aria-labelledby="heading2" data-bs-parent="#accordion">
                                <div class="accordion-body">
                                    <p>{{ $val('faq_a_2') }}</p>
                                    <img src="{{ $img('faq_image', 'faqs-accordion-img.jpg') }}" alt="">
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item wow fadeInUp" data-wow-delay="0.4s">
                            <h2 class="accordion-header" id="heading3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                    {{ $val('faq_q_3') }}
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="heading3" data-bs-parent="#accordion">
                                <div class="accordion-body">
                                    <p>{{ $val('faq_a_3') }}</p>
                                    <img src="{{ $img('faq_image', 'faqs-accordion-img.jpg') }}" alt="">
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item wow fadeInUp" data-wow-delay="0.6s">
                            <h2 class="accordion-header" id="heading4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                                    {{ $val('faq_q_4') }}
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="heading4" data-bs-parent="#accordion">
                                <div class="accordion-body">
                                    <p>{{ $val('faq_a_4') }}</p>
                                    <img src="{{ $img('faq_image', 'faqs-accordion-img.jpg') }}" alt="">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Our FAQs Section End -->
    @endif
      
      
        






















@endsection
