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
                                            <label class="form-label">User</label>
                                            <select name="user" class="form-select select2">
                                                <option value="">Please select option</option>
                                                @foreach($data['users'] as $user)
                                                <option {{ request('user') && request('user')==$user->id ? 'selected' : '' }} value="{{ $user->id }}">{{ $user->name }} - {{$user->entity_name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">Video Type</label>
                                            <select name="video_type" class="form-select select2">
                                                <option value="">Please select option</option>
                                                @foreach($data['video_types']['values'] as $type)
                                                <option {{ request('video_type') && request('video_type')==$type->id ? 'selected' : '' }} value="{{ $type->id }}">{{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">Title</label>
                                            <input type="text" name="title" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3" style="margin-top:28px">
                                        <button type="button" class="btn btn-primary searchSubmitter">Submit</button>
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
                                 <th>ID</th>
                                 <th>User</th>
                                 <th>Title</th>
                                 <th>Video Type</th>
                                 <th>Image</th>
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
