<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $user->name ?: 'Cookster Profile' }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $user->name ?: 'Cookster Profile' }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:type" content="profile">
    @if (!empty($user->image_url))
        <meta property="og:image" content="{{ $user->image_url }}">
    @endif
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f6f7fb; color: #222; }
        .wrap { max-width: 680px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); text-align: center; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; background: #eee; margin: 0 auto 16px; display: block; }
        .name { margin: 0 0 8px; font-size: 24px; }
        .handle { color: #666; margin-bottom: 16px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; justify-content: center; margin-top: 20px; }
        .btn { display: inline-block; padding: 12px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; }
        .btn-primary { background: #111827; color: #fff; }
        .btn-secondary { background: #f3f4f6; color: #111827; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @if (!empty($user->image_url))
                <img class="avatar" src="{{ $user->image_url }}" alt="{{ $user->name }}">
            @endif

            <h1 class="name">{{ $user->name ?: 'Cookster User' }}</h1>
            @if (!empty($user->user_name))
                <div class="handle">@{{ $user->user_name }}</div>
            @endif

            <p>{{ $metaDescription }}</p>

            <div class="actions">
                <a class="btn btn-primary" href="{{ $appSchemeUrl }}">Open In App</a>
                <a class="btn btn-secondary" href="{{ $androidStoreUrl }}">Google Play</a>
                <a class="btn btn-secondary" href="{{ $iosAppStoreUrl }}">App Store</a>
                <a class="btn btn-secondary" href="{{ route('home') }}">Cookster Home</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var ua = navigator.userAgent || '';
            var isAndroid = /Android/i.test(ua);
            var isIOS = /iPhone|iPad|iPod/i.test(ua);
            var fallbackUrl = isAndroid ? @json($androidStoreUrl) : (isIOS ? @json($iosAppStoreUrl) : null);

            if (!isAndroid && !isIOS) {
                return;
            }

            window.location.href = @json($appSchemeUrl);

            if (fallbackUrl) {
                setTimeout(function () {
                    window.location.href = fallbackUrl;
                }, 1500);
            }
        })();
    </script>
</body>
</html>
