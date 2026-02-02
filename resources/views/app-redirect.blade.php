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
        var id = {{ $id }};

        if (isAndroid) {
            // Your logic for Android
            window.location.href = "intent://api/profile_details?id=" + id + "#Intent;scheme=cookster;package=com.cookster.cooksterapp;end";
        } else if (isIOS) {
            // Your logic for iOS (Fixed quotes)
            window.location.href = "cookster://api/profile_details?id=" + id;
        }
        
        // Optional: Close the tab or go back if nothing happens after a few seconds
        setTimeout(function() {
            window.close();
        }, 5000);
    </script>
</body>
</html>