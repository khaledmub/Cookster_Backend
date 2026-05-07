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

        <div class="profile-foreground position-relative mx-n4 mt-n4">
            <div class="profile-wid-bg">
                <img src="{{ asset('assets/admin/images/ba-bg.jpg') }}" alt="" class="profile-wid-img" />
            </div>
        </div>
        <div class="pt-4 mb-4 mb-lg-3 pb-lg-4">
            <div class="row g-4">
                <div class="col-auto">
                    <div class="avatar-lg">
                        @php
                        if($data['general_data']->image!=''){
                            $image = asset('storage/front_users/'.$data['general_data']->image);
                        }
                        else{
                            $image = asset('assets/admin/images/users/ba-dummy.png');
                        }
                        @endphp
                        <img src="{{$image}}" alt="user-img"
                            class="img-thumbnail rounded-circle" style="max-height: 100%;" />
                    </div>
                </div>
                <!--end col-->
                <div class="col">
                    <div class="p-2">
                        <h3 class="text-white mb-1">{{$data['general_data']->name}}</h3>
                        <p class="text-white-75">{{$data['module_title_singular']}}</p>
                    </div>
                </div>

            </div>
            <!--end row-->
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div>
                    <div class="d-flex">
                        <!-- Nav tabs -->
                        <ul class="nav nav-pills animation-nav profile-nav gap-2 gap-lg-3 flex-grow-1"
                            role="tablist">
                            <li class="nav-item">
                                <a class="nav-link fs-14 active" data-bs-toggle="tab" href="#info"
                                    role="tab">
                                    <i class="ri-airplay-fill d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">Info</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fs-14" data-bs-toggle="tab" href="#subscription"
                                    role="tab">
                                    <i class="ri-airplay-fill d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">Subscription History</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fs-14" data-bs-toggle="tab" href="#qrcode_scan"
                                    role="tab">
                                    <i class="ri-airplay-fill d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">QR Code Scan History</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link fs-14" data-bs-toggle="tab" href="#one_time_discount_history"
                                    role="tab">
                                    <i class="ri-airplay-fill d-inline-block d-md-none"></i> <span
                                        class="d-none d-md-inline-block">One-Time QR Reward History</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- Tab panes -->
                    <div class="tab-content pt-4 text-muted">
                        <div class="tab-pane active" id="info" role="tabpanel">
                            <div class="row">
                                <div class="col-xxl-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0">
                                                    <tbody>
                                                        <tr>
                                                            <th class="" scope="row">Email</th>
                                                            <td class="text-muted">{{$data['general_data']->email}}</td>
                                                            <th class="" scope="row">Phone</th>
                                                            <td class="text-muted">{{$data['general_data']->phone}}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="" scope="row">Business Type</th>
                                                            <td class="text-muted">{{$data['additional_data']->business_type_name}}</td>
                                                            <th class="" scope="row">Country</th>
                                                            <td class="text-muted">{{$data['additional_data']->country_name}}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="" scope="row">City</th>
                                                            <td class="text-muted">{{$data['additional_data']->city_name}}</td>
                                                            <th class="" scope="row">Contact Phone</th>
                                                            <td class="text-muted">{{$data['additional_data']->contact_phone}}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="" scope="row">Contact Email</th>
                                                            <td class="text-muted">{{$data['additional_data']->contact_email}}</td>
                                                            <th class="" scope="row">Website</th>
                                                            <td class="text-muted">{{$data['additional_data']->website}}</td>
                                                        </tr>
                                                        <tr>
                                                            <th class="" scope="row">Location</th>
                                                            <td class="text-muted">{{$data['additional_data']->location}}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- end card body -->
                                    </div><!-- end card -->
                                </div>
                            </div>
                            <!--end row-->
                        </div>
                        <div class="tab-pane" id="subscription" role="tabpanel">
                            <div class="row">
                                <div class="col-xxl-12">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Package</th>
                                                            <th>Start Date</th>
                                                            <th>End Date</th>
                                                            <th>Duration</th>
                                                            <th>Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($data['subscription_history'] as $history)
                                                        <tr>
                                                            <td>{{$history->package_title}}</td>
                                                            <td>{{date(env('DATE_FORMAT'), strtotime($history->start_date))}}</td>
                                                            <td>{{date(env('DATE_FORMAT'), strtotime($history->end_date))}}</td>
                                                            <td>{{$history->duration}} Month/s</td>
                                                            <td>{{\App\Helpers\AppHelper::currency_formatter($history->amount)}}</td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- end card body -->
                                    </div><!-- end card -->
                                </div>
                            </div>
                            <!--end row-->
                        </div>
                        <div class="tab-pane" id="qrcode_scan" role="tabpanel">
                            <div class="row">
                                <div class="col-xxl-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="mb-0">Total Outstanding Balance: <b>{{ \App\Helpers\AppHelper::currency_formatter($data['general_data']->total_outstanding_balance) }}</b></h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Customer</th>
                                                            <th>Points Converted</th>
                                                            <th>Discount Given</th>
                                                            <th>Date</th>
                                                            <th>Is Cleared?</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($data['qrcode_scan_history'] as $history)
                                                        <tr>
                                                            <td>{{ $history->customer_name }}</td>
                                                            <td>{{ $history->points }}</td>
                                                            <td>{{ \App\Helpers\AppHelper::currency_formatter($history->amount) }}</td>
                                                            <td>{{ date(env('DATE_FORMAT'), strtotime($history->created_at)) }}</td>
                                                            <td>
                                                                @if($history->is_cleared == 1)
                                                                    <span class="badge bg-success">Yes</span>
                                                                @else
                                                                    <span class="badge bg-danger">No</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- end card body -->
                                    </div><!-- end card -->
                                </div>
                            </div>
                            <!--end row-->
                        </div>
                        <div class="tab-pane" id="one_time_discount_history" role="tabpanel">
                            <div class="row">
                                <div class="col-xxl-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="mb-0">Total Outstanding Balance: <b>{{ \App\Helpers\AppHelper::currency_formatter($data['additional_data']->one_time_discount_outstanding_balance) }}</b></h4>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead>
                                                        <tr>
                                                            <th>Customer</th>
                                                            <th>Discount Given</th>
                                                            <th>Date</th>
                                                            <th>Is Cleared?</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($data['one_time_discount_history'] as $history)
                                                        <tr>
                                                            <td>{{ $history->customer_name }}</td>
                                                            <td>{{ \App\Helpers\AppHelper::currency_formatter($history->amount) }}</td>
                                                            <td>{{ date(env('DATE_FORMAT'), strtotime($history->created_at)) }}</td>
                                                            <td>
                                                                @if($history->is_cleared == 1)
                                                                    <span class="badge bg-success">Yes</span>
                                                                @else
                                                                    <span class="badge bg-danger">No</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div><!-- end card body -->
                                    </div><!-- end card -->
                                </div>
                            </div>
                            <!--end row-->
                        </div>
                        <!--end tab-pane-->
                    </div>
                    <!--end tab-content-->
                </div>
            </div>
            <!--end col-->
        </div>

    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection
