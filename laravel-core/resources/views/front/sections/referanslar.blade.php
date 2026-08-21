@php
    $d = fn (string $k) => __('front.sections.references.' . $k);
    $s = fn (string $k, string $d = '') => \App\Models\Setting::get("modul.referanslar.$k", $d);
    $img = fn (string $k, string $d) => $s($k) ? \Illuminate\Support\Facades\Storage::url($s($k)) : asset('theme/yalovakamera/images/' . $d);
    $u = fn (string $k) => $s($k, '') ?: route('projects.index');
@endphp
<!-- Our Projects Start -->
<div class="our-projects">
    <div class="container">
        <div class="row section-row align-items-center">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <h3 class="wow fadeInUp">{{ $s('heading', $d('heading')) }}</h3>
                    <h2 class="wow fadeInUp" data-wow-delay="0.2s" data-cursor="-opaque"><span>{{ $s('sub_span', $d('sub_span')) }}</span> {{ $s('sub_text', $d('sub_text')) }}</h2>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="our-Project-nav wow fadeInUp" data-wow-delay="0.4s">
                    <ul>
                        <li><a href="#" class="active-btn" data-filter="*">{{ $d('all') }}</a></li>
                        <li><a href="#" data-filter=".residential">{{ $d('residential') }}</a></li>
                        <li><a href="#" data-filter=".commercial">{{ $d('commercial') }}</a></li>
                        <li><a href="#" data-filter=".industrial">{{ $d('industrial') }}</a></li>
                        <li><a href="#" data-filter=".retail">{{ $d('retail') }}</a></li>
                        <li><a href="#" data-filter=".office">{{ $d('office') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="row project-item-boxes align-items-center">
                    <div class="col-lg-4 col-md-6 project-item-box residential">
                        <div class="project-item wow fadeInUp">
                            <div class="project-image">
                                <a href="{{ $u('item_url_1') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_1', 'project-1.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_1') }}">{{ $s('item_category_1', $d('residential')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_1') }}">{{ $s('item_title_1', $d('item_title_1')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 project-item-box commercial">
                        <div class="project-item wow fadeInUp" data-wow-delay="0.2s">
                            <div class="project-image">
                                <a href="{{ $u('item_url_2') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_2', 'project-2.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_2') }}">{{ $s('item_category_2', $d('commercial')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_2') }}">{{ $s('item_title_2', $d('item_title_2')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 project-item-box industrial">
                        <div class="project-item wow fadeInUp" data-wow-delay="0.4s">
                            <div class="project-image">
                                <a href="{{ $u('item_url_3') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_3', 'project-3.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_3') }}">{{ $s('item_category_3', $d('industrial')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_3') }}">{{ $s('item_title_3', $d('item_title_3')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 project-item-box retail">
                        <div class="project-item wow fadeInUp" data-wow-delay="0.6s">
                            <div class="project-image">
                                <a href="{{ $u('item_url_4') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_4', 'project-4.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_4') }}">{{ $s('item_category_4', $d('retail')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_4') }}">{{ $s('item_title_4', $d('item_title_4')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 project-item-box residential">
                        <div class="project-item wow fadeInUp" data-wow-delay="0.8s">
                            <div class="project-image">
                                <a href="{{ $u('item_url_5') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_5', 'project-5.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_5') }}">{{ $s('item_category_5', $d('residential')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_5') }}">{{ $s('item_title_5', $d('item_title_5')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 project-item-box office">
                        <div class="project-item wow fadeInUp" data-wow-delay="1s">
                            <div class="project-image">
                                <a href="{{ $u('item_url_6') }}" data-cursor-text="View">
                                    <figure class="image-anime">
                                        <img src="{{ $img('item_image_6', 'project-6.jpg') }}" alt="">
                                    </figure>
                                </a>
                                <div class="project-tag">
                                    <a href="{{ $u('item_url_6') }}">{{ $s('item_category_6', $d('office')) }}</a>
                                </div>
                            </div>
                            <div class="project-content">
                                <h3><a href="{{ $u('item_url_6') }}">{{ $s('item_title_6', $d('item_title_6')) }}</a></h3>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- Our Projects End -->
