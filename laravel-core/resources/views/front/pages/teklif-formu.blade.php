@extends('front.layouts.app')

@section('title', 'Teklif Formu | Yalova Bilgisayar Teknik Servis')
@section('meta_description', 'PDF tasarımına birebir yakın, responsive teklif formu arayüzü.')
@section('meta_keywords', 'teklif formu, teknik servis, pdfden html, responsive teklif')

@php
    $contactItems = [
        ['icon' => 'fa-location-dot', 'title' => 'Adres', 'text' => 'Sahil Mah. Yalı Cad. No:3/A Çiftlikköy / Yalova'],
        ['icon' => 'fa-file-lines', 'title' => 'Vergi', 'text' => 'Vergi Dairesi: Yalova  Vergi No: 451999618384'],
        ['icon' => 'fa-phone', 'title' => 'Telefon', 'text' => 'Tel: 0 (226) 352 07 24  Cep: 0 (553) 979 32 55'],
        ['icon' => 'fa-globe', 'title' => 'Web', 'text' => 'www.yalovabilgisayar.com'],
        ['icon' => 'fa-envelope', 'title' => 'E-posta', 'text' => 'info@yalovabilgisayar.com'],
    ];

    $customerFields = [
        ['label' => 'Firma Adı', 'multiline' => false],
        ['label' => 'Yetkili Kişi', 'multiline' => false],
        ['label' => 'Adres', 'multiline' => true],
        ['label' => 'Telefon', 'multiline' => false],
        ['label' => 'E-posta', 'multiline' => false],
        ['label' => 'Vergi Dairesi / No', 'multiline' => false],
    ];

    $offerFields = [
        ['label' => 'Teklif No', 'value' => ''],
        ['label' => 'Teklif Tarihi', 'value' => '__ / __ / 2024'],
        ['label' => 'Geçerlilik Tarihi', 'value' => '__ / __ / 2024'],
        ['label' => 'Teklif Geçerlilik Süresi', 'value' => '____ Gün'],
        ['label' => 'Teklifi Hazırlayan', 'value' => ''],
    ];

    $summaryRows = [
        ['label' => 'Ara Toplam', 'value' => '₺'],
        ['label' => 'İskonto Oranı', 'value' => '%'],
        ['label' => 'İskonto Tutarı', 'value' => '₺'],
        ['label' => 'KDV Oranı', 'value' => '%20'],
        ['label' => 'KDV Tutarı', 'value' => '₺'],
    ];

    $notes = [
        'Teklifte belirtilen fiyatlar ve şartlar teklif geçerlilik süresi sonuna kadar geçerlidir.',
        'Ödeme şartları ayrıca belirtilecektir.',
        'Teslimat süresi, sipariş onayına müteakip belirlenecektir.',
        'Teknik şartlar ve garanti koşulları, ürün/hizmete göre değişiklik gösterebilir.',
    ];
@endphp

