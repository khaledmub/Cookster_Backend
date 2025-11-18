@extends('frontend.layouts.app')

@if($data['blog_details'])
    @section('meta_title', $data['blog_details']->meta_title)
    @section('meta_description', $data['blog_details']->meta_description)
    @section('meta_keywords', $data['blog_details']->meta_keywords)
@endif

@section('content')
<!-- Banner -->
    @if($data['blog_details'])
        <div class="inner_banner_parent">
            <div class="container-fluid">
                <div class="inner_banner inner_banner_blog" style="background-image: url({{asset('storage/blogs/'.$data['blog_details']->image)}});"></div>
            </div>
        </div>

        <div class="about_us">
            <div class="container">
                <div class="blog_detail_page">
                    <h1 class="fw-bold mb-3">{{ $data['blog_details']->title }}</h1>

                    <div class="d-flex justify-content-between flex-wrap gap-4 mb-4">
                        <div class="d-flex flex-wrap gap-4">
                            <div class="blog_detail_page_date">
                                <div class="date_icon_box">
                                    <i class="fa-light fa-calendar"></i>
                                </div>
                                <div>
                                    <h6>{{ __('general.date') }}</h6>
                                    <h5 class="fw-bold mb-0">{{ date('d M, Y', strtotime($data['blog_details']->date)) }}</h5>
                                </div>
                            </div>
                            <div class="blog_detail_page_date">
                                <div class="date_icon_box">
                                    <i class="fa-light fa-grid-2"></i>
                                </div>
                                <div>
                                    <h6>{{ __('general.category') }}</h6>
                                    <h5 class="fw-bold mb-0">{{ $data['blog_details']->category_title }}</h5>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button type="button" class="btn btn-primary dropdown-toggle share_btn" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-share me-2"></i> {{ __('general.share') }}</button>
                            <ul class="dropdown-menu">
                                <li><a type="button" class="dropdown-item" data-sharer="x" data-title="{{ $data['blog_details']->title }}" data-hashtags="{{ $data['blog_details']->meta_keywords }}" data-url="{{ url('/blog/' . \Illuminate\Support\Str::slug($data['blog_details']->category_title) . '/' . \Illuminate\Support\Str::slug($data['blog_details']->title)) }}"><i class="fa-brands fa-x-twitter me-2"></i> {{ __('general.twitter_x') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a type="button" class="dropdown-item" data-sharer="facebook" data-hashtag="{{ $data['blog_details']->meta_keywords }}" data-url="{{ url('/blog/' . \Illuminate\Support\Str::slug($data['blog_details']->category_title) . '/' . \Illuminate\Support\Str::slug($data['blog_details']->title)) }}"><i class="fa-brands fa-facebook me-2"></i> {{ __('general.facebook') }}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a type="button" class="dropdown-item" data-sharer="whatsapp" data-title="{{ $data['blog_details']->title }}" data-url="{{ url('/blog/' . \Illuminate\Support\Str::slug($data['blog_details']->category_title) . '/' . \Illuminate\Support\Str::slug($data['blog_details']->title)) }}"><i class="fa-brands fa-whatsapp me-2"></i> {{ __('general.whatsapp') }}</a></li>
                            </ul>
                        </div>
                    </div>

                    {!! $data['blog_details']->description !!}
                </div>

                @if(count($data['related_blogs']) > 0)
                    <h4 class="fw-bold mt-5 mb-4">{{ __('general.related_blogs') }}</h4>
                    <div class="row">
                        @foreach($data['related_blogs'] as $blog)
                        <div class="col-lg-4 col-md-6">
                            <a href="{{ url('/blog/' . \Illuminate\Support\Str::slug($blog->category_title) . '/' . \Illuminate\Support\Str::slug($blog->custom_url)) }}" class="blog_card">
                                <div class="blog_card_img">
                                    <img src="{{ asset('storage/blogs/'.$blog->image) }}" alt="">
                                    <div class="blog_date">{{ date('d M, Y', strtotime($blog->date)) }}</div>
                                </div>
                                <div class="blog_card_body">
                                    <h6>{{ $blog->category_title }}</h6>
                                    <h4>{{ $blog->title }}</h4>
                                    <p>{{ $blog->short_description }}</p>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection