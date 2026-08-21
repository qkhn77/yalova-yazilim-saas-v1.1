@extends('front.layouts.app')

@section('title', __('front.account.message_detail_title'))

@section('content')
    @php
        $durumMap = [
            'yeni' => __('front.account.message_status_new'),
            'okunmamis' => __('front.account.message_status_unread'),
            'yanitlandi' => __('front.account.message_status_replied'),
            'musteri_yaniti_geldi' => __('front.account.message_status_customer_reply'),
            'tamamlandi' => __('front.account.message_status_completed'),
        ];
    @endphp
    <style>
        .message-thread { display: flex; flex-direction: column; gap: 14px; }
        .message-bubble {
            max-width: 78%;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f4f6fb;
            position: relative;
        }
        .message-bubble.mine { margin-left: auto; background: #e7f1ff; }
        .message-meta { font-size: 12px; color: #6c757d; margin-top: 6px; }
        .message-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .message-header strong { font-weight: 700; }
    </style>
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.message_detail_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        @include('front.hesabim.partials.nav')

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card p-3 mb-3">
            <div><strong>{{ __('front.account.subject') }}:</strong> {{ $konu->baslik }}</div>
            <div><strong>{{ __('front.account.status') }}:</strong> {{ $durumMap[$konu->durum] ?? $konu->durum }}</div>
            @if($konu->siparis)
                <div>
                    <strong>{{ __('front.account.order_no') }}:</strong>
                    <a href="{{ route('account.orders.show', ['siparis' => $konu->siparis->id]) }}" class="text-decoration-none">
                        {{ $konu->siparis->siparis_no }}
                    </a>
                </div>
            @endif
            @if($konu->stokKarti)
                <div>
                    <strong>{{ __('front.account.product') }}:</strong>
                    <a href="{{ route('products.show', ['slug' => $konu->stokKarti->slug]) }}" class="text-decoration-none">
                        {{ $konu->stokKarti->ad }}
                    </a>
                </div>
            @endif
        </div>

        <div class="card p-3 mb-3">
            <div class="message-thread">
                @forelse($konu->mesajlar as $mesaj)
                    @php
                        $benimMi = $mesaj->gonderen_tipi !== 'admin';
                    @endphp
                    <div class="message-bubble {{ $benimMi ? 'mine' : '' }}">
                        <div class="message-header">
                            <strong>{{ $benimMi ? __('front.account.you') : __('front.account.support') }}</strong>
                        </div>
                        <div>{{ $mesaj->icerik }}</div>
                        <div class="message-meta">{{ optional($mesaj->created_at)->format('d.m.Y H:i') }}</div>
                    </div>
                @empty
                    <div class="text-muted">{{ __('front.account.no_message_in_topic') }}</div>
                @endforelse
            </div>
        </div>

        <div class="card p-3">
            <form method="POST" action="{{ route('account.messages.reply', ['konu' => $konu->id]) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="icerik">{{ __('front.account.your_reply') }}</label>
                    <textarea class="form-control @error('icerik') is-invalid @enderror" name="icerik" id="icerik" rows="4" required>{{ old('icerik') }}</textarea>
                    @error('icerik')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button class="btn-default" type="submit">{{ __('front.account.send_message') }}</button>
            </form>
        </div>
    </div>
@endsection
