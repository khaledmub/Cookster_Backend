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
                                            <label class="form-label">Rating</label>
                                            <select name="rating" class="form-select select2">
                                                <option value="">Please select option</option>
                                                <option {{ request('rating') && request('rating') == 1 ? 'selected' : '' }} value="1">1</option>
                                                <option {{ request('rating') && request('rating') == 2 ? 'selected' : '' }} value="2">2</option>
                                                <option {{ request('rating') && request('rating') == 3 ? 'selected' : '' }} value="3">3</option>
                                                <option {{ request('rating') && request('rating') == 4 ? 'selected' : '' }} value="4">4</option>
                                                <option {{ request('rating') && request('rating') == 5 ? 'selected' : '' }} value="5">5</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select select2">
                                                <option value="">Please select option</option>
                                                <option {{ request('status') && request('status') == 0 ? 'selected' : '' }} value="0">Pending</option>
                                                <option {{ request('status') && request('status') == 1 ? 'selected' : '' }} value="1">Approved</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                                        <div class="form-group">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" class="form-control">
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
                                <th>Reviewer</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Date</th>
                                <th>Visibility</th>
                                <th>Status</th>
                                <th>Action</th>
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
