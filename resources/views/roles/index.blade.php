@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Roles</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">Roles</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
        <!-- end page title -->

        @can('role-create')
        <div class="row mb-2">
            <div class="col">
                <div class="h-100">
                    <!--end row-->
                    <a href="{{ route('roles.create') }}" class="btn btn-primary btn-label">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="ri-add-circle-fill label-icon align-middle fs-16 me-2"></i>
                            </div>
                            <div class="flex-grow-1">
                                Create New Role
                            </div>
                        </div>
                    </a>
                </div> <!-- end .h-100-->
            </div> <!-- end col -->
        </div>
        @endcan
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
                                         <th>Name</th>
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