@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{$data['module_title_singular']}} Details</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ url('admin/'.$data['url_path']) }}">{{$data['module_title_plural']}}</a></li>
                            <li class="breadcrumb-item active">Details</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
        <div class="row">
            @if($data['video_details'])
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <h4 class="card-title mb-0 flex-grow-1">Video Details</h4>
                        @php
                        if($data['video_details']->status==1){
                            $checked = "checked";
                        }
                        else{
                            $checked = "";
                        }
                        @endphp
                        <div class="form-check form-switch form-switch-success" dir="ltr"><input class="form-check-input videoStatusChanger" data-id="{{$data['video_details']->id}}" data-reports_counter="{{$data['reports_counter']}}" type="checkbox" role="switch" id="flexSwitchCheckChecked" {{$checked}}></div>
                    </div><!-- end card header -->

                    <div class="card-body">

                        <div class="px-3 mx-n3 mb-2" data-simplebar style="height: calc(100vh - 320px);">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="video_thumbnail">
                                        <img src="{{ \App\Helpers\AppHelper::mediaPublicBaseUrl().'videos/'.$data['video_details']->image }}" alt="">
                                        <a data-title="{{$data['video_details']->title}}" data-video="{{ \App\Helpers\AppHelper::mediaPublicBaseUrl().'videos/'.$data['video_details']->video }}" href="javascript:void(0)" class="video_icon showVideo"><i class="ri-play-fill"></i></a>
                                    </div>

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="video_title_info mt-4">
                                                <div class="custom_vid_catagory">
                                                <p>Category: </p> <div class="catagory_badge">{{$data['video_details']->video_type_name}}</div>
                                                </div>
                                                <h3>{{$data['video_details']->title}}</h3>
                                                <p>{{$data['video_details']->description}}</p>
                                            </div>
                                        </div>
                                        <div class="col-sm-6">
                                            <div class="like_share_counters">
                                                <h6>Feedback</h6>
                                                <div class="likes_share_body">
                                                    <div class="counter_custom_box">
                                                        <h4>{{$data['views']}}</h4>
                                                        <small>Views</small>
                                                    </div>
                                                    <div class="counter_custom_box">
                                                        <h4>{{$data['likes']}}</h4>
                                                        <small>Likes</small>
                                                    </div>
                                                    <div class="counter_custom_box">
                                                        <h4>{{$data['order_clicks']}}</h4>
                                                        <small>Order Clicks</small>
                                                    </div>
                                                    <!-- <div class="counter_custom_box">
                                                        <h4>100</h4>
                                                        <small>Comments</small>
                                                    </div> -->
                                                    <!-- <div class="counter_custom_box">
                                                        <h4>10</h4>
                                                        <small>Shares</small>
                                                    </div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @php
                                    $tags = array();
                                    $menu = array();
                                    if($data['video_details']->tags){
                                        $tags = explode(',', $data['video_details']->tags);
                                    }
                                    if($data['video_details']->menu){
                                        $menu = explode(',', $data['video_details']->menu);
                                    }
                                    @endphp
                                    <div class="row">
                                        <div class="col-sm-6">
                                            @if(sizeof($tags) > 0)
                                            <div class="video_menu video_tags">
                                                <div class="vm_title"><h6>Tags</h6></div>
                                                <div class="video_menu_badges">
                                                    @foreach($tags as $tag)
                                                    <div class="menu_badge">{{$tag}}</div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                        <div class="col-sm-6">
                                            @if(sizeof($menu) > 0)
                                            <div class="video_menu">
                                                <div class="vm_title"><h6>Menu</h6></div>
                                                <div class="video_menu_badges">
                                                    @foreach($menu as $s_menu)
                                                    <div class="menu_badge">{{$s_menu}}</div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>    
                                    <hr>
                                    <div class="publish_type mt-3 d-flex gap-4 align-items-center">
                                        <h6 class="mb-0">Publish Type</h6>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            @if($data['video_details']->publish_type == 1)
                                            <h5><span class="badge badge-soft-primary badge-border">Followers</span></h5>
                                            @elseif($data['video_details']->publish_type == 2)
                                            <h5><span class="badge badge-soft-secondary badge-border">Public</span></h5>
                                            @elseif($data['video_details']->publish_type == 3)
                                            <h5><span class="badge badge-soft-light badge-border text-dark">Private </span></h5>
                                            @endif
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="allow_options d-flex flex-wrap gap-5 mt-3">
                                        <div class="allow_option_a d-flex gap-3 align-items-center">
                                            <p class="mb-0">Allow Comments</p>
                                                @if($data['video_details']->allow_comments == 1)
                                                <h2 class="text-success mb-0"><i class="ri-checkbox-circle-fill"></i></h2>
                                                @else
                                                <h2 class="text-danger mb-0"><i class="ri-close-circle-fill"></i></h2>
                                                @endif
                                        </div>
                                        <div class="allow_option_a d-flex gap-3 align-items-center">
                                            <p class="mb-0">Allow Orders</p>
                                                @if($data['video_details']->take_order == 1)
                                                <h2 class="text-success mb-0"><i class="ri-checkbox-circle-fill"></i></h2>
                                                @else
                                                <h2 class="text-danger mb-0"><i class="ri-close-circle-fill"></i></h2>
                                                @endif
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="publish_type mt-3 d-flex gap-4 align-items-center">
                                        <h6 class="mb-0">Country:</h6>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <h5>{{ $data['video_details']->country_name }}</h5>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="publish_type mt-3 d-flex gap-4 align-items-center">
                                        <h6 class="mb-0">City:</h6>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <h5>{{ $data['video_details']->city_name }}</h5>
                                        </div>
                                    </div>
                                    <!-- <hr>
                                    <div class="allow_options d-flex flex-wrap gap-5 mt-3">
                                        <div class="allow_option_a d-flex gap-3 align-items-center">
                                            <p class="mb-0"><i class="fa-regular fa-location-dot text-muted" style="margin-right: 5px;"></i> <span>{{$data['video_details']->location}}</span></p>
                                        </div>
                                    </div> -->
                                </div>
                            </div>
                        </div>


                        
                    </div><!-- end card-body -->
                </div><!-- end card -->

            </div>
            <!-- end col -->

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs nav-tabs-custom nav-success nav-justified mb-3" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#comments" role="tab">
                                    <i class="fa-light fa-comments"></i> Comments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#reports" role="tab">
                                    <i class="fa-light fa-flag-checkered"></i> Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#sponsored" role="tab">
                                    <i class="fa-regular fa-money-bill-trend-up"></i> Sponsored History
                                </a>
                            </li>
                        </ul>

                        <!-- Tab panes -->
                        <div class="tab-content text-muted">
                            <div class="tab-pane active" id="comments" role="tabpanel">
                                <div data-simplebar style="height: calc(100vh - 320px);" class="px-3 mx-n3 mb-2">
                                    @foreach($data['comments'] as $comment)
                                    @php
                                    $user_id = $comment['fields']['userId']['stringValue'];
                                    $user_details = \App\Helpers\AppHelper::get_user_details($user_id);
                                    if(isset($user_details->image) && $user_details->image!=''){
                                        $image = asset('storage/front_users/'.$user_details->image);
                                    }
                                    else{
                                        $image = asset('assets/admin/images/users/pa-dummy.png');
                                    }

                                    $repliesUrl = "https://firestore.googleapis.com/v1/".$comment['name']."/replies?key=".env('FIREBASE_KEY');
                                    $response = file_get_contents($repliesUrl);
                                    $replies = json_decode($response, true);
                                    if(isset($replies['documents'])){
                                        $replies = $replies['documents'];
                                    }
                                    else{
                                        $replies=array();
                                    }
                                    @endphp
                                    <div class="d-flex mb-4">
                                        <div class="flex-shrink-0">
                                            <img src="{{$image}}" alt="" class="avatar-xs rounded-circle" />
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="fs-13">@if(!empty($user_details->name)){{$user_details->name}}@endif<small class="text-muted ms-2">{{date('d M Y', strtotime($comment['fields']['timestamp']['timestampValue']))}} - {{date('h:i A', strtotime($comment['fields']['timestamp']['timestampValue']))}}</small></h5>
                                            <p class="text-muted">{{$comment['fields']['text']['stringValue']}}</p>
                                            <!-- <a href="javascript: void(0);" class="badge text-muted bg-light"><i class="mdi mdi-reply"></i> Reply</a> -->
                                            @foreach($replies as $reply)
                                            @php
                                            $user_id = $reply['fields']['userId']['stringValue'];
                                            $user_details = \App\Helpers\AppHelper::get_user_details($user_id);
                                            if(isset($user_details->image) && $user_details->image!=''){
                                                $image = asset('storage/front_users/'.$user_details->image);
                                            }
                                            else{
                                                $image = asset('assets/admin/images/users/pa-dummy.png');
                                            }
                                            @endphp
                                            <div class="d-flex mt-4">
                                                <div class="flex-shrink-0">
                                                    <img src="{{$image}}" alt="" class="avatar-xs rounded-circle" />
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h5 class="fs-13">@if(!empty($user_details->name)){{$user_details->name}}@endif<small class="text-muted ms-2">{{date('d M Y', strtotime($reply['fields']['timestamp']['timestampValue']))}} - {{date('h:i A', strtotime($reply['fields']['timestamp']['timestampValue']))}}</small></h5>
                                                    <p class="text-muted">{{$reply['fields']['text']['stringValue']}}</p>
                                                    <!-- <a href="javascript: void(0);" class="badge text-muted bg-light"><i class="mdi mdi-reply"></i> Reply</a> -->
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="tab-pane" id="reports" role="tabpanel">
                                @foreach($data['reports'] as $report)
                                @php
                                if(isset($report->user_image) && $report->user_image!=''){
                                    $image = asset('storage/front_users/'.$report->user_image);
                                }
                                else{
                                    $image = asset('assets/admin/images/users/pa-dummy.png');
                                }
                                @endphp
                                <div class="d-flex mb-3">
                                    <div class="flex-shrink-0">
                                        <img src="{{$image}}" alt="" class="avatar-md rounded">
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>{{$report->category_name}} <small class="text-muted ms-2">{{date('d M Y', strtotime($report->created_at))}} - {{date('h:i A', strtotime($report->created_at))}}</small></h6>
                                        <p>{{$report->comments}}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="tab-pane" id="sponsored" role="tabpanel">
                                <div class="row">
                                    <div class="col-xxl-12">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>Cities</th>
                                                                <th>Sponsor Type</th>
                                                                <th>Days</th>
                                                                <th>Start Date</th>
                                                                <th>End Date</th>
                                                                <th>Per Day Price</th>
                                                                <th>Discount</th>
                                                                <th>Total Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($data['sponsored_history'] as $history)
                                                            <tr>
                                                                <td>{{\App\Helpers\AppHelper::get_cities_names($history->cities)}}</td>
                                                                <td>{!! \App\Helpers\AppHelper::get_sponsor_type_label($history->sponsor_type) !!}</td>
                                                                <td>{{$history->days}} Day/s</td>
                                                                <td>{{date(env('DATE_FORMAT'), strtotime($history->start_date))}}</td>
                                                                <td>{{date(env('DATE_FORMAT'), strtotime($history->end_date))}}</td>
                                                                <td>{{\App\Helpers\AppHelper::currency_formatter($history->per_day_price)}}</td>
                                                                <td>{{\App\Helpers\AppHelper::currency_formatter($history->discount_amount)}}</td>
                                                                <td>{{\App\Helpers\AppHelper::currency_formatter($history->total_amount)}}</td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div><!-- end card body -->
                                        </div><!-- end card -->
                                    </div>
                                </div>
                            </div>
                        </div>


                        
                                
                    </div>
                    <!-- end card body -->
                </div>
            </div>

            @else
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="mb-0">No video found.</h4>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection
