<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>body { background: #fff; }</style>
</head>
<body>
    <script>
        function init(){
            var isAndroid = "{{ $isAndroid ? 'true' : 'false' }}" === "true";
            var isIOS = "{{ $isIos ? 'true' : 'false' }}" === "true";
            var id = "{{ $id }}";

            // if (isAndroid) {
            //     // Your logic for Android
            //     window.location.href = "intent://api/profile_details?id=" + id + "#Intent;scheme=cookster;package=com.cookster.cooksterapp;end";
            // } else if (isIOS) {
            //     // Your logic for iOS (Fixed quotes)
            //     window.location.href = "cookster://api/profile_details?id=" + id;
            // }
            
            // // Optional: Close the tab or go back if nothing happens after a few seconds
            // setTimeout(function() {
            //     window.close();
            // }, 5000);

            var appScheme;
            var appStoreUrl;

            // Detect OS
            if(isAndroid){
                // Android-specific settings
                appScheme = "cookster://api/profile_details?id="+id;
                appStoreUrl = "https://play.google.com/store/apps/details?id=com.cookster.cooksterapp"; 
            }
            else if(isIOS){
                // iOS-specific settings
                appScheme = "cookster://api/profile_details?id="+id;
                appStoreUrl = "https://apps.apple.com/us/app/cookster-كوكستر/id6746804733"; 
            }
            else{
                // Desktop or other device: provide a web link or do nothing
                return; 
            }

            // Try to open the app
            window.location = appScheme;

            // Set a timeout as a fallback
            setTimeout(function () {
                // If the user is still in the browser after the timeout, redirect to the store
                window.location = appStoreUrl;
            }, 1500); // 1.5 second delay
        }

        init();
    </script>
</body>
</html>