@push('head_meta')
    <style>
        .main-header,
        .main-footer,
        body > footer[style] {
            display: none !important;
        }

        .floating-contact-stack {
            display: none !important;
        }

        body {
            background: #eef3fa;
        }

        .offer-pdf-page {
            --blue: #0d4ca5;
            --blue-dark: #09367a;
            --red: #ef2d28;
            --text: #1d2a3d;
            --paper-shadow: 0 32px 80px rgba(15, 40, 86, 0.18);
            padding: 32px 0 54px;
            color: var(--text);
            font-family: "Barlow", sans-serif;
        }

        .offer-pdf-wrap {
            width: min(100%, 1120px);
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: var(--paper-shadow);
            overflow: hidden;
        }

        .offer-sheet {
            padding: 0 0 22px;
            background:
                radial-gradient(circle at 50% 24%, rgba(13, 76, 165, 0.05), transparent 42%),
                linear-gradient(180deg, #ffffff 0%, #ffffff 100%);
        }

        .offer-top {
            display: grid;
            grid-template-columns: minmax(0, 1.9fr) minmax(310px, 1fr);
            gap: 18px;
            align-items: stretch;
            padding: 0 20px;
        }

        .offer-brand-block {
            position: relative;
            min-height: 264px;
            padding: 36px 34px 36px 42px;
            overflow: hidden;
            background: #0d1117;
            clip-path: polygon(0 0, 89% 0, 100% 54%, 89% 100%, 0 100%);
        }

        .offer-brand-block::after {
            content: "";
            position: absolute;
            left: 0;
            right: 7%;
            bottom: 0;
            height: 12px;
            background: #1982ec;
            clip-path: polygon(0 0, 100% 0, 94% 100%, 0 100%);
        }

        .offer-brand-inner {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .offer-brand-mark {
            color: #fff;
            font-size: 8rem;
            font-weight: 800;
            line-height: 0.82;
            letter-spacing: -0.12em;
            text-transform: lowercase;
        }

        .offer-brand-copy {
            line-height: 0.88;
        }

        .offer-brand-copy strong,
        .offer-brand-copy span {
            display: block;
        }

        .offer-brand-copy strong {
            color: #fff;
            font-size: clamp(2.8rem, 3.6vw, 4.1rem);
            font-weight: 700;
        }

        .offer-brand-copy span {
            color: #1982ec;
            font-size: clamp(3.4rem, 4.8vw, 5.4rem);
            font-weight: 800;
            letter-spacing: -0.06em;
        }

        .offer-brand-copy em {
            display: block;
            margin-top: 10px;
            color: var(--red);
            font-size: 1.2rem;
            font-style: normal;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .offer-contact {
            display: flex;
            align-items: center;
            padding: 18px 0 8px;
        }

        .offer-contact-card {
            width: 100%;
            border-radius: 0 0 0 24px;
            padding: 10px 12px 12px 6px;
        }

        .offer-contact-list {
            display: grid;
            gap: 12px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .offer-contact-item {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.25;
            color: #1e2c40;
        }

        .offer-contact-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #fff;
            background: linear-gradient(180deg, #0d4ca5 0%, #08387d 100%);
        }

        .offer-contact-item b {
            display: block;
            margin-bottom: 2px;
            font-size: 0.9rem;
            color: #5d6a80;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .offer-content {
            padding: 14px 24px 0;
        }

        .offer-title {
            margin: 8px 0 18px 6px;
            color: #0b0d10;
            font-size: clamp(3rem, 4.5vw, 4.7rem);
            line-height: 0.92;
            font-weight: 800;
            letter-spacing: -0.05em;
            text-transform: uppercase;
        }

        .offer-title span {
            color: var(--blue);
        }

        .offer-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 26px;
            margin-bottom: 18px;
        }

        .offer-box {
            background: #fbfcfe;
            border-radius: 14px;
            border: 1px solid rgba(6, 35, 78, 0.06);
            box-shadow: 0 10px 30px rgba(20, 38, 64, 0.06);
            padding: 18px 24px 16px;
        }

        .offer-box-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 270px;
            margin: -14px 0 18px;
            padding: 8px 16px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            font-size: 0.98rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .offer-field-list {
            display: grid;
            gap: 10px;
        }

        .offer-field-row {
            display: grid;
            grid-template-columns: 150px 12px minmax(0, 1fr);
            gap: 8px;
            align-items: end;
            font-size: 1rem;
            color: #1c2635;
        }

        .offer-field-row strong {
            font-weight: 700;
        }

        .offer-field-value {
            min-height: 28px;
            padding-bottom: 3px;
            border-bottom: 2px solid rgba(28, 38, 53, 0.36);
            font-weight: 600;
            white-space: nowrap;
        }

        .offer-field-row.multiline .offer-field-value {
            min-height: 56px;
        }

        .offer-table-wrap {
            margin-top: 10px;
            overflow-x: auto;
        }

        .offer-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(16, 53, 109, 0.16);
            border-radius: 10px;
            overflow: hidden;
        }

        .offer-table thead th {
            padding: 12px 10px;
            color: #fff;
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            border-right: 1px solid rgba(255, 255, 255, 0.22);
            text-align: center;
        }

        .offer-table thead th:last-child {
            border-right: 0;
        }

        .offer-table tbody td {
            height: 38px;
            padding: 6px 10px;
            border-top: 1px solid rgba(16, 53, 109, 0.10);
            border-right: 1px solid rgba(16, 53, 109, 0.10);
            background: #fff;
        }

        .offer-table tbody td:first-child {
            width: 84px;
            text-align: center;
            font-weight: 700;
        }

        .offer-table tbody td:last-child {
            border-right: 0;
        }

        .offer-bottom-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(360px, 0.95fr);
            gap: 22px;
            margin-top: 18px;
        }

        .offer-notes-box,
        .offer-summary-box {
            background: #fbfcfe;
            border-radius: 14px;
            border: 1px solid rgba(6, 35, 78, 0.06);
            box-shadow: 0 10px 30px rgba(20, 38, 64, 0.06);
        }

        .offer-notes-box {
            padding: 16px 18px 12px;
        }

        .offer-section-heading {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            color: var(--blue);
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .offer-notes-box ul {
            margin: 0;
            padding-left: 18px;
        }

        .offer-notes-box li {
            margin-bottom: 8px;
            font-size: 0.98rem;
            line-height: 1.45;
        }

        .offer-warranty {
            display: grid;
            grid-template-columns: 68px minmax(0, 1fr);
            gap: 14px;
            align-items: center;
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid rgba(16, 53, 109, 0.10);
        }

        .offer-warranty-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            font-size: 1.7rem;
        }

        .offer-warranty p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.55;
        }

        .offer-warranty strong {
            color: var(--blue);
        }

        .offer-summary-box {
            overflow: hidden;
        }

        .offer-summary-box table {
            width: 100%;
            border-collapse: collapse;
        }

        .offer-summary-box td {
            padding: 14px 18px;
            font-size: 1rem;
            font-weight: 700;
            border-bottom: 1px solid rgba(16, 53, 109, 0.12);
        }

        .offer-summary-box td:last-child {
            width: 120px;
            text-align: right;
        }

        .offer-summary-total td {
            color: #fff;
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            font-size: 1.2rem;
            text-transform: uppercase;
            border-bottom: 0;
        }

        .offer-sign-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 1px minmax(250px, 0.62fr);
            gap: 24px;
            align-items: center;
            margin-top: 22px;
            padding: 18px 4px 0;
        }

        .offer-sign-divider {
            align-self: stretch;
            background: linear-gradient(180deg, rgba(16, 53, 109, 0), rgba(16, 53, 109, 0.35), rgba(16, 53, 109, 0));
        }

        .offer-thanks {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .offer-thanks-icon {
            width: 64px;
            height: 64px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #fff;
            background: linear-gradient(180deg, var(--blue) 0%, var(--blue-dark) 100%);
            font-size: 1.65rem;
        }

        .offer-thanks p {
            margin: 0;
            font-size: 1.2rem;
            font-style: italic;
            line-height: 1.3;
            color: #364259;
            font-family: "Georgia", "Times New Roman", serif;
        }

        .offer-thanks strong {
            display: block;
            margin-top: 8px;
            color: var(--blue);
            font-size: 1.55rem;
            font-style: normal;
            font-weight: 800;
            font-family: "Barlow", sans-serif;
        }

        .offer-approval {
            padding-left: 18px;
        }

        .offer-approval h3 {
            margin: 0;
            color: var(--blue);
            font-size: 1.2rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .offer-approval p {
            margin: 6px 0 26px;
            font-size: 1rem;
            font-weight: 500;
        }

        .offer-signature-line {
            width: 100%;
            max-width: 240px;
            height: 44px;
            border-bottom: 2px solid rgba(28, 38, 53, 0.36);
        }

        .offer-disclaimer {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 56px;
            margin-top: 18px;
            padding: 0 24px;
            color: #fff;
            background: #101317;
            font-size: 1rem;
            font-weight: 600;
        }

        .offer-disclaimer::after {
            content: "";
            position: absolute;
            right: 0;
            top: 0;
            width: 34%;
            height: 100%;
            background: #0d4ca5;
            clip-path: polygon(14% 0, 100% 0, 100% 100%, 0 100%);
        }

        .offer-disclaimer span {
            position: relative;
            z-index: 1;
        }

        .offer-toolbar {
            display: flex;
            justify-content: flex-end;
            padding: 18px 20px 0;
        }

        .offer-print-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 999px;
            color: var(--blue);
            background: #fff;
            border: 1px solid rgba(16, 53, 109, 0.14);
            text-decoration: none;
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .offer-top,
            .offer-info-grid,
            .offer-bottom-grid,
            .offer-sign-row {
                grid-template-columns: 1fr;
            }

            .offer-sign-divider {
                display: none;
            }

            .offer-brand-block {
                clip-path: none;
                border-radius: 0 0 18px 18px;
            }

            .offer-contact {
                padding-top: 0;
            }

            .offer-contact-card {
                border-radius: 18px;
                padding: 18px 12px 6px;
            }
        }

        @media (max-width: 767.98px) {
            .offer-pdf-page {
                padding-top: 0;
            }

            .offer-pdf-wrap {
                border-radius: 0;
            }

            .offer-top,
            .offer-content,
            .offer-toolbar {
                padding-left: 12px;
                padding-right: 12px;
            }

            .offer-brand-block {
                min-height: auto;
                padding: 26px 22px 28px;
            }

            .offer-brand-inner {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .offer-brand-mark {
                font-size: 6.2rem;
            }

            .offer-title {
                margin-left: 0;
                font-size: 2.6rem;
            }

            .offer-box {
                padding-left: 16px;
                padding-right: 16px;
            }

            .offer-box-label {
                min-width: 0;
                width: 100%;
                justify-content: center;
                margin-top: -10px;
            }

            .offer-field-row {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .offer-field-row span:nth-child(2) {
                display: none;
            }

            .offer-summary-box td {
                padding-left: 14px;
                padding-right: 14px;
            }

            .offer-disclaimer::after {
                width: 42%;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .offer-toolbar {
                display: none !important;
            }

            .offer-pdf-page {
                padding: 0;
            }

            .offer-pdf-wrap {
                width: 100%;
                box-shadow: none;
            }
        }
    </style>
@endpush

@section('content')
    <section class="offer-pdf-page">
        <div class="offer-pdf-wrap">
            <div class="offer-sheet">
                <div class="offer-toolbar">
                    <a href="javascript:window.print()" class="offer-print-btn">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                        Yazdır
                    </a>
                </div>

                <div class="offer-top">
                    <div class="offer-brand-block">
                        <div class="offer-brand-inner">
                            <div class="offer-brand-mark">yb</div>

                            <div class="offer-brand-copy">
                                <strong>Yalova</strong>
                                <span>Bilgisayar</span>
                                <em>Teknik Servis</em>
                            </div>
                        </div>
                    </div>

                    <div class="offer-contact">
                        <div class="offer-contact-card">
                            <ul class="offer-contact-list">
                                @foreach ($contactItems as $item)
                                    <li class="offer-contact-item">
                                        <span class="offer-contact-icon">
                                            <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>
                                        </span>
                                        <span>
                                            <b>{{ $item['title'] }}</b>
                                            {{ $item['text'] }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="offer-content">
                    <h1 class="offer-title"><span>Teklif</span> Formu</h1>

                    <div class="offer-info-grid">
                        <section class="offer-box">
                            <div class="offer-box-label">
                                <i class="fa-solid fa-id-card-clip" aria-hidden="true"></i>
                                Müşteri Bilgileri
                            </div>

                            <div class="offer-field-list">
                                @foreach ($customerFields as $field)
                                    <div class="offer-field-row {{ $field['multiline'] ? 'multiline' : '' }}">
                                        <strong>{{ $field['label'] }}</strong>
                                        <span>:</span>
                                        <div class="offer-field-value"></div>
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="offer-box">
                            <div class="offer-box-label">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                Teklif Bilgileri
                            </div>

                            <div class="offer-field-list">
                                @foreach ($offerFields as $field)
                                    <div class="offer-field-row">
                                        <strong>{{ $field['label'] }}</strong>
                                        <span>:</span>
                                        <div class="offer-field-value">{{ $field['value'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    </div>

                    <div class="offer-table-wrap">
                        <table class="offer-table">
                            <thead>
                                <tr>
                                    <th>Sıra No</th>
                                    <th>Ürün / Hizmet Açıklaması</th>
                                    <th>Marka / Model</th>
                                    <th>Adet</th>
                                    <th>Birim Fiyatı (₺)</th>
                                    <th>Toplam Fiyat (₺)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 1; $i <= 15; $i++)
                                    <tr>
                                        <td>{{ $i }}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <div class="offer-bottom-grid">
                        <section class="offer-notes-box">
                            <div class="offer-section-heading">
                                <i class="fa-regular fa-note-sticky" aria-hidden="true"></i>
                                Notlar / Açıklamalar
                            </div>

                            <ul>
                                @foreach ($notes as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>

                            <div class="offer-warranty">
                                <div class="offer-warranty-icon">
                                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                                </div>
                                <p>
                                    <strong>Garanti</strong>
                                    Ürünler fatura tarihinden itibaren
                                    <strong>2 yıl cihaz garantisi</strong> ve
                                    <strong>6 ay işçilik garantisi</strong> kapsamındadır.
                                </p>
                            </div>
                        </section>

                        <section class="offer-summary-box">
                            <table>
                                <tbody>
                                    @foreach ($summaryRows as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td>{{ $row['value'] }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="offer-summary-total">
                                        <td>Satır Toplamı</td>
                                        <td>₺</td>
                                    </tr>
                                </tbody>
                            </table>
                        </section>
                    </div>

                    <div class="offer-sign-row">
                        <section class="offer-thanks">
                            <div class="offer-thanks-icon">
                                <i class="fa-regular fa-handshake" aria-hidden="true"></i>
                            </div>

                            <div>
                                <p>Bizi tercih ettiğiniz için teşekkür ederiz.</p>
                                <strong>Yalova Bilgisayar Teknik Servis</strong>
                            </div>
                        </section>

                        <div class="offer-sign-divider" aria-hidden="true"></div>

                        <section class="offer-approval">
                            <h3>Teklifi Onaylayan</h3>
                            <p>Ad - Soyad / Kaşe - İmza</p>
                            <div class="offer-signature-line"></div>
                        </section>
                    </div>
                </div>

                <div class="offer-disclaimer">
                    <span><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span>
                    <span>Fiyatlarımıza KDV dahil değildir.</span>
                </div>
            </div>
        </div>
    </section>
@endsection
