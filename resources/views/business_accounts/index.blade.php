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
                                         <th>One-Time QR Reward Outstanding Balance</th>
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

<!-- One-Time QR Reward Modal -->
<div class="modal fade themeModal" id="oneTimeQRRewardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">One-Time QR Reward</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input id="business_account_id" type="hidden" name="business_account_id" class="form-control" value="">
                <div class="mb-3">
                    <label class="form-label">Enable</label>
                    <div class="form-check form-switch" dir="ltr">
                        <input id="allow_one_time_qr_discount" name="allow_one_time_qr_discount" class="form-check-input" type="checkbox" role="switch">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="no_of_one_time_discounts" class="form-label">No. of Discounts</label>
                    <input id="no_of_one_time_discounts" type="number" name="no_of_one_time_discounts" class="form-control" value="">
                </div>
                <div class="mb-3">
                    <label for="one_time_max_discount" class="form-label">Max. Discount</label>
                    <input id="one_time_max_discount" type="number" step="0.01" name="one_time_max_discount" class="form-control" value="">
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-primary waves-effect waves-light oneTimeQRRewardSaveBtn"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
