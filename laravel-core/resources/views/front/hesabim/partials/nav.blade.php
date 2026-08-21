@php
    $hesapNav = [
        ['route' => 'account.index', 'active' => 'account.index', 'label' => __('front.account.summary')],
        ['route' => 'account.profile', 'active' => 'account.profile', 'label' => __('front.account.profile_title')],
        ['route' => 'account.addresses', 'active' => 'account.addresses', 'label' => __('front.account.addresses_title')],
        ['route' => 'account.orders', 'active' => 'account.orders*', 'label' => __('front.account.orders_title')],
        ['route' => 'account.messages', 'active' => 'account.messages*', 'label' => __('front.account.messages_title')],
    ];
@endphp

<style>
    .account-page-shell {
        max-width: 1080px;
        margin: 0 auto;
        padding-inline: 18px;
    }

    .account-page-frame {
        background: #ffffff;
        border: 1px solid rgba(15, 76, 129, 0.09);
        border-radius: 24px;
        box-shadow: 0 18px 50px rgba(15, 76, 129, 0.08);
        padding: 22px;
    }

    .account-inner-card {
        border: 1px solid rgba(15, 76, 129, .08);
        border-radius: 20px;
        background: linear-gradient(135deg, #fbfdff 0%, #ffffff 100%);
        box-shadow: none;
    }

    .account-nav-wrap {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-bottom: 28px;
    }

    .account-nav-btn {
        border-radius: 999px;
        padding: 9px 18px;
        font-weight: 700;
        border-color: rgba(15, 76, 129, .18);
        color: #14324d;
        background: #fff;
    }

    .account-nav-btn:hover,
    .account-nav-btn.is-active {
        background: #d71920;
        border-color: #d71920;
        color: #fff;
    }

    @media (max-width: 575.98px) {
        .account-page-shell {
            padding-inline: 10px;
        }

        .account-page-frame {
            padding: 14px;
            border-radius: 20px;
        }
    }
</style>

<div class="account-nav-wrap">
    @foreach($hesapNav as $item)
        <a
            class="btn account-nav-btn {{ request()->routeIs($item['active']) ? 'is-active' : '' }}"
            href="{{ route($item['route']) }}"
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
