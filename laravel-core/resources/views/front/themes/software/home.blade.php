@php
    $softwareServices = collect($services ?? [])->take(6);
    $softwareProjects = collect($projects ?? [])->take(3);
@endphp

<main class="software-home">
    <section class="software-hero">
        <div class="container">
            <div class="software-hero-grid">
                <div class="software-hero-copy">
                    <span class="software-eyebrow">{{ __('front.home.company_name') }}</span>
                    <h1>Güvenliğinizi <span>akıllı teknolojiyle</span> güçlendirin.</h1>
                    <p>{{ __('front.home.hero_desc') }}</p>
                    <div class="software-hero-actions">
                        <a href="{{ route('contact') }}" class="btn-default">{{ __('front.home.get_quote_now') }}</a>
                        <a href="{{ route('services.index') }}" class="software-text-link">Çözümleri keşfet <span aria-hidden="true">→</span></a>
                    </div>
                </div>

                <div class="software-hero-console" aria-label="Teknoloji çözümleri özeti">
                    <div class="software-console-top"><span></span><span></span><span></span><small>system.status</small></div>
                    <div class="software-console-body">
                        <div class="software-console-line"><span class="software-prompt">$</span> security --monitor</div>
                        <div class="software-console-status"><i></i> Tüm sistemler aktif</div>
                        <div class="software-console-metrics">
                            <div><strong>24/7</strong><small>İzleme</small></div>
                            <div><strong>10+</strong><small>Yıl deneyim</small></div>
                            <div><strong>99.9%</strong><small>Güvenilirlik</small></div>
                        </div>
                        <div class="software-console-chart"><span style="height: 34%"></span><span style="height: 52%"></span><span style="height: 42%"></span><span style="height: 68%"></span><span style="height: 58%"></span><span style="height: 86%"></span><span style="height: 73%"></span><span style="height: 96%"></span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="software-trust-strip">
        <div class="container">
            <div><strong>Uçtan uca</strong><span>planlama, kurulum ve destek</span></div>
            <div><strong>Hızlı destek</strong><span>ihtiyaçlarınıza özel çözümler</span></div>
            <div><strong>Ölçeklenebilir</strong><span>bugün ve yarın için hazır</span></div>
        </div>
    </section>

    <section class="software-section software-services-section">
        <div class="container">
            <div class="software-section-heading">
                <div><span class="software-eyebrow">Çözümlerimiz</span><h2>İşinizi koruyan teknoloji.</h2></div>
                <a href="{{ route('services.index') }}" class="software-text-link">Tüm hizmetler <span aria-hidden="true">→</span></a>
            </div>
            <div class="software-service-grid">
                @forelse($softwareServices as $service)
                    <a class="software-feature-card" href="{{ route('services.show', $service->slug) }}">
                        <span class="software-card-number">0{{ $loop->iteration }}</span>
                        <h3>{{ $service->title }}</h3>
                        <p>{{ IlluminateSupportStr::limit(strip_tags((string) $service->short_description), 130) }}</p>
                        <span class="software-card-arrow" aria-hidden="true">↗</span>
                    </a>
                @empty
                    <div class="software-empty-state">Hizmet içerikleri yakında burada yayınlanacaktır.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="software-section software-projects-section">
        <div class="container">
            <div class="software-section-heading"><div><span class="software-eyebrow">Referanslar</span><h2>Gerçek ihtiyaçlara gerçek çözümler.</h2></div><a href="{{ route('projects.index') }}" class="software-text-link">Projeleri gör <span aria-hidden="true">→</span></a></div>
            <div class="software-project-grid">
                @forelse($softwareProjects as $project)
                    <a class="software-project-card" href="{{ route('projects.show', $project->slug) }}">
                        @if($project->image_thumb_url ?? $project->image_url)
                            <img src="{{ $project->image_thumb_url ?? $project->image_url }}" alt="{{ $project->title }}" loading="lazy">
                        @endif
                        <div><span>{{ $project->category?->name ?? 'Proje' }}</span><h3>{{ $project->title }}</h3></div>
                    </a>
                @empty
                    <div class="software-empty-state">Referans projeleri yakında burada yayınlanacaktır.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="software-cta-section">
        <div class="container">
            <div><span class="software-eyebrow">Bir sonraki adım</span><h2>Projeniz için doğru teknoloji ortağını bulun.</h2></div>
            <a href="{{ route('contact') }}" class="btn-default">Ücretsiz görüşme planla <span aria-hidden="true">↗</span></a>
        </div>
    </section>
</main>
