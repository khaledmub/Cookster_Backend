@extends('frontend.layouts.app')

@section('meta_title', $data['page']->meta_title)
@section('meta_description', $data['page']->meta_description)
@section('meta_keywords', $data['page']->meta_keywords)

@section('content')
@php
$pageImage = \App\Helpers\AppHelper::cmsMediaUrl('pages', $data['page']->image ?? null);
@endphp
<!-- Banner -->
    <div class="inner_banner_parent">
        <div class="container-fluid">
            <div class="inner_banner"@if($pageImage) style="background-image: url({{ $pageImage }});"@endif>
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
        <div class="row align-items-center">
            @if($pageImage)
            <div class="col-sm-6 wow fadeInLeft" data-wow-delay="0.4s">
                <div class="os_img">
                    <img src="{{ $pageImage }}" alt="{{ $data['page']->title }}">
                </div>
            </div>
            <div class="col-sm-6 wow fadeInRight" data-wow-delay="0.5s">
            @else
            <div class="col-sm-12 wow fadeInLeft" data-wow-delay="0.4s">
            @endif
                <h3>{{$data['page']->title}}</h3>
                <h2>{{$data['page']->sub_title}}</h2>
                {!! \App\Helpers\AppHelper::rewriteCmsHtml($data['page']->description) !!}
            </div>
        </div>
    </div>
    </div>
@endsection
