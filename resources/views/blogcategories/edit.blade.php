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
                <div class="col-md-9">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header align-items-center d-flex">
                            <h6 class="card-title mb-0 flex-grow-1">Tranlsation Content</h6>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs nav-border-top nav-border-top-primary mb-3 languages_tab" role="tablist">
                                @foreach($data['site_languages'] as $s_language)
                                <li class="nav-item">
                                    <a class="nav-link {{ $s_language->is_default == 1 ? 'active' : '' }}" data-bs-toggle="tab" href="#nav-language-{{$s_language->id}}" role="tab" aria-selected="{{ $s_language->is_default == 1 ? 'true' : 'false' }}">
                                        <img style="margin-top: -1px; margin-right: 5px;" width="18px" src="{{ asset('storage/country_icons/'.$s_language->image) }}"> <span style="vertical-align: middle;">{{$s_language->name}}</span>
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                            <div class="tab-content">
                                @foreach($data['site_languages'] as $s_language)
                                <div class="tab-pane {{ $s_language->is_default == 1 ? 'active' : '' }}" id="nav-language-{{$s_language->id}}" role="tabpanel">
                                    <div class="row mt-4">
                                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                            <div class="form-group">
                                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                                <input type="text" name="title[{{$s_language->id}}]" placeholder="" class="form-control" value="{{ $m_data_descriptions[$s_language->id]->title }}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12 text-end">
                                            <button type="button" class="btn btn-soft-info waves-effect waves-light custom_copy_to_all copyToAll" data-language-id="{{$s_language->id}}"><i class="fa-light fa-copy"></i></button>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card mb-4 total_table_card">
                        <div class="card-header align-items-center d-flex">
                            <h6 class="card-title mb-0 flex-grow-1">General Content</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xs-12 col-sm-12 col-md-12 mb-3">
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