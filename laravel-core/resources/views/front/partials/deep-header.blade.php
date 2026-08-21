<header class="deep-site-header" data-deep-header>
    <div class="deep-header-inner">
        <a class="deep-brand" href="{{ route('home') }}" aria-label="Yalova Yazılım ana sayfa">
            <img src="{{ asset('themes/deep/images/deeplogo-light.png') }}" alt="Yalova Yazılım" width="130" height="27">
            <span class="deep-brand-fallback">DEEP</span>
        </a>

        <button class="deep-menu-toggle" type="button" aria-expanded="false" aria-controls="deep-primary-nav" data-deep-menu-toggle>
            <span class="deep-menu-icon" aria-hidden="true"><i></i><i></i></span>
            <span class="sr-only">Menüyü aç</span>
        </button>

        <nav class="deep-primary-nav" id="deep-primary-nav" aria-label="Ana menü" data-deep-menu>
            <a class="is-active" href="{{ route('home') }}#top">Anasayfa</a>
            <a href="#services">Çözümler</a>
            <a href="#features">Neden biz</a>
        </nav>

        <a class="deep-header-action" href="{{ route('tenant.login') }}">Giriş Yap <span aria-hidden="true">↗</span></a>
    </div>
</header>
