<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
    <style>
        .navbar-brand img {
            margin-top: 20px;
            max-width: 200px;
        }
        .footer {
            font-family: Cairo, sans-serif;
            font-size: 13px;
            font-weight: 400;
            color: #77798C;
            padding: 30px;
            min-height: 70px;
            background: #fff;
            border-top: 1px solid #f2f2f2;
            width: 100%;
            display: -webkit-box;
            display: -webkit-flex;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-pack: center;
            -webkit-justify-content: center;
            -ms-flex-pack: center;
            justify-content: space-between;
            -webkit-align-items: center;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
        }
        .app-wrapper{
            display: flex;
            justify-content: center;
        }

    </style>
    @yield('styles')
</head>
<body>
<div class="container-fluid app-wrapper" style="justify-content: center">
    <div class="container" style="width: 80%">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="{{ asset('images/logo.png') }}" class="attachment-medium size-medium" alt="logo"
                         sizes="(max-width: 200px) 100vw, 207px" style="margin-top: 20px">
                </a>

            </div>
        </nav>
        <div class="container" style="padding: 1%">
            @yield('content')
        </div>
        <div class="footer" style="justify-content: space-between;">
            <div class="footer-left">
                <p>
                    {{\Carbon\Carbon::today()->format('Y')}} © Mikroelectron. ALL Rights Reserved.
                </p>
                <p>
                    AMMAN, Jordan. University Street, Khalifa Building 3rd floor
                </p>
            </div>
            <div class="footer-right">
{{--                <p>--}}
{{--                    Call :   +962 65344772--}}
{{--                </p>--}}
{{--                <p>--}}
{{--                  Whatsapp :   +962 790062196--}}
{{--                </p>--}}
            </div>

        </div>
    </div>
</div>
</body>
</html>
