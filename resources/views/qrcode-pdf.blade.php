<!DOCTYPE html>
<html>
<head>
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet'>
    <title>QR Code</title>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
        .text-24{
            font-size: 24px;
            font-weight: 700;
            line-height: 28px;
            margin: 0;
        }
        .text-20{
            font-size: 20px;
            font-weight: 600;
            line-height: 26px;
            margin: 0;
        }
        .text-18{
            font-size: 18px;
            font-weight: 400;
            line-height: 24px;
            margin: 0;
        }
        .text-16{
            font-size: 16px;
            font-weight: 400;
            line-height: 21px;
            margin: 0;
        }
        .text-14{
            font-size: 14px;
            font-weight: 400;
            line-height: 19px;
            margin: 0;
        }
        .text-12{
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
            margin: 0;
        }
        .fw-bold{
            font-weight: 700;
        }
        .w-50{
            width: 49.9%;
        }
        .text-end{
            text-align: right;
        }
        .text-center{
            text-align: center;
        }
        .text-start{
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: none;
        }
    </style>
</head>
<body>
    <div>
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="{{ asset('assets/frontend/images/logo_icon_y.svg') }}" alt="" style="width: 100px;">
        </div>
        <div style="text-align: center;">
            <img src="data:image/png;base64,{{ $qrcode_image }}" alt="" style="width: 680px;">
        </div>
        <div style="text-align: center;">
            @if($language == 'ar')
            <img src="{{ asset('assets/frontend/images/download_apps_qrcode_ar.png') }}" alt="" style="width: 680px;">
            @else
            <img src="{{ asset('assets/frontend/images/download_apps_qrcode.png') }}" alt="" style="width: 680px;">
            @endif
        </div>
    </div>
</body>
</html>