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
        @can($data['permission_initial'].'-create')
        <div class="row mb-2">
            <div class="col">
                <div class="h-100">
                    <!--end row-->
                    <a href="{{ route($data['url_path'].'.create') }}" class="btn btn-primary btn-label">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="ri-add-circle-fill label-icon align-middle fs-16 me-2"></i>
                            </div>
                            <div class="flex-grow-1">
                                Create New {{$data['module_title_singular']}}
                            </div>
                        </div>
                    </a>
                </div> <!-- end .h-100-->
            </div> <!-- end col -->
        </div>
        @endcan
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
                                 <th>Title</th>
                                 <th>To</th>
                                 <th>User</th>
                                 <th>Date/Time</th>
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
<div class="modal fade themeModal" id="notificationModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id=""></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-2">
        <table class="table table-bordered">
            <tr>
                <td class="table-light"><b>Title</b></td>
                <td><span class="notification_title"></span></td>
            </tr>
            <tr>
                <td class="table-light"><b>Date</b></td>
                <td><span class="notification_date"></span></td>
            </tr>
            <tr>
                <td class="table-light"><b>Text</b></td>
                <td><span class="notification_text"></span></td>
            </tr>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
