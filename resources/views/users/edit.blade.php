@extends('layouts.app')

@section('content')

<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Edit User</h4>

                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('admin/dashboard') }}">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="{{ url('admin/users') }}">Users</a></li>
                            <li class="breadcrumb-item active">Edit User</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
        <!-- end page title -->

        
        <div class="row">
            <div class="col">
                <div class="h-100">
                    <!--end row-->
                    
                </div> <!-- end .h-100-->
            </div> <!-- end col -->
        </div>
        <div class="card mb-4 total_table_card">
            <div class="card-body">
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
                <form method="POST" action="{{ route('users.update', $user->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" placeholder="" class="form-control" value="{{ $user->name }}">
                            </div>
                        </div>
                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" placeholder="" class="form-control" value="{{ $user->email }}">
                            </div>
                        </div>
                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" placeholder="" class="form-control">
                            </div>
                        </div>
                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm-password" placeholder="" class="form-control">
                            </div>
                        </div>
                        <div class="col-xs-3 col-sm-3 col-md-3 mb-3">
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="roles[]" class="form-select" data-choices aria-label="Default select example">
                                    @foreach ($roles as $value => $label)
                                        <option value="{{ $value }}" {{ isset($userRole[$value]) ? 'selected' : ''}}>
                                            {{ $label }}
                                        </option>
                                     @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-xs-12 col-sm-12 col-md-12">
                            <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="fa-solid fa-floppy-disk"></i> Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- container-fluid -->
</div>
<!-- End Page-content -->
@endsection