@php
$settings=\App\Helpers\AppHelper::get_site_settings();
$works=\App\Helpers\AppHelper::get_works();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Stylesheets -->
    @if(app()->getLocale() == 'ar')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/bootstrap.rtl.min.css') }}">
    @else
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/bootstrap.min.css') }}">
    @endif
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/all.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/animate.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/main.css') }}">
    @if(app()->getLocale() == 'ar')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/rtl.css') }}">
    @else
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/frontend/css/ltr.css') }}">
    @endif
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300..700&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/icon" href="{{ asset('assets/frontend/images/favicon.ico') }}"/>

    <title>@yield('meta_title', 'Cookster')</title>
    <meta name="description" content="@yield('meta_description', 'Cookster')">
    <meta name="keywords" content="@yield('meta_keywords', 'Cookster,cookster')">
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    <link rel="canonical" href="{{ url()->current() }}">

</head>
<body dir="<?php if(app()->getLocale()=='ar'){ echo 'rtl'; }else{ echo 'ltr'; } ?>">
    <input type="hidden" id="baseurl" value="<?php echo config('app.url'); ?>">
    <input type="hidden" id="language" value="<?php echo App::getLocale(); ?>">
    <input type="hidden" id="pageName" value="{{ Route::currentRouteName() }}">

    <input type="hidden" id="success_translated_text" value="{{ __('general.success') }}">
    <input type="hidden" id="error_translated_text" value="{{ __('general.error') }}">
    <input type="hidden" id="ok_translated_text" value="{{ __('general.ok') }}">
    <input type="hidden" id="please_fill_all_the_fields_translated_text" value="{{ __('general.please_fill_all_the_fields') }}">

    <!-- Back To Top Button -->
    <a id="back_to_top_btn" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('general.text_16') }}"></a>

    <div class="modal fade" id="downloadappModal" tabindex="-1" aria-labelledby="downloadappModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="qr_popup">
                    <div class="logo"><img src="{{ asset('assets/frontend/images/logo_icon_y.svg') }}" alt=""></div>
                    <h3>{{ __('general.text_8') }}</h3>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="qr">
                                <h6 class="mb-4">{{ __('general.text_9') }}</h6>
                                <img src="{{ asset('assets/frontend/images/app_store_qr.svg') }}" alt="">
                            </div>
                            <div class="storebtns mt-4">
                                <a target="_blank" href="{{ $settings->app_store_link }}"><img src="{{ asset('assets/frontend/images/appstore_btn.png') }}" alt=""></a>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="qr">
                                <h6 class="mb-4">{{ __('general.text_10') }}</h6>
                                <img src="{{ asset('assets/frontend/images/play_store_qr.svg') }}" alt="">
                            </div>
                            <div class="storebtns mt-4">
                                <a target="_blank" href="{{ $settings->play_store_link }}"><img src="{{ asset('assets/frontend/images/playstore_btn.png') }}" alt=""></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>


    <!-- Header -->
    <div class="main_header">
        <div class="head_topbar">
            <div class="container">
                <div class="row top_bar_row">
                    <div class="col-sm-5">
                        <div class="th_part_a d-flex gap-4">
                            <nav class="navbar navbar-expand-lg">
                                <div class="container-fluid">
                                    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                                        <span class="navbar-toggler-icon"></span>
                                    </button>
                                    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
                                        <div class="offcanvas-header">
                                            <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menu</h5>
                                            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                                          </div>
                                          <div class="offcanvas-body">
                                              <ul class="main_ul navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                                                <li><a class="nav-link {{ request()->is('/') || request()->is('home') ? 'active' : '' }}" href="{{ url('/') }}">{{ __('general.home') }}</a></li>
                                                <li><a class="nav-link {{ request()->is('about_us') ? 'active' : '' }}" href="{{ url('/about_us') }}">{{ __('general.about_us') }}</a></li>
                                                <li><a class="nav-link {{ request()->is('contact_us') ? 'active' : '' }}" href="{{ url('/contact_us') }}">{{ __('general.contact_us') }}</a></li>
                                                <li><a class="nav-link {{ request()->is('blog') ? 'active' : '' }}" href="{{ url('/blog') }}">{{ __('general.blog') }}</a></li>
                                            </ul>
                                          </div>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <a href="{{ url('/') }}"><img src="{{ asset('assets/frontend/images/logo.svg') }}" alt=""></a>
                    </div>
                    <div class="col-sm-5 resposive_items_head_col">
                        <div class="th_part_b d-flex gap-2">
                            @if(app()->getLocale() == 'ar')
                            <a href="{{ url('change/lang?lang=en') }}" class="lang_btn"><img src="{{ asset('assets/frontend/images/english_flag.png') }}" class="me-2" alt="">{{ __('general.english') }}</a>
                            @else
                            <a href="{{ url('change/lang?lang=ar') }}" class="lang_btn"><img src="{{ asset('assets/frontend/images/saudi_flag.png') }}" class="me-2" alt="">{{ __('general.arabic') }}</a>
                            @endif
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="header_menu">
            <div class="container">
                
            </div>
        </div>
    </div>
    @yield('content')
     <!-- How it works -->
    <div class="how_it_works" id="how_works">
        <div class="container">
            <div class="title">
                <div class="row">
                    <div class="col-sm-6">
                        <h4>{{ __('general.text_11', ['number' => sizeof($works)]) }}</h4>
                        <h1>{{ __('general.text_12') }}</h1>
                        <p>{{ __('general.text_13') }}</p>
                    </div>
                    <div class="col-sm-6 text-end">
                        <div class="storebtns">
                            <a target="_blank" href="{{ $settings->app_store_link }}"><img src="{{ asset('assets/frontend/images/appstore_btn.png') }}" alt=""></a>
                            <a target="_blank" href="{{ $settings->play_store_link }}"><img src="{{ asset('assets/frontend/images/playstore_btn.png') }}" alt=""></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                @foreach($works as $work)
                <div class="col-sm-3">
                    <div class="white_box">
                        <h1>{{$work->number}}</h1>
                        <h2>{{$work->title}}</h2>
                        {!! $work->description !!}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>        
    <!-- Footer -->
    <div class="footer">
        <div class="container">
            <div class="title">
                <div class="title_left">
                    <div class="footer_logo">
                        <a href="{{ url('/') }}"><img src="{{ asset('assets/frontend/images/logo_icon.png') }}" alt=""></a>
                    </div>
                    <h6>{{ __('general.text_14') }}</h6>
                </div>
                <div class="title_right">
                    <div class="storebtns">
                        <a target="_blank" href="{{ $settings->app_store_link }}"><img src="{{ asset('assets/frontend/images/appstore_btn.png') }}" alt=""></a>
                        <a target="_blank" href="{{ $settings->play_store_link }}"><img src="{{ asset('assets/frontend/images/playstore_btn.png') }}" alt=""></a>
                    </div>
                </div>
            </div>

            <div class="footer_body">
                <div class="row">
                    <div class="col-sm-3">
                        <h4>{{ __('general.text_15') }}</h4>
                        <ul>
                            <li><a href="{{ url('/') }}">{{ __('general.home') }}</a></li>
                            <li><a href="{{ url('/about_us') }}">{{ __('general.about_us') }}</a></li>
                            <li><a href="{{ url('/blog') }}">{{ __('general.blog') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-sm-3">
                        <h4>{{ __('general.registration') }}</h4>
                        <ul>
                            <li><a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_2') }}</a></li>
                            <li><a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_4') }}</a></li>
                            <!-- <li><a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_6') }}</a></li> -->
                            <li><a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_19') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-sm-3">
                        <h4>{{ __('general.support') }}</h4>
                        <ul>
                            <li><a href="{{ url('/terms_of_use') }}">{{ __('general.terms_of_use') }}</a></li>
                            <li><a href="{{ url('/contact_us') }}">{{ __('general.contact_us') }}</a></li>
                            <li><a href="{{ url('/privacy_policy') }}">{{ __('general.privacy_policy') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-sm-3">
                        <h4>{{ __('general.social_links') }}</h4>
                        <div class="f_socail_icons">
                            @if($settings->facebook)
                            <a target="_blank" href="{{ $settings->facebook }}"><img src="{{ asset('assets/frontend/images/facebook.png') }}" alt=""></a>
                            @endif
                            @if($settings->twitter)
                            <a target="_blank" href="{{ $settings->twitter }}"><img src="{{ asset('assets/frontend/images/twitter.png') }}" alt=""></a>
                            @endif
                            @if($settings->instagram)
                            <a target="_blank" href="{{ $settings->instagram }}"><img src="{{ asset('assets/frontend/images/instagram.png') }}" alt=""></a>
                            @endif
                            @if($settings->linkedin)
                            <a target="_blank" href="{{ $settings->linkedin }}"><img src="{{ asset('assets/frontend/images/linkedin.png') }}" alt=""></a>
                            @endif
                            @if($settings->tiktok)
                            <a target="_blank" href="{{ $settings->tiktok }}"><img src="{{ asset('assets/frontend/images/tiktok.png') }}" alt=""></a>
                            @endif
                            @if($settings->snapchat)
                            <a target="_blank" href="{{ $settings->snapchat }}"><img src="{{ asset('assets/frontend/images/snapchat.png') }}" alt=""></a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer_bottom">
            <div class="container">
                <div class="row">
                    <div class="col-sm-3">
                        <p>© <?=date('Y')?>. {{ __('general.all_rights_reserved') }}</p>
                    </div>
                    <div class="col-sm-9 text-end">
                        <div class="th_part_a d-flex gap-4 justify-content-end">
                            <p>{{ __('general.powered_by') }}: <a target="_blank" href="https://www.abaskatech.com/">Abaska Technologies</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Scripts -->
    <script src="{{ asset('assets/frontend/js/jquery-3.6.3.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/wow.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/sweetalert.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/sharer.min.js') }}"></script>
    <script src="{{ asset('assets/frontend/js/custom.js') }}"></script>

    <script>
        new WOW().init();
    </script>
    

</body>
</html>