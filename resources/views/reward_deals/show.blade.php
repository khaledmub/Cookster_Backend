@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{ $data['module_title_singular'] }} Details</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ url('admin/reward-deals') }}">{{ $data['module_title_plural'] }}</a></li>
                            <li class="breadcrumb-item active">Details</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        @if ($message = Session::get('success'))
            <div class="alert alert-success"><p>{{ $message }}</p></div>
        @endif
        @if ($message = Session::get('error'))
            <div class="alert alert-danger"><p>{{ $message }}</p></div>
        @endif

        <div class="card mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Partner:</strong> {{ $data['deal']->partner_name ?: $data['deal']->partner_user_id }}</p>
                        <p class="mb-1"><strong>Item:</strong> {{ $data['deal']->title }}</p>
                        <p class="mb-1"><strong>Remaining:</strong> {{ $data['deal']->quantity_remaining }} / {{ $data['deal']->quantity_total }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Status:</strong> {{ $data['deal']->status }}</p>
                        <p class="mb-1"><strong>Created:</strong> {{ $data['deal']->created_at }}</p>
                        <p class="mb-1"><strong>Partner blocked:</strong> {{ $data['deal']->partner_blocked_at ? 'Yes' : 'No' }}</p>
                    </div>
                </div>
                @can($data['permission_initial'].'-edit')
                <div class="d-flex gap-2">
                    @if($data['deal']->status === 'active')
                    <form method="POST" action="{{ url('admin/reward-deals/'.$data['deal']->id.'/pause') }}">
                        @csrf
                        <button type="submit" class="btn btn-warning">Pause deal</button>
                    </form>
                    @endif
                    @if($data['deal']->partner_blocked_at)
                    <form method="POST" action="{{ url('admin/reward-deals/partners/'.$data['deal']->partner_user_id.'/unblock') }}">
                        @csrf
                        <button type="submit" class="btn btn-success">Unblock partner</button>
                    </form>
                    @else
                    <form method="POST" action="{{ url('admin/reward-deals/partners/'.$data['deal']->partner_user_id.'/block') }}">
                        @csrf
                        <button type="submit" class="btn btn-danger">Block partner</button>
                    </form>
                    @endif
                </div>
                @endcan
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Redemption log</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Username</th>
                                <th>Redeemed at</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['redemptions'] as $row)
                            <tr>
                                <td>{{ $row->client_name ?: $row->client_user_id }}</td>
                                <td>{{ $row->client_user_name }}</td>
                                <td>{{ $row->created_at }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3">No redemptions yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
