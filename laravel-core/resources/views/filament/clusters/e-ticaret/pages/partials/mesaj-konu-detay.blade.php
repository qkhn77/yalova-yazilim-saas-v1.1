<div class="space-y-4">
    <div class="rounded-lg border border-gray-200 p-3 text-sm">
        <div><strong>Konu:</strong> {{ $konu->baslik }}</div>
        <div><strong>Durum:</strong> {{ \App\Support\EcommerceMesajTanimlari::durumlar()[$konu->durum] ?? $konu->durum }}</div>
        <div><strong>Musteri:</strong> {{ $konu->musteri_ad_soyad ?: '-' }} / {{ $konu->musteri_email ?: '-' }}</div>
    </div>

    <div class="max-h-[420px] space-y-3 overflow-y-auto rounded-lg border border-gray-200 p-3">
        @forelse($konu->mesajlar as $mesaj)
            <div class="rounded-md border p-3 text-sm {{ $mesaj->gonderen_tipi === 'admin' ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-white' }}">
                <div class="mb-1 flex items-center justify-between text-xs text-gray-600">
                    <span>
                        {{ $mesaj->gonderen_tipi === 'admin' ? 'Admin' : 'Musteri' }}
                        @if($mesaj->ic_not_mu)
                            / Ic Not
                        @endif
                    </span>
                    <span>{{ optional($mesaj->created_at)->format('d.m.Y H:i') }}</span>
                </div>
                <div class="whitespace-pre-line">{{ $mesaj->icerik }}</div>
            </div>
        @empty
            <div class="text-sm text-gray-500">Bu konuda mesaj kaydi bulunmuyor.</div>
        @endforelse
    </div>
</div>