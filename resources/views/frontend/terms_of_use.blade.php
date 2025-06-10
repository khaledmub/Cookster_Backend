@extends('frontend.layouts.app')

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
    <div class="content_main_body mt-4 mb-4">
    <div class="container">
        <h1>{{$data['page']->title}}</h1>
        {!! $data['page']->description !!}
    </div>
    </div>
@endsection