@extends('frontend.layouts.app')

@if($data['category_details'])
    @section('meta_title', $data['category_details']->meta_title)
    @section('meta_description', $data['category_details']->meta_description)
    @section('meta_keywords', $data['category_details']->meta_keywords)
@else
    @section('meta_title', $data['page']->meta_title)
    @section('meta_description', $data['page']->meta_description)
    @section('meta_keywords', $data['page']->meta_keywords)
@endif

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
                              <li class="breadcrumb-item active" aria-current="page">{{ __('general.blog') }}</li>
                            </ol>
                          </nav>
                          
                        <h1 class="text-center">{{ __('general.blog') }}</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="about_us">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <h4 class="fw-bold mb-4">
                        @if($data['category_details'])
                        {{ $data['category_details']->title }}
                        @else
                        {{ __('general.all_blogs') }}
                        @endif
                    </h4>

                    @if(count($data['blogs']) > 0)
                    <div class="row">
                        @foreach($data['blogs'] as $blog)
                        <div class="col-md-6">
                            <a href="{{ url('/blog/' . \Illuminate\Support\Str::slug($blog->category_title) . '/' . \Illuminate\Support\Str::slug($blog->title)) }}" class="blog_card">
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

                    {{ $data['blogs']->links('vendor.pagination.bootstrap-5') }}
                    
                    @else
                    <h4>{{ __('general.no_blogs_found') }}</h4>
                    @endif
                </div>
                <div class="col-lg-4">
                    <div class="blog_category_card">
                        <h4>{{ __('general.filter_by_category') }}</h4>
                        <hr>
                        <ul class="blog_category_list">
                            <li><a href="{{ url('/blog') }}" class="blog_category_link {{ $data['category_details']? '': 'active' }}">- {{ __('general.all_blogs') }}</a></li>
                            @foreach($data['blogcategories'] as $category)
                            <li><a href="{{ url('/blog/' . \Illuminate\Support\Str::slug($category->title)) }}" class="blog_category_link {{ $data['category_details'] && $data['category_details']->id == $category->id? 'active': '' }}">- {{ $category->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection