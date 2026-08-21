@php
    $d = fn (string $k) => __('front.sections.why_choose.' . $k);
    $s = fn (string $k, string $d = '') => \App\Models\Setting::get("modul.neden_biz.$k", $d);
    $img = function (string $k, string $defaultFile) use ($s) {
        $v = (string) $s($k, '');
        return $v !== '' ? asset('uploads/' . ltrim(str_replace('\\', '/', $v), '/')) : asset('theme/yalovakamera/images/' . $defaultFile);
    };
@endphp
    <!-- Why Choose Us Section Start -->
    <div class="why-choose-us">
        <div class="container">
            <div class="row section-row align-items-center">
                <div class="col-lg-12">
                    <div class="section-title section-title-center">
                        <h3 class="wow fadeInUp">{{ $s('heading', $d('heading')) }}</h3>
                        <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $s('sub_span', $d('sub_span')) }}</span> {{ $s('sub_text', $d('sub_text')) }}</h2>
                    </div>
                </div>
            </div>

            <div class="row align-items-center">
                <div class="col-lg-3">
                    <div class="why-choose-box">
                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_1', 'icon-why-choose-1.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $s('title_1', $d('title_1')) }}</h3>
                                <p>{{ $s('text_1', $d('text_1')) }}</p>
                            </div>
                        </div>

                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_2', 'icon-why-choose-2.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $s('title_2', $d('title_2')) }}</h3>
                                <p>{{ $s('text_2', $d('text_2')) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="why-choose-image">
                        <figure>
                            <img src="{{ $img('image', 'why-choose-image.png') }}" alt="">
                        </figure>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="why-choose-box">
                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_3', 'icon-why-choose-3.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $s('title_3', $d('title_3')) }}</h3>
                                <p>{{ $s('text_3', $d('text_3')) }}</p>
                            </div>
                        </div>

                        <div class="why-choose-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="icon-box">
                                <img src="{{ $img('icon_4', 'icon-why-choose-4.svg') }}" alt="">
                            </div>
                            <div class="why-choose-item-content">
                                <h3>{{ $s('title_4', $d('title_4')) }}</h3>
                                <p>{{ $s('text_4', $d('text_4')) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Why Choose Us Section End -->
