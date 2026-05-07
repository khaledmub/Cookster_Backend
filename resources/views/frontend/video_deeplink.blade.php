<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $video->title ?: 'Cookster Video' }}</title>
    <meta name="description" content="{{ $video->description ?: 'Open this Cookster video in the app.' }}">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f6f7fb; color: #222; }
        .wrap { max-width: 680px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .thumb { width: 100%; border-radius: 12px; background: #eee; object-fit: cover; max-height: 360px; }
        .title { margin: 16px 0 8px; font-size: 24px; }
        .meta { color: #666; margin-bottom: 16px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
        .btn { display: inline-block; padding: 12px 16px; border-radius: 10px; text-decoration: none; font-weight: 700; }
        .btn-primary { background: #111827; color: #fff; }
        .btn-secondary { background: #f3f4f6; color: #111827; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @if (!empty($video->image_url))
                <img class="thumb" src="{{ $video->image_url }}" alt="{{ $video->title }}">
            @endif

            <h1 class="title">{{ $video->title ?: 'Cookster Video' }}</h1>
            @if (!empty($video->user_name))
                <div class="meta">By {{ $video->user_name }}</div>
            @endif
            @if (!empty($video->description))
                <p>{{ $video->description }}</p>
            @endif

            <div class="actions">
                <a class="btn btn-primary" href="{{ $appSchemeUrl }}">Open In App</a>
                <a class="btn btn-secondary" href="{{ $androidStoreUrl }}">Google Play</a>
                <a class="btn btn-secondary" href="{{ $iosAppStoreUrl }}">App Store</a>
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
