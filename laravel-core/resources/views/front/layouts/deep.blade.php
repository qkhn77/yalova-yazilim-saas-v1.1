<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Yalova Yazılım ile işletmenizi büyüten sade, hızlı ve ölçeklenebilir SaaS çözümleri.">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" type="image/png" href="{{ asset('themes/deep/images/deeplogo-light.png') }}">
    <title>@yield('title', 'Yalova Yazılım | SaaS Çözümleri')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Unbounded:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('themes/deep/css/home.css') }}">
</head>
<body class="deep-page">
    @yield('content')

    <script src="{{ asset('themes/deep/js/home.js') }}"></script>
    @stack('scripts')
</body>
</html>
