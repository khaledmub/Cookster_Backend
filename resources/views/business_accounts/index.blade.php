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
        <div class="card mb-4 total_table_card">
            <div class="card-body">
                <div class="tab-content text-muted">
                    <div class="tab-pane active" id="liveweight_tab" role="tabpanel">
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
                                         <th>Name</th>
                                         <th>Email</th>
                                         <th>Phone</th>
                                         <th>Outstanding Balance</th>
                                         <th>Is B2B?</th>
                                         <th>Is Soft Delete?</th>
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
        </div>
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection
