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

            var appScheme;
            var appStoreUrl;

            if(isAndroid){
                appScheme = "cookster://open.cookster.app/web/visitProfile?id="+id;
                appStoreUrl = "https://play.google.com/store/apps/details?id=com.cookster.cooksterapp";
            }
            else if(isIOS){
                appScheme = "cookster://open.cookster.app/web/visitProfile?id="+id;
                appStoreUrl = "https://apps.apple.com/us/app/cookster-كوكستر/id6746804733";
            }
            else{
                return;
            }

            window.location = appScheme;

            setTimeout(function () {
                window.location = appStoreUrl;
            }, 1500);
        }

        init();
    </script>
</body>
</html>
