<footer class="deep-footer">
    <div class="deep-footer-grid">
        <div class="deep-footer-intro">
            <a class="deep-brand deep-brand-footer" href="{{ route('home') }}" aria-label="Yalova Yazılım ana sayfa">
                <img src="{{ asset('themes/deep/images/deeplogo-light.png') }}" alt="Yalova Yazılım" width="130" height="27">
                <span class="deep-brand-fallback">DEEP</span>
            </a>
            <p>İşletmelerin daha hızlı çalışması için sade, güvenilir ve ölçeklenebilir yazılım çözümleri.</p>
        </div>

        <div class="deep-footer-column">
            <span class="deep-footer-label">Keşfet</span>
            <a href="{{ route('home') }}#features">Hakkımızda</a>
            <a href="{{ route('home') }}#services">Servisler</a>
            <a href="{{ route('home') }}#projects">Projeler</a>
        </div>

        <div class="deep-footer-column">
            <span class="deep-footer-label">Destek</span>
            <a href="{{ route('home') }}#contact">İletişim</a>
            <a href="{{ route('home') }}#projects">Blog</a>
            <a href="{{ route('tenant.login') }}">Giriş Yap</a>
        </div>

        <div class="deep-footer-contact">
            <span class="deep-footer-label">Birlikte başlayalım</span>
            <a href="{{ route('home') }}#contact" class="deep-footer-mail">İletişime geç <span aria-hidden="true">↗</span></a>
            <p>Yalova ve Türkiye genelinde dijital iş süreçleri için.</p>
        </div>
    </div>

    <div class="deep-footer-bottom">
        <span>© {{ date('Y') }} Yalova Yazılım. Tüm hakları saklıdır.</span>
        <a href="#top">Yukarı çık <span aria-hidden="true">↑</span></a>
    </div>
</footer>
