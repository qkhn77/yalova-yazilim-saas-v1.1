<x-filament-panels::page>
    <div class="teklif-cork-screen teklif-cork-template-preview">
    <div class="teklif-cork-toolbar mb-4">
        <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
            onclick="document.getElementById('teklif-sablon-preview-frame')?.contentWindow?.print()"
        >
            Yazdir
        </button>
    </div>

    <style>
        .teklif-sablon-preview-frame {
            width: 100%;
            min-height: 72vh;
            border: 0;
            background: #fff;
        }
    </style>

        <div class="teklif-cork-preview-frame-shell">
            <iframe
                id="teklif-sablon-preview-frame"
                class="teklif-sablon-preview-frame"
                src="{{ $this->frameUrl() }}"
                title="Son kullanıcı teklif görünümü"
                loading="eager"
            ></iframe>
        </div>
    </div>
</x-filament-panels::page>
