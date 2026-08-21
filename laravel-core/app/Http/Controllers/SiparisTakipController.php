<?php

namespace App\Http\Controllers;

use App\Models\Ecommerce\Siparis;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiparisTakipController extends Controller
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
    ) {}

    public function index(): View
    {
        return view('front.checkout.siparis-takip');
    }

    public function sorgula(Request $request): View
    {
        $data = $request->validate([
            'siparis_no' => ['required', 'string', 'max:64'],
            'dogrulama' => ['required', 'string', 'max:255'],
        ], [
            'siparis_no.required' => 'Sipariş numarası zorunludur.',
            'dogrulama.required' => 'E-posta adresi veya telefon numarası zorunludur.',
        ]);

        $firmaId = (int) ($this->tenantContextService->aktifFirmaId() ?? 0);
        $siparisNo = trim((string) $data['siparis_no']);
        $dogrulama = trim((string) $data['dogrulama']);

        $siparis = Siparis::query()
            ->where('firma_id', $firmaId)
            ->where('siparis_no', $siparisNo)
            ->with(['kalemler.stokKarti.gorseller'])
            ->latest('id')
            ->get()
            ->first(fn (Siparis $siparis): bool => $this->dogrulamaEslesiyorMu($siparis, $dogrulama));

        if (! $siparis) {
            return view('front.checkout.siparis-takip', [
                'hata' => 'Bu bilgilerle eşleşen bir sipariş bulunamadı. Sipariş numarası, e-posta veya telefon bilgisini kontrol edin.',
            ]);
        }

        return view('front.hesabim.siparis-detay', [
            'siparis' => $siparis,
            'misafirTakip' => true,
        ]);
    }

    private function dogrulamaEslesiyorMu(Siparis $siparis, string $dogrulama): bool
    {
        $eposta = mb_strtolower(trim((string) ($siparis->musteri_email ?? '')), 'UTF-8');
        $girdiEposta = mb_strtolower($dogrulama, 'UTF-8');
        if ($eposta !== '' && $eposta === $girdiEposta) {
            return true;
        }

        $telefon = preg_replace('/\D+/', '', (string) ($siparis->musteri_telefon ?? '')) ?: '';
        $girdiTelefon = preg_replace('/\D+/', '', $dogrulama) ?: '';
        if ($telefon === '' || $girdiTelefon === '') {
            return false;
        }

        return str_ends_with($telefon, $girdiTelefon) || str_ends_with($girdiTelefon, $telefon);
    }
}
