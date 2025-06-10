@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{$data['module_title_plural']}}</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">{{$data['module_title_plural']}}</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="h-100">
                    <!--end row-->
                    <div class="card mb-4 total_table_card">
                        <div class="card-body">
                            <form id="searchFormListing">
                                <div class="row">
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">Key <span class="text-danger">*</span></label>
                                            <select name="key_id" class="form-select select2">
                                                <option value="">Please select option</option>
                                                @foreach($data['generic_keys'] as $gkey)
                                                <option {{ request('key_id') && request('key_id')==$gkey->id ? 'selected' : '' }} value="{{ $gkey->id }}">{{ $gkey->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3" style="margin-top:28px">
                                        <button type="button" class="btn btn-primary searchSubmitter">Submit</button>
                                    </div>
                                    <div class="col-xs-6 col-sm-6 col-md-6 mb-3" style="text-align: right; margin-top:25px;">
                                        @can($data['permission_initial'].'-create')
                                        <a href="{{ route($data['url_path'].'.create', ['key_id' => request()->key_id]) }}" class="btn btn-primary btn-label">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="ri-add-circle-fill label-icon align-middle fs-16 me-2"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    Create New {{$data['module_title_singular']}}
                                                </div>
                                            </div>
                                        </a>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div> <!-- end .h-100-->
            </div> <!-- end col -->
        </div>
        <div class="card mb-4 total_table_card">
            <div class="card-body">
                @if ($message = Session::get('success'))
                    <div class="alert alert-success">
                        <p>{{ $message }}</p>
                    </div>
                @endif
                <div class="table-responsive custom_datatable">
                    <table class="table table-bordered  table-nowrap dynamicTable">
                        <thead class="table-light">
                            <tr>
                                 <th>Generic Key</th>
                                 <th>Name</th>
                                 <th>Status</th>
                                 <th width="280px">Action</th>
                              </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection
