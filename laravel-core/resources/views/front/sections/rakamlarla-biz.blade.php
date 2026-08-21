@php
    $d = fn (string $k) => __('front.sections.features.' . $k);
    $s = fn (string $k, string $d = '') => \App\Models\Setting::get("modul.rakamlarla_biz.$k", $d);
    $img = function (string $k, string $defaultFile) use ($s) {
        $v = (string) $s($k, '');
        return $v !== '' ? asset('uploads/' . ltrim(str_replace('\\', '/', $v), '/')) : asset('theme/yalovakamera/images/' . $defaultFile);
    };
@endphp
<!-- Our Feature Section Start -->
<div class="our-feature dark-section">
    <div class="container">
        <div class="row section-row align-items-center">
            <div class="col-lg-6 col-md-8">
                <div class="section-title">
                    <h3 class="wow fadeInUp">{{ $s('heading', $d('heading')) }}</h3>
                    <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $s('sub_span', $d('sub_span')) }}</span> {{ $s('sub_text', $d('sub_text')) }}</h2>
                </div>
            </div>

            <div class="col-lg-6 col-md-4">
                <div class="contact-now-circle">
                    <a href="{{ route('contact') }}">
                        <img src="{{ $img('contact_circle', 'contact-now-circle.svg') }}" alt="">
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="our-feature-box">
                    <div class="feature-item wow fadeInUp">
                        <div class="icon-box">
                            <img src="{{ $img('icon_1', 'icon-feature-item-1.svg') }}" alt="">
                        </div>
                        <div class="feature-item-content">
                            <h3>{{ $s('title_1', $d('title_1')) }}</h3>
                            <p>{{ $s('text_1', $d('text_1')) }}</p>
                        </div>
                    </div>

                    <div class="feature-item wow fadeInUp" data-wow-delay="0.2s">
                        <div class="icon-box">
                            <img src="{{ $img('icon_2', 'icon-feature-item-2.svg') }}" alt="">
                        </div>
                        <div class="feature-item-content">
                            <h3>{{ $s('title_2', $d('title_2')) }}</h3>
                            <p>{{ $s('text_2', $d('text_2')) }}</p>
                        </div>
                    </div>

                    <div class="feature-item wow fadeInUp" data-wow-delay="0.4s">
                        <div class="icon-box">
                            <img src="{{ $img('icon_3', 'icon-feature-item-3.svg') }}" alt="">
                        </div>
                        <div class="feature-item-content">
                            <h3>{{ $s('title_3', $d('title_3')) }}</h3>
                            <p>{{ $s('text_3', $d('text_3')) }}</p>
                        </div>
                    </div>

                    <div class="feature-item wow fadeInUp" data-wow-delay="0.6s">
                        <div class="icon-box">
                            <img src="{{ $img('icon_4', 'icon-feature-item-4.svg') }}" alt="">
                        </div>
                        <div class="feature-item-content">
                            <h3>{{ $s('title_4', $d('title_4')) }}</h3>
                            <p>{{ $s('text_4', $d('text_4')) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="feature-counter-box">
                    <div class="feature-counter-item">
                        <h2><span class="counter">{{ $s('counter_1', '220') }}</span>+</h2>
                        <p>{{ $s('counter_label_1', $d('counter_label_1')) }}</p>
                    </div>
                    <div class="feature-counter-item">
                        <h2><span class="counter">{{ $s('counter_2', '30') }}</span>+</h2>
                        <p>{{ $s('counter_label_2', $d('counter_label_2')) }}</p>
                    </div>
                    <div class="feature-counter-item">
                        <h2><span class="counter">{{ $s('counter_3', '100') }}</span>+</h2>
                        <p>{{ $s('counter_label_3', $d('counter_label_3')) }}</p>
                    </div>
                    <div class="feature-counter-item">
                        <h2><span class="counter">{{ $s('counter_4', '700') }}</span>+</h2>
                        <p>{{ $s('counter_label_4', $d('counter_label_4')) }}</p>
                    </div>
                    <div class="feature-counter-item">
                        <h2><span class="counter">{{ $s('counter_5', '10') }}</span>+</h2>
                        <p>{{ $s('counter_label_5', $d('counter_label_5')) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Our Feature Section End -->
