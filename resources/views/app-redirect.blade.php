<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>body { background: #fff; }</style>
</head>
<body>
    <script>
        var isAndroid = {{ $isAndroid ? 'true' : 'false' }};
        var isIOS = {{ $isIos ? 'true' : 'false' }};

        if (isAndroid) {
            // Your logic for Android
            window.location.href = "intent://profile_details#Intent;scheme=https;package=com.cookster.cooksterapp;end";
        } else if (isIOS) {
            // Your logic for iOS (Fixed quotes)
            window.location.href = "cookster://profile_details";
        }
        
        // Optional: Close the tab or go back if nothing happens after a few seconds
        setTimeout(function() {
            window.close();
        }, 5000);
    </script>
</body>
</html>