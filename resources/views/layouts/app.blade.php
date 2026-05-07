<!doctype html>
@php
$settings=\App\Helpers\AppHelper::get_site_settings();
$notifications=\App\Helpers\AppHelper::get_unread_notifications();
@endphp
<html lang="en" data-layout="vertical" data-topbar="dark" data-sidebar-size="lg">

<head>

    <meta charset="utf-8" />
    <title>Dashboard | {{ env('APP_NAME') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Themesbrand" name="author" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ asset('assets/admin/images/favicon.ico') }}">

    <!-- jsvectormap css -->
    <link href="{{ asset('assets/admin/libs/jsvectormap/css/jsvectormap.min.css') }}" rel="stylesheet" type="text/css" />

    <!--Swiper slider css-->
    <link href="{{ asset('assets/admin/libs/swiper/swiper-bundle.min.css') }}" rel="stylesheet" type="text/css" />

    <!-- Layout config Js -->
    <script src="{{ asset('assets/admin/js/layout.js') }}"></script>
    <!-- Bootstrap Css -->
    <link href="{{ asset('assets/admin/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{ asset('assets/admin/css/icons.min.css') }}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{ asset('assets/admin/css/app.min.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-colvis-2.3.3/b-html5-2.3.3/b-print-2.3.3/datatables.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.1/themes/base/jquery-ui.css">
    <!-- fullcalendar css -->
    <link href="{{ asset('assets/admin/libs/fullcalendar/main.min.css') }}" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">

    <!-- custom Css-->
    <link href="{{ asset('assets/admin/css/all.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/admin/css/custom.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/admin/css/custom.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/admin/css/custom_c.css') }}" rel="stylesheet" type="text/css" />


</head>

<body>
    <div class="preloader">
        <div class="loader">
            <svg viewBox="25 25 50 50">
                <circle cx="50" cy="50" r="20"></circle>
            </svg>
        </div>
    </div>
    <input type="hidden" id="baseurl" value="{{ rtrim(config('app.url'), '/') }}/">
    <input type="hidden" id="pageName" value="{{ Route::currentRouteName() }}">
    <!-- Begin page -->
    <div id="layout-wrapper">
        <div class="toastify on bg-danger toastify-right toastify-top customtoastifyError" aria-live="polite" style="transform: translate(0px, 0px); top: 15px;"></div>
        <div class="toastify on bg-success toastify-right toastify-top customtoastifySuccess" aria-live="polite" style="transform: translate(0px, 0px); top: 15px;"></div>
        <header id="page-topbar" class="">
            <div class="layout-width">
                <div class="navbar-header">
                    <div class="d-flex">
                        <!-- LOGO -->
                        <div class="navbar-brand-box horizontal-logo">
                            <a href="{{ url('admin/dashboard') }}" class="logo logo-dark">
                                <span class="logo-sm">
                                    <img src="{{ asset('assets/admin/images/logo_sm_2.png') }}" alt="" height="22">
                                </span>
                                <span class="logo-lg">
                                    <img src="{{ asset('assets/admin/images/logo_sm_2.png') }}" alt="" height="33">
                                </span>
                            </a>

                            <a href="{{ url('admin/dashboard') }}" class="logo logo-light">
                                <span class="logo-sm">
                                    <img src="{{ asset('assets/admin/images/logo_sm_2.png') }}" alt="" height="22">
                                </span>
                                <span class="logo-lg">
                                    <img src="{{ asset('assets/admin/images/logo_sm_2.png') }}" alt="" height="45">
                                </span>
                            </a>
                        </div>

                        <!-- <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger" id="topnav-hamburger-icon">
                            <span class="hamburger-icon">
                                <span></span>
                                <span></span>
                                <span></span>
                            </span>
                        </button> -->
                    </div>

                    <div class="d-flex align-items-center">

                        <div class="dropdown d-md-none topbar-head-dropdown header-item">
                            <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle" id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bx bx-search fs-22"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-search-dropdown">
                                <form class="p-3">
                                    <div class="form-group m-0">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search ..." aria-label="Recipient's username">
                                            <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="ms-1 header-item d-none d-sm-flex">
                            @if(request()->is('admin/dashboard') || request()->is('admin'))
                            <button type="button"
                                class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle layout-rightside-btn">
                                <i class="ri-pulse-line fs-22"></i>
                            </button>
                            @endif
                            <div class="dropdown topbar-head-dropdown ms-1 header-item">
                                <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle"
                                    id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <i class='bx bx-bell fs-22'></i>
                                    <span
                                        class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger">{{sizeof($notifications)}}<span
                                            class="visually-hidden">unread notifications</span></span>
                                </button>
                                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                                    aria-labelledby="page-header-notifications-dropdown">

                                    <div class="dropdown-head bg-primary bg-pattern rounded-top">
                                        <div class="p-3">
                                            <div class="row align-items-center">
                                                <div class="col">
                                                    <h6 class="m-0 fs-16 fw-semibold text-white"> Notifications </h6>
                                                </div>
                                                <div class="col-auto dropdown-tabs">
                                                    <span class="badge badge-soft-light fs-13"> {{sizeof($notifications)}} New</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="" id="notificationItemsTabContent">
                                        <!-- <div class="tab-pane fade show py-2 ps-2" id="" role="tabpanel"> -->
                                            <div data-simplebar style="max-height: 300px;" class="pe-2">
                                                @foreach($notifications as $notification)
                                                @php
                                                $details = \App\Helpers\AppHelper::get_notification_subject_text($notification);
                                                @endphp
                                                <div class="text-reset notification-item d-block dropdown-item position-relative">
                                                    <div class="d-flex">
                                                        <div class="avatar-xs me-3">
                                                            <span
                                                                class="avatar-title bg-soft-danger text-danger rounded-circle fs-16">
                                                                <i class='bx bx-message-square-dots'></i>
                                                            </span>
                                                        </div>
                                                        <div class="flex-1">
                                                            <a href="{{$details['href']}}" class="stretched-link">
                                                                <h6 class="mt-0 mb-2 fs-13 lh-base">{{$details['subject']}}</h6>
                                                                <p>{{$details['text']}}</p>
                                                            </a>
                                                            <p class="mb-0 fs-11 fw-medium text-uppercase text-muted">
                                                                <span><i class="mdi mdi-clock-outline"></i> {{$details['date_time']}}</span>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>

                                        <!-- </div> -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dropdown ms-sm-3 header-item topbar-user">
                            <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="d-flex align-items-center">
                                    <img class="rounded-circle header-profile-user" src="{{ asset('assets/admin/images/logo_sm_2.png') }}" alt="Header Avatar">
                                    <span class="text-start ms-xl-2">
                                        <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ env('APP_NAME') }}</span>
                                        <span class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text">@foreach (auth()->user()->roles as $role){{ $role->name }}@endforeach</span>
                                    </span>
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <!-- item-->
                                <h6 class="dropdown-header">{{ auth()->user()->name }}</h6>

                                <a class="dropdown-item" id="logoutClicker" href="javascript:void(0)">
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST">
                                        @csrf
                                    </form>
                                    <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i><span class="align-middle">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <!-- ========== App Menu ========== -->
        <div class="app-menu navbar-menu">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <!-- Dark Logo-->
                <a href="{{ url('admin/dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ asset('assets/admin/images/logo_white.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('assets/admin/images/logo_white.png') }}" alt="" height="17">
                    </span>
                </a>
                <!-- Light Logo-->
                <a href="{{ url('admin/dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ asset('assets/admin/images/logo_white.png') }}" alt="" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('assets/admin/images/logo_white.png') }}" alt="" height="35">
                    </span>
                </a>
                <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover" id="vertical-hover">
                    <i class="ri-record-circle-line"></i>
                </button>
            </div>

            <div id="scrollbar">
                <div class="container-fluid">
            
                    <div id="two-column-menu">
                    </div>
                    <ul class="navbar-nav" id="navbar-nav">
                        <li class="menu-title"><span data-key="t-menu">General</span></li>
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/dashboard') || request()->is('admin') ? 'active' : '' }}" href="{{ url('admin/dashboard') }}">
                                <i class="ri-home-3-line"></i> <span data-key="t-dashboards">Dashboard</span>
                            </a>
                        </li> <!-- end Dashboard Menu -->

                        @if(auth()->user()->can('users-list') || auth()->user()->can('personal-accounts') || auth()->user()->can('business-accounts') || auth()->user()->can('chef-accounts') || auth()->user()->can('sponsored-accounts'))
                        @php
                        $entitiesActive = request()->is('admin/users*') || request()->is('admin/personal_accounts*') || request()->is('admin/business_accounts*') || request()->is('admin/chef_accounts*') || request()->is('admin/sponsored_accounts*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $entitiesActive ? '' : 'collapsed' }} {{ $entitiesActive ? 'active' : '' }}" href="#sidebarLayouts1" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $entitiesActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts1">
                                <i class="ri-team-line"></i> <span data-key="t-layouts">Entities</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $entitiesActive ? 'show' : '' }}" id="sidebarLayouts1">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('users-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('users.index') }}" class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}" data-key="t-detached">System Users</a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('personal-accounts'))
                                    <li class="nav-item">
                                        <a href="{{ route('personal_accounts.index') }}" class="nav-link {{ request()->is('admin/personal_accounts*') ? 'active' : '' }}" data-key="t-detached">Personal Accounts</a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('business-accounts'))
                                    <li class="nav-item">
                                        <a href="{{ route('business_accounts.index') }}" class="nav-link {{ request()->is('admin/business_accounts*') ? 'active' : '' }}" data-key="t-detached">Business Accounts</a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('chef-accounts'))
                                    <!-- <li class="nav-item">
                                        <a href="{{ route('chef_accounts.index') }}" class="nav-link {{ request()->is('admin/chef_accounts*') ? 'active' : '' }}" data-key="t-detached">Chef Accounts</a>
                                    </li> -->
                                    @endif
                                    @if(auth()->user()->can('sponsored-accounts'))
                                    <li class="nav-item">
                                        <a href="{{ route('sponsored_accounts.index') }}" class="nav-link {{ request()->is('admin/sponsored_accounts*') ? 'active' : '' }}" data-key="t-detached">Sponsored Accounts</a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('role-list') || auth()->user()->can('role-create'))
                        @php
                        $rolesActive = request()->is('admin/roles*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $rolesActive ? '' : 'collapsed' }} {{ $rolesActive ? 'active' : '' }}" href="#sidebarLayouts2" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $rolesActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts2">
                                <i class="ri-shield-user-line"></i> <span data-key="t-layouts">Roles</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $rolesActive ? 'show' : '' }}" id="sidebarLayouts2">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('role-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('roles.index') }}" class="nav-link {{ request()->is('admin/roles') ? 'active' : '' }}" data-key="t-detached">All Roles</a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('role-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('roles.create') }}" class="nav-link {{ request()->is('admin/roles/create') ? 'active' : '' }}" data-key="t-detached">Create Role</a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('generic-keys-list') || auth()->user()->can('generic-keys-create'))
                        @php
                        $gKeyActive = request()->is('admin/generickeys*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $gKeyActive ? '' : 'collapsed' }} {{ $gKeyActive ? 'active' : '' }}" href="#sidebarLayouts4" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $gKeyActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts4">
                                <i class="fa-light fa-nfc-pen"></i> <span data-key="t-layouts">Generic Keys</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $gKeyActive ? 'show' : '' }}" id="sidebarLayouts4">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('generic-keys-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('generickeys.index') }}" class="nav-link {{ request()->is('admin/generickeys') ? 'active' : '' }}" data-key="t-detached"><span>All Generic Keys</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('generic-keys-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('generickeys.create') }}" class="nav-link {{ request()->is('admin/generickeys/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Generic Key</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('generic-keys-list') || auth()->user()->can('generic-keys-create'))
                        @php
                        $gKeyVActive = request()->is('admin/generickeyvalues*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $gKeyVActive ? '' : 'collapsed' }} {{ $gKeyVActive ? 'active' : '' }}" href="#sidebarLayouts5" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $gKeyVActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts5">
                                <i class="fa-light fa-database"></i> <span data-key="t-layouts">Generic Key Values</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $gKeyVActive ? 'show' : '' }}" id="sidebarLayouts5">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('generic-key-values-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('generickeyvalues.index') }}" class="nav-link {{ request()->is('admin/generickeyvalues') ? 'active' : '' }}" data-key="t-detached"><span>All Generic Key Values</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('generic-key-values-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('generickeyvalues.create') }}" class="nav-link {{ request()->is('admin/generickeyvalues/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Generic Key Value</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('categories-list') || auth()->user()->can('categories-create'))
                        @php
                        $categoriesActive = request()->is('admin/categories*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $categoriesActive ? '' : 'collapsed' }} {{ $categoriesActive ? 'active' : '' }}" href="#sidebarLayouts6" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts6">
                                <i class="fa-regular fa-layer-group"></i> <span data-key="t-layouts">Categories</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $categoriesActive ? 'show' : '' }}" id="sidebarLayouts6">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('categories-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('categories.index') }}" class="nav-link {{ request()->is('admin/categories') ? 'active' : '' }}" data-key="t-detached"><span>All Categories</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('categories-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('categories.create') }}" class="nav-link {{ request()->is('admin/categories/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Category</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('screens-list') || auth()->user()->can('screens-create'))
                        @php
                        $screensActive = request()->is('admin/screens*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $screensActive ? '' : 'collapsed' }} {{ $screensActive ? 'active' : '' }}" href="#sidebarLayouts7" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts7">
                                <i class="fa-regular fa-mobile-screen"></i> <span data-key="t-layouts">Screens</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $screensActive ? 'show' : '' }}" id="sidebarLayouts7">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('screens-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('screens.index') }}" class="nav-link {{ request()->is('admin/screens') ? 'active' : '' }}" data-key="t-detached"><span>All Screens</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('screens-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('screens.create') }}" class="nav-link {{ request()->is('admin/screens/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Screen</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('advertisements-list') || auth()->user()->can('advertisements-create'))
                        @php
                        $advertisementsActive = request()->is('admin/advertisements*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $advertisementsActive ? '' : 'collapsed' }} {{ $advertisementsActive ? 'active' : '' }}" href="#sidebarLayouts8" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts8">
                                <i class="fa-regular fa-rectangle-ad"></i> <span data-key="t-layouts">Advertisements</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $advertisementsActive ? 'show' : '' }}" id="sidebarLayouts8">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('advertisements-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('advertisements.index') }}" class="nav-link {{ request()->is('admin/advertisements') ? 'active' : '' }}" data-key="t-detached"><span>All Advertisements</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('advertisements-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('advertisements.create') }}" class="nav-link {{ request()->is('admin/advertisements/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Advertisement</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('packages-list') || auth()->user()->can('packages-create'))
                        @php
                        $packagesActive = request()->is('admin/packages*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $packagesActive ? '' : 'collapsed' }} {{ $packagesActive ? 'active' : '' }}" href="#sidebarLayouts11" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $packagesActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts11">
                                <i class="fa-regular fa-hand-holding-box"></i> <span data-key="t-layouts">Packages</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $packagesActive ? 'show' : '' }}" id="sidebarLayouts11">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('packages-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('packages.index') }}" class="nav-link {{ request()->is('admin/packages') ? 'active' : '' }}" data-key="t-detached"><span>All Packages</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('packages-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('packages.create') }}" class="nav-link {{ request()->is('admin/packages/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Package</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('cities-groups-list') || auth()->user()->can('cities-groups-create'))
                        @php
                        $citiesgroupsActive = request()->is('admin/cities_groups*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $citiesgroupsActive ? '' : 'collapsed' }} {{ $citiesgroupsActive ? 'active' : '' }}" href="#sidebarLayouts12" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $citiesgroupsActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts12">
                                <i class="fa-regular fa-city"></i> <span data-key="t-layouts">Cities Groups</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $citiesgroupsActive ? 'show' : '' }}" id="sidebarLayouts12">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('cities-groups-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('cities_groups.index') }}" class="nav-link {{ request()->is('admin/cities_groups') ? 'active' : '' }}" data-key="t-detached"><span>All Cities Groups</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('cities-groups-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('cities_groups.create') }}" class="nav-link {{ request()->is('admin/cities_groups/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Cities Group</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('audios-list') || auth()->user()->can('audios-create'))
                        @php
                        $audiosActive = request()->is('admin/audios*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $audiosActive ? '' : 'collapsed' }} {{ $audiosActive ? 'active' : '' }}" href="#sidebarLayouts13" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $audiosActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts13">
                                <i class="fa-regular fa-city"></i> <span data-key="t-layouts">Audios</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $audiosActive ? 'show' : '' }}" id="sidebarLayouts13">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('audios-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('audios.index') }}" class="nav-link {{ request()->is('admin/audios') ? 'active' : '' }}" data-key="t-detached"><span>All Audios</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('audios-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('audios.create') }}" class="nav-link {{ request()->is('admin/audios/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Audio</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('videos-list'))
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/videos') || request()->is('admin/videos/*') ? 'active' : '' }}" href="{{ route('videos.index') }}">
                                <i class="fa-regular fa-camcorder"></i> <span data-key="t-dashboards">Videos</span>
                            </a>
                        </li>
                        @endif
                        @if(auth()->user()->can('user-payments-list'))
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/user_payments') || request()->is('admin/user_payments/*') ? 'active' : '' }}" href="{{ route('user_payments.index') }}">
                                <i class="fa-regular fa-money-bill"></i> <span data-key="t-dashboards">User Payments</span>
                            </a>
                        </li>
                        @endif
                        @if(auth()->user()->can('user-reviews-list'))
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/user_reviews') || request()->is('admin/user_reviews/*') ? 'active' : '' }}" href="{{ route('user_reviews.index') }}">
                                <i class="fa-regular fa-star"></i> <span data-key="t-dashboards">User Reviews</span>
                            </a>
                        </li>
                        @endif
                        @if(auth()->user()->can('notifications-list'))
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/notifications') ||  request()->is('admin/notifications/*') ? 'active' : '' }}" href="{{ route('notifications.index') }}">
                                <i class="fa-regular fa-bell"></i> <span data-key="t-dashboards">Notifications</span>
                            </a>
                        </li>
                        @endif
                        @if(auth()->user()->can('settings-edit'))
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ request()->is('admin/settings/*') ? 'active' : '' }}" href="{{ route('settings.edit',1) }}">
                                <i class="ri-settings-2-line"></i> <span data-key="t-dashboards">Settings</span>
                            </a>
                        </li>
                        @endif

                        <li class="menu-title"><span data-key="t-menu">Website</span></li>
                        @if(auth()->user()->can('blogcategories-list') || auth()->user()->can('blogcategories-create'))
                        @php
                        $blogcategoriesActive = request()->is('admin/blogcategories*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $blogcategoriesActive ? '' : 'collapsed' }} {{ $blogcategoriesActive ? 'active' : '' }}" href="#sidebarLayouts14" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts14">
                                <i class="fa-regular fa-grid-2"></i> <span data-key="t-layouts">Blog Categories</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $blogcategoriesActive ? 'show' : '' }}" id="sidebarLayouts14">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('blogcategories-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('blogcategories.index') }}" class="nav-link {{ request()->is('admin/blogcategories') ? 'active' : '' }}" data-key="t-detached"><span>All Blog Categories</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('blogcategories-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('blogcategories.create') }}" class="nav-link {{ request()->is('admin/blogcategories/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Blog Category</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('blogs-list') || auth()->user()->can('blogs-create'))
                        @php
                        $blogsActive = request()->is('admin/blogs*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $blogsActive ? '' : 'collapsed' }} {{ $blogsActive ? 'active' : '' }}" href="#sidebarLayouts15" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts15">
                                <i class="fa-regular fa-file-lines"></i> <span data-key="t-layouts">Blogs</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $blogsActive ? 'show' : '' }}" id="sidebarLayouts15">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('blogs-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('blogs.index') }}" class="nav-link {{ request()->is('admin/blogs') ? 'active' : '' }}" data-key="t-detached"><span>All Blogs</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('blogs-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('blogs.create') }}" class="nav-link {{ request()->is('admin/blogs/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Blog</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('banners-list') || auth()->user()->can('banners-create'))
                        @php
                        $bannersActive = request()->is('admin/banners*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $bannersActive ? '' : 'collapsed' }} {{ $bannersActive ? 'active' : '' }}" href="#sidebarLayouts9" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts9">
                                <i class="fa-regular fa-images"></i> <span data-key="t-layouts">Banners</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $bannersActive ? 'show' : '' }}" id="sidebarLayouts9">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('banners-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('banners.index') }}" class="nav-link {{ request()->is('admin/banners') ? 'active' : '' }}" data-key="t-detached"><span>All Banners</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('banners-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('banners.create') }}" class="nav-link {{ request()->is('admin/banners/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Banner</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('pages-list') || auth()->user()->can('pages-create'))
                        @php
                        $pagesActive = request()->is('admin/pages*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $pagesActive ? '' : 'collapsed' }} {{ $pagesActive ? 'active' : '' }}" href="#sidebarLayouts3" data-bs-toggle="collapse" role="button"
                                aria-expanded="{{ $pagesActive ? 'true' : 'false' }}" aria-controls="sidebarLayouts3">
                                <i class="fa-light fa-notes"></i> <span data-key="t-layouts">Pages</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $pagesActive ? 'show' : '' }}" id="sidebarLayouts3">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('pages-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('pages.index') }}" class="nav-link {{ request()->is('admin/pages') ? 'active' : '' }}" data-key="t-detached"><span>All Pages</span></a>
                                    </li>
                                    @endif
                                    <!-- @if(auth()->user()->can('pages-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('pages.create') }}" class="nav-link {{ request()->is('admin/pages/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Page</span></a>
                                    </li>
                                    @endif -->
                                </ul>
                            </div>
                        </li>
                        @endif
                        @if(auth()->user()->can('works-list') || auth()->user()->can('works-create'))
                        @php
                        $worksActive = request()->is('admin/works*');
                        @endphp
                        <li class="nav-item">
                            <a class="nav-link menu-link {{ $worksActive ? '' : 'collapsed' }} {{ $worksActive ? 'active' : '' }}" href="#sidebarLayouts10" data-bs-toggle="collapse" role="button"
                                aria-expanded="false" aria-controls="sidebarLayouts10">
                                <i class="fa-regular fa-briefcase"></i> <span data-key="t-layouts">How It Works</span>
                            </a>
                            <div class="collapse menu-dropdown custom-menu-dropdown {{ $worksActive ? 'show' : '' }}" id="sidebarLayouts10">
                                <ul class="nav nav-sm flex-column">
                                    @if(auth()->user()->can('works-list'))
                                    <li class="nav-item">
                                        <a href="{{ route('works.index') }}" class="nav-link {{ request()->is('admin/works') ? 'active' : '' }}" data-key="t-detached"><span>All Works</span></a>
                                    </li>
                                    @endif
                                    @if(auth()->user()->can('works-create'))
                                    <li class="nav-item">
                                        <a href="{{ route('works.create') }}" class="nav-link {{ request()->is('admin/works/create') ? 'active' : '' }}" data-key="t-detached"><span>Create Work</span></a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>
                        @endif
                    </ul>
                </div>
                <!-- Sidebar -->
            </div>
        </div>
        <!-- Left Sidebar End -->
        <!-- Vertical Overlay-->
        <div class="vertical-overlay"></div>

        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            @yield('content')
            
            <div class="modal fade" id="attendanceTimeLocation" tabindex="-1" role="dialog" aria-labelledby="attendanceTimeLocationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title" id="attendanceTimeLocationModalLabel">Attendance Location</h4>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body"></div>
                    </div>
                </div>
            </div>
            <footer class="footer">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <script>document.write(new Date().getFullYear())</script> © <a href="https://www.abaskatech.com/" target="_blank">Abaska Technologies</a>
                        </div>
                        <div class="col-sm-6">
                            <!-- <div class="text-sm-end d-none d-sm-block">
                                Design &amp; Develop by Abaska Technologies
                            </div> -->
                        </div>
                    </div>
                </div>
            </footer>
        </div>
        <!-- end main content-->

    </div>


    <!-- Modal -->
    <div class="modal fade themeModal" id="videoModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id=""></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-2">
            <video width="100%" height="270" controls>
              <source src="" type="video/mp4">
            </video>
          </div>
        </div>
      </div>
    </div>
    <!-- END layout-wrapper -->

    <!--start back-to-top-->
    <button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
        <i class="ri-arrow-up-line"></i>
    </button>
    <!--end back-to-top-->

    <!-- JAVASCRIPT -->
    <script src="{{ asset('assets/admin/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/admin/libs/node-waves/waves.min.js') }}"></script>
    <script src="{{ asset('assets/admin/libs/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>
    <script src="{{ asset('assets/admin/js/plugins.js') }}"></script>
    <!-- apexcharts -->
    <script src="{{ asset('assets/admin/libs/apexcharts/apexcharts.min.js') }}"></script>

    <!-- Vector map-->
    <script src="{{ asset('assets/admin/libs/jsvectormap/js/jsvectormap.min.js') }}"></script>
    <script src="{{ asset('assets/admin/libs/jsvectormap/maps/world-merc.js') }}"></script>

    <!--Swiper slider js-->
    <script src="{{ asset('assets/admin/libs/swiper/swiper-bundle.min.js') }}"></script>

    <!-- Dashboard init -->
    <!-- <script src="{{ asset('assets/admin/js/pages/dashboard-ecommerce.init.js') }}"></script> -->
    <script>
        document.querySelectorAll(".layout-rightside-btn").forEach(function(e){var t=document.querySelector(".layout-rightside-col");e.addEventListener("click",function(){t.classList.contains("d-block")?(t.classList.remove("d-block"),t.classList.add("d-none")):(t.classList.remove("d-none"),t.classList.add("d-block"))})}),window.addEventListener("resize",function(){var e=document.querySelector(".layout-rightside-col");document.querySelectorAll(".layout-rightside-btn").forEach(function(){window.outerWidth<1699||3440<window.outerWidth?e.classList.remove("d-block"):1699<window.outerWidth&&(console.log("yesss"),e.classList.add("d-block"))})}),document.querySelector(".overlay").addEventListener("click",function(){1==document.querySelector(".layout-rightside-col").classList.contains("d-block")&&document.querySelector(".layout-rightside-col").classList.remove("d-block")}),window.addEventListener("load",function(){var e=document.querySelector(".layout-rightside-col");document.querySelectorAll(".layout-rightside-btn").forEach(function(){window.outerWidth<1699||3440<window.outerWidth?e.classList.remove("d-block"):1699<window.outerWidth&&e.classList.add("d-block")})});
    </script>

    <!-- calendar min js -->
    <script src="{{ asset('assets/admin/libs/fullcalendar/main.min.js') }}"></script>

    <script>
        document.querySelectorAll("[data-choices]").forEach(function(e){var t={},a=e.attributes;a["data-choices-groups"]&&(t.placeholderValue="This is a placeholder set in the config"),a["data-choices-search-false"]&&(t.searchEnabled=!1),a["data-choices-search-true"]&&(t.searchEnabled=!0),a["data-choices-removeItem"]&&(t.removeItemButton=!0),a["data-choices-sorting-false"]&&(t.shouldSort=!1),a["data-choices-sorting-true"]&&(t.shouldSort=!0),a["data-choices-multiple-default"],a["data-choices-multiple-groups"],a["data-choices-multiple-remove"]&&(t.removeItemButton=!0),a["data-choices-limit"]&&(t.maxItemCount=a["data-choices-limit"].value.toString()),a["data-choices-limit"]&&(t.maxItemCount=a["data-choices-limit"].value.toString()),a["data-choices-editItem-true"]&&(t.maxItemCount=!0),a["data-choices-editItem-false"]&&(t.maxItemCount=!1),a["data-choices-text-unique-true"]&&(t.duplicateItemsAllowed=!1),a["data-choices-text-disabled-true"]&&(t.addItems=!1),a["data-choices-text-disabled-true"]?new Choices(e,t).disable():new Choices(e,t)})
    </script>

    <!-- App js -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.1/b-2.3.3/b-colvis-2.3.3/b-html5-2.3.3/b-print-2.3.3/datatables.min.js"></script>
    <script src="{{ asset('assets/admin/js/sweetalert.js') }}"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>
    <script src="{{ asset('assets/admin/js/ckeditor/ckeditor.js') }}"></script>
    <script src="{{ asset('assets/admin/libs/@ckeditor/ckeditor5-build-classic/build/ckeditor.js') }}"></script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=true&libraries=places&key=AIzaSyBxMeZhnLJfK4ax7_GOGDd00OS5-jBFc4M"></script>
    <script src="{{ asset('assets/admin/js/locationpicker.jquery.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/jquery.placepicker.js') }}"></script>
    <script src="{{ asset('assets/admin/js/custom.js') }}"></script>
    <script src="{{ asset('assets/admin/js/app.js') }}"></script>
</body>

</html>