@php
    $d = fn (string $k) => __('front.sections.testimonials.' . $k);
    $s = fn (string $k, string $d = '') => \App\Models\Setting::get("modul.musteri_yorumlari.$k", $d);
    $img = function (string $k, string $defaultFile) use ($s) {
        $v = (string) $s($k, '');
        return $v !== '' ? asset('uploads/' . ltrim(str_replace('\\', '/', $v), '/')) : asset('theme/yalovakamera/images/' . $defaultFile);
    };
@endphp
<!-- Our Testimonials Section Start -->
<div class="our-testimonials">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <h3 class="wow fadeInUp">{{ $s('heading', $d('heading')) }}</h3>
                    <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $s('sub_span', $d('sub_span')) }}</span> {{ $s('sub_text', $d('sub_text')) }}</h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="testimonial-slider">
                    <div class="swiper">
                        <div class="swiper-wrapper" data-cursor-text="Drag">

                            <div class="swiper-slide">
                                <div class="testimonial-item">
                                    <div class="testimonial-header">
                                        <div class="testimonial-author-box">
                                            <div class="author-image">
                                                <figure class="image-anime">
                                                    <img src="{{ $img('author_image_1', 'author-1.jpg') }}" alt="">
                                                </figure>
                                            </div>
                                            <div class="author-content">
                                                <h3>{{ $s('name_1', 'Sophia Reynolds') }}</h3>
                                                <p>{{ $s('role_1', 'CEO') }}</p>
                                            </div>
                                        </div>
                                        <div class="testimonial-quote">
                                            <img src="{{ $img('quote_icon', 'testimonial-quote.svg') }}" alt="">
                                        </div>
                                    </div>
                                    <div class="testimonial-rating">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                    <div class="testimonial-content">
                                        <p>"{{ $s('comment_1', $d('comment_1')) }}"</p>
                                    </div>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="testimonial-item">
                                    <div class="testimonial-header">
                                        <div class="testimonial-author-box">
                                            <div class="author-image">
                                                <figure class="image-anime">
                                                    <img src="{{ $img('author_image_2', 'author-2.jpg') }}" alt="">
                                                </figure>
                                            </div>
                                            <div class="author-content">
                                                <h3>{{ $s('name_2', 'Kathryn Murphy') }}</h3>
                                                <p>{{ $s('role_2', $d('role_2')) }}</p>
                                            </div>
                                        </div>
                                        <div class="testimonial-quote">
                                            <img src="{{ $img('quote_icon', 'testimonial-quote.svg') }}" alt="">
                                        </div>
                                    </div>
                                    <div class="testimonial-rating">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                    <div class="testimonial-content">
                                        <p>"{{ $s('comment_2', $d('comment_2')) }}"</p>
                                    </div>
                                </div>
                            </div>

                            <div class="swiper-slide">
                                <div class="testimonial-item">
                                    <div class="testimonial-header">
                                        <div class="testimonial-author-box">
                                            <div class="author-image">
                                                <figure class="image-anime">
                                                    <img src="{{ $img('author_image_3', 'author-3.jpg') }}" alt="">
                                                </figure>
                                            </div>
                                            <div class="author-content">
                                                <h3>{{ $s('name_3', 'John Miller') }}</h3>
                                                <p>{{ $s('role_3', 'IT Manager') }}</p>
                                            </div>
                                        </div>
                                        <div class="testimonial-quote">
                                            <img src="{{ $img('quote_icon', 'testimonial-quote.svg') }}" alt="">
                                        </div>
                                    </div>
                                    <div class="testimonial-rating">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                    <div class="testimonial-content">
                                        <p>"{{ $s('comment_3', $d('comment_3')) }}"</p>
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
