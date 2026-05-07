@extends('frontend.layouts.app')

@section('meta_title', $data['page']->meta_title)
@section('meta_description', $data['page']->meta_description)
@section('meta_keywords', $data['page']->meta_keywords)

@section('content')
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
                              <li class="breadcrumb-item active" aria-current="page">{{$data['page']->title}}</li>
                            </ol>
                          </nav>
                          
                        <h1 class="text-center">{{$data['page']->title}}</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- About Us -->
    <div class="about_us">
    <div class="container">
        <div class="row">
            <div class="col-sm-12 wow fadeInLeft" data-wow-delay="0.4s">
                <h3>{{$data['page']->title}}</h3>
                <h2>{{$data['page']->sub_title}}</h2>
                {!! $data['page']->description !!}
            </div>
        </div>
    </div>
    </div>
@endsection