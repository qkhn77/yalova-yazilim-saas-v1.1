<?php

namespace App\Http\Controllers;

use App\Services\Front\FrontTercihServisi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FrontTercihController extends Controller
{
    public function __construct(
        private readonly FrontTercihServisi $tercihServisi,
    ) {}

    public function dilGuncelle(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', 'max:5'],
        ]);

        $locale = $this->tercihServisi->dilNormalize((string) $data['locale']);
        $request->session()->put(FrontTercihServisi::SESSION_LOCALE, $locale);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'locale' => $locale]);
        }

        return back();
    }

    public function paraBirimiGuncelle(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'max:3'],
        ]);

        $currency = $this->tercihServisi->paraBirimiNormalize((string) $data['currency']);
        $request->session()->put(FrontTercihServisi::SESSION_CURRENCY, $currency);
        $request->session()->forget([
            'aktif_sepet_ara_toplam',
            'aktif_sepet_genel_toplam',
            'cart_recently_added',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'currency' => $currency]);
        }

        return back();
    }
}
