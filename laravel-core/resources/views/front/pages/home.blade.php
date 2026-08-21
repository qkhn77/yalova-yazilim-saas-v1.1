@extends('front.layouts.deep')

@section('title', 'Yalova Yazılım | İşinizi büyüten SaaS çözümleri')

@section('content')
<div class="deep-source-home" id="top">
    @include('front.partials.deep-header')

    <main>
        <section class="deep-source-hero" aria-labelledby="deep-source-hero-title">
            <div class="deep-source-hero-inner">
                <div class="deep-source-hero-pill">YALOVA YAZILIM / SAAS ALTYAPISI</div>
                <p class="deep-source-kicker">İŞİNİZ İÇİN TASARLANDI</p>
                <h1 id="deep-source-hero-title">İŞİNİZİ BÜYÜTEN<br><em>DİJİTAL ALTYAPI</em></h1>
                <p class="deep-source-hero-text">Operasyonlarınızı sadeleştiren, ekibinizi hızlandıran ve büyümeye hazır yazılım çözümleri.</p>
            </div>
            <a class="deep-source-scroll" href="#services" aria-label="Çözümler bölümüne ilerle">
                <span class="deep-source-scroll-icon">↓</span>
                <span>Aşağı kaydır</span>
            </a>
        </section>

        <section class="deep-source-marquee" aria-label="Yalova Yazılım değerleri">
            <div class="deep-source-marquee-track">
                <span>SADE ÇÖZÜMLER</span><b>✳</b><span>GÜÇLÜ ALTYAPI</span><b>✳</b><span>HIZLI GELİŞİM</span><b>✳</b><span>GÜVENİLİR DESTEK</span><b>✳</b>
                <span>SADE ÇÖZÜMLER</span><b>✳</b><span>GÜÇLÜ ALTYAPI</span><b>✳</b><span>HIZLI GELİŞİM</span><b>✳</b><span>GÜVENİLİR DESTEK</span><b>✳</b>
            </div>
        </section>

        <section class="deep-source-section deep-source-services" id="services" aria-labelledby="deep-services-title">
            <div class="deep-source-pill deep-source-pill-light">ÇÖZÜMLER / 01</div>
            <h2 id="deep-services-title">İŞİNİZ İÇİN<br><em>GÜÇLÜ ARAÇLAR</em></h2>
            <p class="deep-source-section-lead">İhtiyacınıza göre şekillenen, kullanımı kolay ve sürdürülebilir dijital çözümler.</p>

            <div class="deep-source-card-grid">
                @forelse($services->take(4) as $index => $service)
                    <article class="deep-source-card" data-reveal data-reveal-delay="{{ min($index * 90, 270) }}">
                        <a href="#services" class="deep-source-card-media">
                            <span class="deep-source-browser-bar"><i></i><i></i><i></i></span>
                            <img src="{{ asset('themes/deep/images/source/' . ['agency-demo.webp', 'creative-demo.webp', 'exposure-demo.webp', 'split-demo.webp'][$index]) }}" alt="{{ $service->title }}" loading="lazy">
                            <span class="deep-source-card-arrow">↗</span>
                        </a>
                        <div class="deep-source-card-meta">
                            <span>0{{ $index + 1 }}</span>
                            <h3><a href="#services">{{ $service->title }}</a></h3>
                            <p>{{ \Illuminate\Support\Str::limit($service->short_description, 110) }}</p>
                        </div>
                    </article>
                @empty
                    <article class="deep-source-card deep-source-card-empty">
                        <div><span>01</span><h3>İşinize özel yazılım</h3><p>Operasyonlarınızı kolaylaştıracak çözümü birlikte tasarlayalım.</p></div>
                    </article>
                @endforelse
            </div>

            <div class="deep-source-section-link">
                <span>İşletmeniz için doğru başlangıç noktasını bulalım.</span>
                <a href="#services">Tüm çözümler <b>↗</b></a>
            </div>
        </section>

        <section class="deep-source-section deep-source-features" id="features" aria-labelledby="deep-features-title">
            <div class="deep-source-pill">NEDEN BİZ / 02</div>
            <h2 id="deep-features-title">DAHA AZ KARMAŞA.<br><em>DAHA ÇOK İLERLEME.</em></h2>
            <p class="deep-source-section-lead">Teknolojiyi işinizin önüne değil, işinizin yanına koyuyoruz.</p>

            <div class="deep-source-feature-grid">
                <article class="deep-source-feature large" data-reveal>
                    <img src="{{ asset('themes/deep/images/source/elementor.webp') }}" alt="Dijital ürün arayüzleri" loading="lazy">
                    <div><span>01</span><h3>Hızlı başlangıç</h3><p>İhtiyacınızı anlayıp doğru çözümle kısa sürede ilerleriz.</p></div>
                </article>
                <article class="deep-source-feature" data-reveal data-reveal-delay="100">
                    <img src="{{ asset('themes/deep/images/source/import-system-brandberry.webp') }}" alt="Esnek dijital çözüm" loading="lazy">
                    <div><span>02</span><h3>Esnek yapı</h3><p>İşiniz büyüdükçe çözümünüz de sizinle birlikte büyür.</p></div>
                </article>
                <article class="deep-source-feature" data-reveal data-reveal-delay="180">
                    <img src="{{ asset('themes/deep/images/source/brandberry-widgets.webp') }}" alt="Güvenilir teknik destek" loading="lazy">
                    <div><span>03</span><h3>Güvenilir destek</h3><p>Kurulum sonrası ihtiyaçlarınızda yanınızda oluruz.</p></div>
                </article>
            </div>
        </section>

        <section class="deep-source-stats" aria-label="Yalova Yazılım istatistikleri">
            <div><strong>10<span>+</span></strong><small>yıllık deneyim</small></div>
            <div><strong>24<span>/7</span></strong><small>çözüm odağı</small></div>
            <div><strong>360<span>°</span></strong><small>bakış açısı</small></div>
            <div><strong>1<span>→1</span></strong><small>yakın iletişim</small></div>
        </section>

        <section class="deep-source-section deep-source-projects" id="projects" aria-labelledby="deep-projects-title">
            <div class="deep-source-pill deep-source-pill-light">PROJELER / 03</div>
            <h2 id="deep-projects-title">BİRLİKTE<br><em>BAŞARDIKLARIMIZ</em></h2>
            <p class="deep-source-section-lead">Farklı ihtiyaçlara uyarlanan, sonuç odaklı işler.</p>

            <div class="deep-source-project-grid">
                @forelse($projects->take(4) as $index => $project)
                    <a class="deep-source-project" href="#projects" data-reveal data-reveal-delay="{{ min($index * 90, 270) }}">
                        <span>0{{ $index + 1 }}</span>
                        <div class="deep-source-project-media">
                            <img src="{{ asset('themes/deep/images/source/' . ['elementor.webp', 'brandberry-widgets.webp', 'themebuilder.webp', 'mobile.webp'][$index]) }}" alt="{{ $project->title }}" loading="lazy">
                        </div>
                        <div class="deep-source-project-caption"><h3>{{ $project->title }}</h3><b>Projeyi gör ↗</b></div>
                    </a>
                @empty
                    <a class="deep-source-project deep-source-project-empty" href="#contact">
                        <span>01</span><div>Yeni projeniz<br>burada başlayabilir.</div><strong>İletişime geç ↗</strong>
                    </a>
                @endforelse
            </div>
        </section>

        <section class="deep-source-cta" id="contact" aria-labelledby="deep-cta-title">
            <div class="deep-source-pill deep-source-pill-light">BİR SONRAKİ ADIM / 04</div>
            <h2 id="deep-cta-title">FİKRİNİZİ<br><em>BİRLİKTE BÜYÜTELİM.</em></h2>
            <a class="deep-source-cta-button" href="#contact">Bize ulaşın <span>↗</span></a>
        </section>
    </main>

    @include('front.partials.deep-footer')
</div>
@endsection
