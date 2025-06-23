@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit {{$data['module_title_singular']}}</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">Edit {{$data['module_title_singular']}}</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
        <!-- end page title -->

        
        <div class="row">
            <div class="col">
                <div class="h-100">
                    <!--end row-->
                    
                </div> <!-- end .h-100-->
            </div> <!-- end col -->
        </div>
        @if (count($errors) > 0)
            <div class="alert alert-danger">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
                </ul>
            </div>
        @endif
        @if ($message = Session::get('success'))
            <div class="alert alert-success">
                <p>{{ $message }}</p>
            </div>
        @endif
        <div class="row">
            <form method="POST" enctype="multipart/form-data" action="{{ route($data['url_path'].'.update', $setting->id) }}">
            @csrf
            @method('PUT')
                <div class="col-md-12">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header" style="border-radius: 1.02rem 1.02rem 0 0;">
                            <h5 class="mb-0">System Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Email</label>
                                        <input type="text" name="email" placeholder="" class="form-control" value="{{ $setting->email }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="phone" placeholder="" class="form-control" value="{{ $setting->phone }}">
                                    </div>
                                </div>
                                <div class="col-xs-6 col-sm-6 col-md-6 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Address</label>
                                        <input type="text" name="address" placeholder="" class="form-control" value="{{ $setting->address }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Facebook</label>
                                        <input type="text" name="facebook" placeholder="" class="form-control" value="{{ $setting->facebook }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Twitter</label>
                                        <input type="text" name="twitter" placeholder="" class="form-control" value="{{ $setting->twitter }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Instagram</label>
                                        <input type="text" name="instagram" placeholder="" class="form-control" value="{{ $setting->instagram }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Linkedin</label>
                                        <input type="text" name="linkedin" placeholder="" class="form-control" value="{{ $setting->linkedin }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">TikTok</label>
                                        <input type="text" name="tiktok" placeholder="" class="form-control" value="{{ $setting->tiktok }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Snapchat</label>
                                        <input type="text" name="snapchat" placeholder="" class="form-control" value="{{ $setting->snapchat }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Play Store Link</label>
                                        <input type="text" name="play_store_link" placeholder="" class="form-control" value="{{ $setting->play_store_link }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">App Store Link</label>
                                        <input type="text" name="app_store_link" placeholder="" class="form-control" value="{{ $setting->app_store_link }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Currency Symbol</label>
                                        <input type="text" name="currency_symbol" placeholder="" class="form-control" value="{{ $setting->currency_symbol }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header" style="border-radius: 1.02rem 1.02rem 0 0;">
                            <h5 class="mb-0">Sponsored Videos Pricing</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Basic (Per Day)</label>
                                        <input type="number" step="0.01" name="basic_sponsored_video_price" placeholder="" class="form-control" value="{{ $setting->basic_sponsored_video_price }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Premium (Per Day)</label>
                                        <input type="number" step="0.01" name="premium_sponsored_video_price" placeholder="" class="form-control" value="{{ $setting->premium_sponsored_video_price }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Discount % (For Business Accounts)</label>
                                        <input type="number" step="0.01" name="sponsor_video_discount" placeholder="" class="form-control" value="{{ $setting->sponsor_video_discount }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header" style="border-radius: 1.02rem 1.02rem 0 0;">
                            <h5 class="mb-0">Mobile App Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Allow General Videos</label><br>
                                        <input type="checkbox" name="allow_general_videos" placeholder="" class="" value="1" {{ $setting->allow_general_videos ? 'checked' : '' }}>
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Allow Following Videos</label><br>
                                        <input type="checkbox" name="allow_following_videos" placeholder="" class="" value="1" {{ $setting->allow_following_videos ? 'checked' : '' }}>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-12 col-md-12 text-end mb-3">
                    <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                </div>
            </form>
        </div>
        
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection