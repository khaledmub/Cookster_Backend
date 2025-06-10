@extends('frontend.layouts.app')
@section('content')
@php
$settings=\App\Helpers\AppHelper::get_site_settings();
@endphp
<!-- Banner -->
    <div class="inner_banner_parent">
        <div class="container-fluid">
            <div class="inner_banner">
                <div class="banner_overlay"></div>
                <div class="banner_content">
                    <div class="container">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                              <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('general.home') }}</a></li>
                              <li class="breadcrumb-item" aria-current="page">{{ __('general.contact_us') }}</li>
                            </ol>
                          </nav>
                          
                        <h1 class="text-center">{{ __('general.contact_us') }}</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Us -->
    <div id="contactus" class="contact_us">
        <div class="overlay"></div>
        <div class="container">
            <div class="row">
                <div class="col-sm-5 wow fadeInLeft" data-wow-delay="0.4s">
                    <h1>{{ __('general.text_17') }}</h1>
                    <p>{{ __('general.text_18') }}</p>

                    <div class="contact_detail">
                        <a href="tell:{{$settings->phone}}" class="mt-5 mb-5">
                            <div class="d-flex align-items-center mt-4 mb-4">
                                <div class="call_icon">
                                    <i class="fa-light fa-phone"></i>
                                </div>
                                <div class="call_btn_detail ms-3">
                                    <h5>{{$settings->phone}}</h5>
                                </div>
                            </div>
                        </a>
                        <a href="mailto:{{$settings->email}}" class="mt-5 mb-5">
                            <div class="d-flex align-items-center mt-4 mb-4">
                                <div class="call_icon">
                                    <i class="fa-light fa-envelope"></i>
                                </div>
                                <div class="call_btn_detail ms-3">
                                    <h5>{{$settings->email}}</h5>
                                </div>
                            </div>
                        </a>
                        <a class="mt-5 mb-5">
                            <div class="d-flex align-items-center mt-4 mb-4">
                                <div class="call_icon">
                                    <i class="fa-light fa-location-dot"></i>
                                </div>
                                <div class="call_btn_detail ms-3">
                                    <h5>{{$settings->address}}</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-sm-7 wow fadeInRight" data-wow-delay="0.5s">
                    <div class="contact_box">
                        <form id="contactForm">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="mb-3">
                                        <label for="exampleFormControlInput1" class="form-label">{{ __('general.name') }}</label>
                                        <input type="text" name="name" class="form-control" id="exampleFormControlInput1">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label for="exampleFormControlInput1" class="form-label">{{ __('general.email') }}</label>
                                        <input type="text" name="email" class="form-control" id="exampleFormControlInput1">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label for="exampleFormControlInput1" class="form-label">{{ __('general.phone') }}</label>
                                        <input type="text" name="phone" class="form-control" id="exampleFormControlInput1">
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="mb-3">
                                        <label for="exampleFormControlTextarea1" class="form-label">{{ __('general.message') }}</label>
                                        <textarea name="message" class="form-control" id="exampleFormControlTextarea1" rows="5"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-end"><button id="contact_btn_submit" type="button" class="btn-primary">{{ __('general.submit') }}</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
@endsection