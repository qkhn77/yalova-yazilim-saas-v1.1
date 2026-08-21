<div
    x-data="templateLivePreview()"
    x-init="init()"
    class="teklif-cork-live-preview overflow-hidden rounded-xl border border-gray-200 bg-gray-100 shadow-sm dark:border-white/10 dark:bg-gray-950"
>
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900">
        <div>
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Canlı A4 önizleme</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">HTML ve CSS alanlarında yaptığınız değişiklikler kaydetmeden burada görünür.</div>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
            Anlık güncelleniyor
        </div>
    </div>
    <div class="max-h-[760px] overflow-auto bg-gray-700 p-4 dark:bg-gray-950">
        <iframe
            x-ref="frame"
            title="Şablon canlı önizleme"
            class="mx-auto block max-w-none bg-white shadow-2xl"
            style="width: 794px; height: 1123px;"
        ></iframe>
    </div>
</div>

<script>
    window.templateLivePreview = function () {
        return {
            htmlField: null,
            cssField: null,
            timer: null,
            bound: false,
            init() {
                const connect = () => {
                    this.htmlField = document.querySelector('#data\\.sablon_html');
                    this.cssField = document.querySelector('#data\\.sablon_css');

                    if (!this.htmlField || !this.cssField) {
                        window.setTimeout(connect, 250);
                        return;
                    }

                    if (!this.htmlField.value && !this.cssField.value) {
                        window.setTimeout(connect, 250);
                        return;
                    }

                    if (this.bound) return;
                    this.bound = true;

                    const refresh = () => {
                        window.clearTimeout(this.timer);
                        this.timer = window.setTimeout(() => this.render(), 120);
                    };

                    this.htmlField.addEventListener('input', refresh);
                    this.cssField.addEventListener('input', refresh);
                    this.render();
                };

                connect();
            },
            render() {
                if (!this.htmlField || !this.cssField || !this.$refs.frame) return;

                const replacements = {
                    '@{{FIRMA_LOGO}}': '<img src="{{ asset('storage/teklif-sablon-logolari/yb-logo.png') }}" alt="Firma logosu">',
                    '@{{YB_MARK_LOGO}}': '<img src="{{ asset('storage/teklif-sablon-logolari/yb-logo.png') }}" alt="YB logo">',
                    '@{{FIRMA_UNVAN}}': 'Yalova Bilgisayar Teknik Servis',
                    '@{{MUSTERI_AD}}': 'Örnek Cari / Müşteri',
                    '@{{MUSTERI_ADRES}}': 'Yalova / Merkez',
                    '@{{MUSTERI_TELEFON}}': '+90 (555) 000 00 00',
                    '@{{TEKLIF_NO}}': 'TKL-2026-0001',
                    '@{{TEKLIF_TARIHI}}': '08.08.2026',
                    '@{{GECERLILIK_TARIHI}}': '23.08.2026',
                    '@{{ARA_TOPLAM}}': '829,90 USD',
                    '@{{TOPLAM_INDIRIM}}': '0,00 USD',
                    '@{{GENEL_TOPLAM}}': '995,88 USD',
                    '@{{ISKONTO_ORANI}}': '0%',
                    '@{{YB_KALEM_TABLOSU}}': '<tr><td>1</td><td>Örnek ürün / hizmet</td><td>1</td><td>100,00 USD</td><td>100,00 USD</td></tr>',
                    '@{{KALEMLER_TABLOSU}}': '<tr><td>1</td><td>Örnek ürün / hizmet</td><td>1</td><td>100,00 USD</td><td>100,00 USD</td></tr>',
                };

                let html = this.htmlField.value || '<div style="padding:24px;font-family:Arial">Şablon HTML alanı boş.</div>';
                Object.entries(replacements).forEach(([token, value]) => {
                    html = html.split(token).join(value);
                });

                const doc = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'
                    + 'html,body{margin:0;padding:0;background:#fff}img{max-width:100%}'
                    + (this.cssField.value || '')
                    + '</style></head><body>' + html + '</body></html>';

                this.$refs.frame.srcdoc = doc;
            },
        };
    };
</script>
