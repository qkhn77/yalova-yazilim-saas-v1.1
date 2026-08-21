@extends('front.layouts.app')

@section('title', __('front.account.profile_title'))

@section('content')
    <style>
        .account-profile-shell {
            max-width: 980px;
            margin: 0 auto;
            padding-inline: 18px;
        }

        .account-profile-frame {
            background: #ffffff;
            border: 1px solid rgba(15, 76, 129, 0.09);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(15, 76, 129, 0.08);
            padding: 22px;
        }

        .account-profile-card {
            border: 1px solid rgba(15, 76, 129, .08);
            border-radius: 20px;
            background: linear-gradient(135deg, #fbfdff 0%, #ffffff 100%);
            box-shadow: none;
        }

        @media (max-width: 575.98px) {
            .account-profile-shell {
                padding-inline: 10px;
            }

            .account-profile-frame {
                padding: 14px;
                border-radius: 20px;
            }
        }
    </style>

    <div class="page-header">
        <div class="container">
            <div class="page-header-box">
                <h1 class="wow fadeInUp">{{ __('front.account.profile_title') }}</h1>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="account-profile-shell">
            <div class="account-profile-frame">
                @include('front.hesabim.partials.nav')

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <div class="card account-profile-card p-3 p-md-4">
                    <form method="POST" action="{{ route('account.profile.update') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="ad_soyad">{{ __('front.auth.name_surname') }}</label>
                                <input id="ad_soyad" name="ad_soyad" class="form-control" value="{{ old('ad_soyad', $kullanici->ad_soyad ?: $kullanici->name) }}" required>
                                @error('ad_soyad') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="email">{{ __('front.auth.email') }}</label>
                                <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $kullanici->email) }}" required>
                                @error('email') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="telefon">{{ __('front.auth.phone') }}</label>
                                <input id="telefon" name="telefon" type="tel" class="form-control" placeholder="+90 555 000 11 22" value="{{ old('telefon', $kullanici->telefon ?? '') }}">
                                @error('telefon') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mt-4 text-center">
                            <button type="submit" class="btn btn-primary px-4">{{ __('front.account.update_info') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
