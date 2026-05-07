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
                            <li class="breadcrumb-item"><a href="{{ url('admin/'.$data['url_path']) }}">{{$data['module_title_plural']}}</a></li>
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
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route($data['url_path'].'.update', $m_data->id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header align-items-center d-flex">
                            <h6 class="card-title mb-0 flex-grow-1">General Content</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" name="title" placeholder="" class="form-control" value="{{ $m_data->title }}">
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Country <span class="text-danger">*</span></label>
                                        <select name="country" class="form-select select2">
                                            <option value="">Please select option</option>
                                            @foreach($data['countries'] as $country)
                                            <option {{ $m_data->country == $country->id ? 'selected' : '' }} value="{{$country->id}}">{{$country->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Cities <span class="text-danger">*</span></label>
                                        <select multiple name="cities[]" class="form-select select2">
                                            <option value="">Please select option</option>
                                            @foreach($data['cities'] as $city)
                                            <option value="{{$city->id}}" {{ in_array($city->id, $m_data->cities ?? []) ? 'selected' : '' }}>{{$city->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Status <span class="text-danger">*</span></label>
                                        <select name="status" class="form-select">
                                            <option {{ $m_data->status == 1 ? 'selected' : '' }} value="1">Active</option>
                                            <option {{ $m_data->status == 0 ? 'selected' : '' }} value="0">Inctive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-12 col-sm-12 col-md-12 mt-2">
                        <button type="submit" class="btn btn-primary waves-effect waves-light float-end"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection