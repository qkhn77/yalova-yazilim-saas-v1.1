@extends('front.layouts.app')

@section('title', __('front.account.messages_title'))

@section('content')
    @php
        $tipMap = [
            'musteri' => __('front.account.message_type_customer'),
            'urun' => __('front.account.message_type_product'),
        ];
        $durumMap = [
            'yeni' => __('front.account.message_status_new'),
            'okunmamis' => __('front.account.message_status_unread'),
            'yanitlandi' => __('front.account.message_status_replied'),
            'musteri_yaniti_geldi' => __('front.account.message_status_customer_reply'),
            'tamamlandi' => __('front.account.message_status_completed'),
        ];
        $durumRenk = [
            'yeni' => 'bg-primary',
            'okunmamis' => 'bg-warning text-dark',
            'yanitlandi' => 'bg-info',
            'musteri_yaniti_geldi' => 'bg-warning text-dark',
            'tamamlandi' => 'bg-success',
        ];
    @endphp
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.messages_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="account-page-shell">
            <div class="account-page-frame">
                @include('front.hesabim.partials.nav')

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="text-muted">{{ __('front.account.messages_hint') }}</div>
                    <a href="{{ route('account.messages.new') }}" class="btn btn-primary">{{ __('front.account.new_message') }}</a>
                </div>

                <div class="row g-3">
                    @forelse($konular as $konu)
                        @php
                            $sonMesaj = $konu->sonMesaj;
                            $icerik = $sonMesaj?->icerik ? trim((string) $sonMesaj->icerik) : '';
                            $icerik = $icerik !== '' ? \Illuminate\Support\Str::limit($icerik, 140) : __('front.account.no_message_preview');
                            $durumEtiket = $durumMap[$konu->durum] ?? $konu->durum;
                            $durumSinif = $durumRenk[$konu->durum] ?? 'bg-secondary';
                        @endphp
                        <div class="col-12">
                            <div class="card account-inner-card p-3 p-md-4 h-100">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="small text-muted">{{ $tipMap[$konu->konu_tipi] ?? $konu->konu_tipi }}</div>
                                        <h5 class="mb-1">{{ $konu->baslik }}</h5>
                                        @if($konu->stokKarti)
                                            <div class="small">
                                                {{ __('front.account.product') }}:
                                                <a href="{{ route('products.show', ['slug' => $konu->stokKarti->slug]) }}" class="text-decoration-none">
                                                    {{ $konu->stokKarti->ad }}
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-end">
                                        <span class="badge {{ $durumSinif }}">{{ $durumEtiket }}</span>
                                        @if((int) $konu->okunmamis_mesaj_sayisi > 0)
                                            <div class="mt-2">
                                                <span class="badge bg-danger">{{ __('front.account.unread_count', ['count' => (int) $konu->okunmamis_mesaj_sayisi]) }}</span>
                                            </div>
                                        @endif
                                        <div class="small text-muted mt-2">{{ optional($konu->updated_at)->format('d.m.Y H:i') }}</div>
                                    </div>
                                </div>

                                <div class="mt-3 text-muted">
                                    <strong>{{ __('front.account.last_message') }}:</strong>
                                    <div class="mt-1">{{ $icerik }}</div>
                                </div>
                                @if($konu->siparis)
                                    <div class="mt-2 small">
                                        {{ __('front.account.order_no') }}:
                                        <a href="{{ route('account.orders.show', ['siparis' => $konu->siparis->id]) }}" class="text-decoration-none">
                                            {{ $konu->siparis->siparis_no }}
                                        </a>
                                    </div>
                                @endif

                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="{{ route('account.messages.show', ['konu' => $konu->id]) }}" class="btn btn-outline-primary">
                                        {{ __('front.account.open_thread') }}
                                    </a>
                                    @if($konu->stokKarti)
                                        <a href="{{ route('products.show', ['slug' => $konu->stokKarti->slug]) }}" class="btn btn-outline-secondary">
                                            {{ __('front.account.view_product') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-info mb-0">{{ __('front.account.no_messages') }}</div>
                        </div>
                    @endforelse
                </div>

                <div class="mt-3">
                    {{ $konular->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
