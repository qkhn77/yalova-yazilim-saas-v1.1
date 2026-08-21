@php
    /** @var \App\Models\Muhasebe\StokKarti $record */
    $record = $getRecord();
    $url = $record->kapak_gorsel_url;
@endphp

@if($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="Büyük önizleme">
        <img
            src="{{ $url }}"
            alt="Stok görseli"
            loading="lazy"
            style="width:40px;height:40px;object-fit:cover;border-radius:0.5rem;border:1px solid #e5e7eb;"
        >
    </a>
@else
    <div
        aria-label="Görsel yok"
        title="Görsel yok"
        style="width:40px;height:40px;border-radius:0.5rem;background:#f3f4f6;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:11px;"
    >
        —
    </div>
@endif

