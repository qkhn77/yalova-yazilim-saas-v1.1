@php
    $d = fn (string $k) => __('front.sections.what_we_do.' . $k);
    $s = fn (string $k, string $d = '') => \App\Models\Setting::get("modul.neler_yapiyoruz.$k", $d);
    $img = function (string $k, string $defaultFile) use ($s) {
        $v = (string) $s($k, '');
        return $v !== '' ? asset('uploads/' . ltrim(str_replace('\\', '/', $v), '/')) : asset('theme/yalovakamera/images/' . $defaultFile);
    };
@endphp
<!-- What We Do Section Start -->
<div class="what-we-do dark-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="what-we-do-content">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ $s('heading', $d('heading')) }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $s('sub_span', $d('sub_span')) }}</span> {{ $s('sub_text', $d('sub_text')) }}</h2>
                        <p class="wow fadeInUp" data-wow-delay="0.4s">{{ $s('text_1', $d('text_1')) }}</p>
                        <p class="wow fadeInUp" data-wow-delay="0.6s">{{ $s('text_2', $d('text_2')) }}</p>
                    </div>

                    <div class="about-need-help wow fadeInUp" data-wow-delay="0.8s">
                        <div class="icon-box">
                            <img src="{{ $img('need_help_icon', 'icon-need-help.svg') }}" alt="">
                        </div>
                        <div class="need-help-content">
                            <p>{{ $s('need_help_text', $d('need_help_text')) }}</p>
                            <h3><a href="tel:{{ preg_replace('/[^0-9]/', '', $s('phone', '0 (226) 352 07 24')) }}">{{ $s('phone', '0 (226) 352 07 24') }}</a></h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="what-we-counter-image">
                    <div class="what-we-counter-box">
                        <div class="what-we-counter-item">
                            <div class="icon-box">
                                <img src="{{ $img('counter_icon_1', 'icon-what-we-counter-1.svg') }}" alt="">
                            </div>
                            <div class="what-we-counter-item-content">
                                <h3><span class="counter">{{ $s('counter_value_1', '550') }}</span>+</h3>
                                <p>{{ $s('counter_label_1', $d('counter_label_1')) }}</p>
                            </div>
                        </div>

                        <div class="what-we-counter-item">
                            <div class="icon-box">
                                <img src="{{ $img('counter_icon_2', 'icon-what-we-counter-2.svg') }}" alt="">
                            </div>
                            <div class="what-we-counter-item-content">
                                <h3><span class="counter">{{ $s('counter_value_2', '250') }}</span>+</h3>
                                <p>{{ $s('counter_label_2', $d('counter_label_2')) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="what-we-image">
                        <figure>
                            <img src="{{ $img('main_image', 'what-we-image.jpg') }}" alt="">
                        </figure>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- What We Do Section End -->
