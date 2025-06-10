@extends('layouts.app')

@section('content')
<div class="page-content">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col-12">
                            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 class="mb-sm-0">Dashboard</h4>

                                <div class="page-title-right">
                                    <ol class="breadcrumb m-0">
                                        <li class="breadcrumb-item active">Dashboard</li>
                                    </ol>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                            <div class="h-100">
                                <div class="row">
                                    <div class="col-xl-3 col-md-6">
                                        <!-- card -->
                                        <div class="card card-animate">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <p
                                                            class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                                            Personal Accounts</p>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-end justify-content-between mt-4">
                                                    <div>
                                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span
                                                                class="counter-value">{{$data['personal_accounts_count']}}</span>
                                                        </h4>
                                                        <a href="{{ route('personal_accounts.index') }}" class="text-decoration-underline">View All</a>
                                                    </div>
                                                    <div class="avatar-sm flex-shrink-0">
                                                        <span class="avatar-title bg-soft-success rounded fs-3">
                                                            <i class="fa-solid fa-users text-success"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div><!-- end card body -->
                                        </div><!-- end card -->
                                    </div><!-- end col -->

                                    <div class="col-xl-3 col-md-6">
                                        <!-- card -->
                                        <div class="card card-animate">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <p
                                                            class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                                            Business Accounts</p>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-end justify-content-between mt-4">
                                                    <div>
                                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span
                                                                class="counter-value">{{$data['business_accounts_count']}}</span>
                                                        </h4>
                                                        <a href="{{ route('business_accounts.index') }}" class="text-decoration-underline">View All</a>
                                                    </div>
                                                    <div class="avatar-sm flex-shrink-0">
                                                        <span class="avatar-title bg-soft-info rounded fs-3">
                                                            <i class="fa-solid fa-briefcase text-info"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- end card -->
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <!-- card -->
                                        <div class="card card-animate">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <p
                                                            class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                                            Sponsored Accounts</p>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-end justify-content-between mt-4">
                                                    <div>
                                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span
                                                                class="counter-value">{{$data['sponsored_accounts_count']}}</span>
                                                        </h4>
                                                        <a href="{{ route('sponsored_accounts.index') }}" class="text-decoration-underline">View All</a>
                                                    </div>
                                                    <div class="avatar-sm flex-shrink-0">
                                                        <span class="avatar-title bg-soft-warning rounded fs-3">
                                                            <i class="fa-solid fa-bullhorn text-warning"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- end card -->
                                    </div>
                                    <div class="col-xl-3 col-md-6">
                                        <!-- card -->
                                        <div class="card card-animate">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <p
                                                            class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                                            Videos</p>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-end justify-content-between mt-4">
                                                    <div>
                                                        <h4 class="fs-22 fw-semibold ff-secondary mb-4"><span
                                                                class="counter-value">{{$data['videos_count']}}</span>
                                                        </h4>
                                                        <a href="{{ route('videos.index') }}" class="text-decoration-underline">View All</a>
                                                    </div>
                                                    <div class="avatar-sm flex-shrink-0">
                                                        <span class="avatar-title bg-soft-primary rounded fs-3">
                                                            <i class="fa-solid fa-camcorder text-primary"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div><!-- end card -->
                                    </div>
                                </div> <!-- end row-->
                                <div class="row gallery-wrapper custom_gallery_wrapper mt-3">
                                    <h6 class="mb-3 text-uppercase fw-semibold">Recent Videos</h6>
                                    @foreach($data['latest_videos'] as $video)
                                    <div class="element-item col-xxl-3 col-xl-4 col-sm-6">
                                        <div class="gallery-box card">
                                            <div class="gallery-container">
                                                <a class="showVideo" data-title="{{$video->title}}" data-video="{{env('AWS_CLOUD_FRONT_PATH').'videos/'.$video->video}}" href="javascript:void(0)">
                                                    <img class="gallery-img img-fluid mx-auto" src="{{env('AWS_CLOUD_FRONT_PATH').'videos/'.$video->image}}" alt="" />
                                                    <div class="gallery-overlay">
                                                        <h5 class="overlay-caption">{{$video->title}}</h5>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="box-content">
                                                <div class="d-flex align-items-center mt-1">
                                                    <div class="flex-grow-1 text-muted">by <a href="" class="text-body text-truncate">{{$video->user_name}}</a></div>
                                                    <div class="flex-shrink-0">
                                                        <div class="d-flex gap-3">
                                                            <button type="button" class="btn btn-sm fs-12 btn-link text-body text-decoration-none px-0">
                                                                <i class="ri-thumb-up-fill text-muted align-bottom me-1"></i> {{$video->total_likes}}
                                                            </button>
                                                            <button type="button" class="btn btn-sm fs-12 btn-link text-body text-decoration-none px-0">
                                                                <i class="ri-question-answer-fill text-muted align-bottom me-1"></i> {{$video->total_comments}}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <!--
                        <div class="col-auto layout-rightside-col">
                            <div class="overlay"></div>
                            <div class="layout-rightside">
                                <div class="card h-100 rounded-0 layout_rightside_card">
                                    <div class="card-body p-0">
                                        <div class="p-3">
                                            <h6 class="text-muted mb-0 text-uppercase fw-semibold">Recent Reviews</h6>
                                        </div>
                                        <div data-simplebar style="max-height: 480px;" class="p-3 pt-0">
                                            <div class="reviewsListWrapper">
                                                <div class="">
                                                    <div class="">
                                                        <div class="card border border-dashed shadow-none">
                                                            <div class="card-body">
                                                                <div class="d-flex">
                                                                    <div class="flex-shrink-0">
                                                                        <img src="{{asset('assets/admin/images/users/pa-dummy.png')}}"
                                                                            alt="" class="avatar-sm rounded">
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <div>
                                                                            <p
                                                                                class="text-muted mb-1 fst-italic text-truncate-two-lines">
                                                                                " Amazing template, very easy to
                                                                                understand and manipulate. "</p>
                                                                            <div
                                                                                class="fs-11 align-middle text-warning">
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-half-fill"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-end mb-0 text-muted">
                                                                            - by <cite title="Source Title">Henry
                                                                                Baird</cite>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="">
                                                        <div class="card border border-dashed shadow-none">
                                                            <div class="card-body">
                                                                <div class="d-flex">
                                                                    <div class="flex-shrink-0">
                                                                        <img src="{{asset('assets/admin/images/users/pa-dummy.png')}}"
                                                                            alt="" class="avatar-sm rounded">
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <div>
                                                                            <p
                                                                                class="text-muted mb-1 fst-italic text-truncate-two-lines">
                                                                                " Amazing template, very easy to
                                                                                understand and manipulate. "</p>
                                                                            <div
                                                                                class="fs-11 align-middle text-warning">
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-half-fill"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-end mb-0 text-muted">
                                                                            - by <cite title="Source Title">Henry
                                                                                Baird</cite>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="">
                                                        <div class="card border border-dashed shadow-none">
                                                            <div class="card-body">
                                                                <div class="d-flex">
                                                                    <div class="flex-shrink-0">
                                                                        <img src="{{asset('assets/admin/images/users/pa-dummy.png')}}"
                                                                            alt="" class="avatar-sm rounded">
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <div>
                                                                            <p
                                                                                class="text-muted mb-1 fst-italic text-truncate-two-lines">
                                                                                " Amazing template, very easy to
                                                                                understand and manipulate. "</p>
                                                                            <div
                                                                                class="fs-11 align-middle text-warning">
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-half-fill"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-end mb-0 text-muted">
                                                                            - by <cite title="Source Title">Henry
                                                                                Baird</cite>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="">
                                                        <div class="card border border-dashed shadow-none">
                                                            <div class="card-body">
                                                                <div class="d-flex">
                                                                    <div class="flex-shrink-0">
                                                                        <img src="{{asset('assets/admin/images/users/pa-dummy.png')}}"
                                                                            alt="" class="avatar-sm rounded">
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <div>
                                                                            <p
                                                                                class="text-muted mb-1 fst-italic text-truncate-two-lines">
                                                                                " Amazing template, very easy to
                                                                                understand and manipulate. "</p>
                                                                            <div
                                                                                class="fs-11 align-middle text-warning">
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-half-fill"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-end mb-0 text-muted">
                                                                            - by <cite title="Source Title">Henry
                                                                                Baird</cite>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="">
                                                        <div class="card border border-dashed shadow-none">
                                                            <div class="card-body">
                                                                <div class="d-flex">
                                                                    <div class="flex-shrink-0">
                                                                        <img src="{{asset('assets/admin/images/users/pa-dummy.png')}}"
                                                                            alt="" class="avatar-sm rounded">
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <div>
                                                                            <p
                                                                                class="text-muted mb-1 fst-italic text-truncate-two-lines">
                                                                                " Amazing template, very easy to
                                                                                understand and manipulate. "</p>
                                                                            <div
                                                                                class="fs-11 align-middle text-warning">
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-fill"></i>
                                                                                <i class="ri-star-half-fill"></i>
                                                                            </div>
                                                                        </div>
                                                                        <div class="text-end mb-0 text-muted">
                                                                            - by <cite title="Source Title">Henry
                                                                                Baird</cite>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="p-3">
                                            <div class="card border border-dashed shadow-none">
                                                <div class="card-body">
                                                    <h6 class="text-muted mb-3 text-uppercase fw-semibold">Customer Reviews</h6>
                                                    <div class="bg-light px-3 py-2 rounded-2 mb-2">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-grow-1">
                                                                <div class="fs-16 align-middle text-warning">
                                                                    <i class="ri-star-fill"></i>
                                                                    <i class="ri-star-fill"></i>
                                                                    <i class="ri-star-fill"></i>
                                                                    <i class="ri-star-fill"></i>
                                                                    <i class="ri-star-half-fill"></i>
                                                                </div>
                                                            </div>
                                                            <div class="flex-shrink-0">
                                                                <h6 class="mb-0">4.5 out of 5</h6>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="text-center">
                                                        <div class="text-muted">Total <span class="fw-medium">5.50k</span>
                                                            reviews</div>
                                                    </div>

                                                    <div class="mt-3">
                                                        <div class="row align-items-center g-2">
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0">5 star</h6>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="p-1">
                                                                    <div class="progress animated-progress progress-sm">
                                                                        <div class="progress-bar bg-success" role="progressbar"
                                                                            style="width: 50.16%" aria-valuenow="50.16"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0 text-muted">2758</h6>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row align-items-center g-2">
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0">4 star</h6>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="p-1">
                                                                    <div class="progress animated-progress progress-sm">
                                                                        <div class="progress-bar bg-success" role="progressbar"
                                                                            style="width: 29.32%" aria-valuenow="29.32"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0 text-muted">1063</h6>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row align-items-center g-2">
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0">3 star</h6>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="p-1">
                                                                    <div class="progress animated-progress progress-sm">
                                                                        <div class="progress-bar bg-warning" role="progressbar"
                                                                            style="width: 18.12%" aria-valuenow="18.12"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0 text-muted">997</h6>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row align-items-center g-2">
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0">2 star</h6>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="p-1">
                                                                    <div class="progress animated-progress progress-sm">
                                                                        <div class="progress-bar bg-success" role="progressbar"
                                                                            style="width: 4.98%" aria-valuenow="4.98"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0 text-muted">227</h6>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="row align-items-center g-2">
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0">1 star</h6>
                                                                </div>
                                                            </div>
                                                            <div class="col">
                                                                <div class="p-1">
                                                                    <div class="progress animated-progress progress-sm">
                                                                        <div class="progress-bar bg-danger" role="progressbar"
                                                                            style="width: 7.42%" aria-valuenow="7.42"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto">
                                                                <div class="p-1">
                                                                    <h6 class="mb-0 text-muted">408</h6>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        -->
                    </div>
                </div>
                <!-- container-fluid -->
            </div>
@endsection