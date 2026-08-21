@extends('front.layouts.app')

@section('title', __('front.account.orders_title'))

@section('content')
    @php
        $fiyatServisi = app(\App\Services\Front\FrontFiyatServisi::class);
    @endphp
    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.orders_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="account-page-shell">
            <div class="account-page-frame">
                @include('front.hesabim.partials.nav')

                <div class="card account-inner-card p-2 p-md-3">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>{{ __('front.account.order_no') }}</th>
                                <th>{{ __('front.account.status') }}</th>
                                <th>{{ __('front.account.amount') }}</th>
                                <th>{{ __('front.account.date') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($siparisler as $siparis)
                                <tr>
                                    <td>{{ $siparis->siparis_no }}</td>
                                    <td>{{ \App\Models\Ecommerce\Siparis::durumEtiketi($siparis->durum) }}</td>
                                    <td>{{ $fiyatServisi->cevirVeFormatla((float) $siparis->genel_toplam, (string) ($siparis->para_birimi ?: 'TRY')) }}</td>
                                    <td>{{ optional($siparis->created_at)->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('account.orders.show', ['siparis' => $siparis->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('front.account.detail') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">{{ __('front.account.no_orders') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3">
                    {{ $siparisler->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
