@extends('frontend.layouts.app')

@section('meta_title', $data['page']->meta_title)
@section('meta_description', $data['page']->meta_description)
@section('meta_keywords', $data['page']->meta_keywords)

@section('content')

@php
$settings=\App\Helpers\AppHelper::get_site_settings();
@endphp

    <!-- Banner -->
    <div class="main_banner_parent">
        <div class="swiper banners_swiper">
            <div class="swiper-wrapper">
                @foreach($data['banners'] as $banner)
                <div class="swiper-slide">
                    <div class="container-fluid">
                        <div class="px-5">
                            <div class="main_banner" style="background-image: url({{asset('storage/banners/'.$banner->image)}});">
                                <div class="banner_overlay"></div>
                                <div class="banner_content">
                                    <div class="container">
                                        <div class="row align-items-center">
                                            <div class="col-sm-6 wow fadeInLeft" data-wow-delay="0.4s">
                                                <h1>{{$banner->title}}</h1>
                                                <p>{{$banner->sub_title}}</p>
                                                <div class="bnr_btns mt-4">
                                                    <a target="_blank" href="{{ $settings->app_store_link }}"><img src="{{ asset('assets/frontend/images/appstore_btn.png') }}" alt=""></a>
                                                    <a target="_blank" href="{{ $settings->play_store_link }}"><img src="{{ asset('assets/frontend/images/playstore_btn.png') }}" alt=""></a>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 wow fadeInUp" data-wow-delay="0.6s">
                                                <div class="bnr_side_img">
                                                    <img src="{{ asset('assets/frontend/images/mobapp_mockup.png') }}" alt="">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="banners_slide_options">
                <div class="container">
                    <div class="row align-items-center pb-5">
                        <div class="col-sm-6">
                            <div class="swiper-pagination banners_pagination"></div>
                        </div>
                        <div class="col-sm-6">
                            <!-- <div class="banner_navigation">
                                <div class="swiper-button-prev banners_swiper_prev">
                                    <i class="fa-light fa-arrow-left"></i>
                                </div>
                                <div class="swiper-button-next banners_swiper_next">
                                    <i class="fa-light fa-arrow-right"></i>
                                </div>
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registrations -->
    <div class="multiple_registration">
        <div class="container">
            <div class="title">
                <h1>{{ __('general.text_1') }}</h1>
            </div>
            <div class="row">
                <div class="col-sm-4">
                    <div class="reg_bx" style="background-color: #FFFBE2;">
                        <div class="icn_bx">
                            <img src="{{ asset('assets/frontend/images/profile.svg') }}" alt="">
                        </div>
                        <h4>{{ __('general.text_2') }}</h4>
                        <p>{{ __('general.text_3') }}</p>
                        <a href="javascript:void(0)" class="btn-primary" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_2') }}</a>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="reg_bx" style="background-color: #F8F8F8;">
                        <div class="icn_bx">
                            <img src="{{ asset('assets/frontend/images/shop.svg') }}" alt="">
                        </div>
                        <h4>{{ __('general.text_4') }}</h4>
                        <p>{{ __('general.text_5') }}</p>
                        <a href="javascript:void(0)" class="btn-primary" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_4') }}</a>
                    </div>
                </div>
                <!-- <div class="col-sm-4">
                    <div class="reg_bx" style="background-color: #F2EBE8;">
                        <div class="icn_bx">
                            <img src="{{ asset('assets/frontend/images/chef_icon.svg') }}" alt="">
                        </div>
                        <h4>{{ __('general.text_6') }}</h4>
                        <p>{{ __('general.text_7') }}</p>
                        <a href="javascript:void(0)" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_6') }}</a>
                    </div>
                </div> -->
                <div class="col-sm-4">
                    <div class="reg_bx" style="background-color: #F2EBE8;">
                        <div class="icn_bx">
                            <img src="{{ asset('assets/frontend/images/sponsored_ads_icon.svg') }}" alt="">
                        </div>
                        <h4>{{ __('general.text_19') }}</h4>
                        <p>{{ __('general.text_20') }}</p>
                        <a href="javascript:void(0)" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#downloadappModal">{{ __('general.text_19') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
@endsection