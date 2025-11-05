<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\GenerickeyController;
use App\Http\Controllers\GenerickeyvalueController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\VideosController;
use App\Http\Controllers\UserPaymentsController;
use App\Http\Controllers\UserReviewsController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\BannersController;
use App\Http\Controllers\BlogcategoriesController;
use App\Http\Controllers\BlogsController;
use App\Http\Controllers\WorksController;
use App\Http\Controllers\PersonalAccountsController;
use App\Http\Controllers\SponsoredAccountsController;
use App\Http\Controllers\BusinessAccountsController;
use App\Http\Controllers\ChefsAccountsController;
use App\Http\Controllers\ScreensController;
use App\Http\Controllers\AdvertisementsController;
use App\Http\Controllers\PackagesController;
use App\Http\Controllers\CitiesgroupsController;
use App\Http\Controllers\AudiosController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CronjobController;

Route::get('generate', function (){
    \Illuminate\Support\Facades\Artisan::call('storage:link');
    echo 'ok';
});
Auth::routes();

Route::get('change/lang', [LocalizationController::class, 'lang_change'])->name('LangChange');
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/about_us', [HomeController::class, 'about_us'])->name('about_us');
Route::get('/blog/{category?}', [HomeController::class, 'blog'])->name('blog');
Route::get('/blog/{category}/{post}', [HomeController::class, 'blog_post'])->name('blog.post');
Route::get('/privacy_policy', [HomeController::class, 'privacy_policy'])->name('privacy_policy');
Route::get('/terms_of_use', [HomeController::class, 'terms_of_use'])->name('terms_of_use');
Route::get('/contact_us', [HomeController::class, 'contact_us'])->name('contact_us');
Route::post('ajax/submit_contact_us', [HomeController::class, 'submit_contact_us'])->name('contact.submit_contact_us.ajax');

Route::group(['middleware' => ['auth'], 'prefix' => 'admin'], function() {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    // Roles
    Route::resource('roles', RoleController::class);
    Route::post('ajax/get_roles', [RoleController::class, 'get_roles_ajax']);
    
    // Users
    Route::resource('users', UserController::class);
    Route::post('ajax/get_users', [UserController::class, 'get_users_ajax']);

    // Pages
    Route::resource('pages', PagesController::class);
    Route::post('ajax/get_pages', [PagesController::class, 'get_data_ajax']);

    // Generic Keys
    Route::resource('generickeys', GenerickeyController::class);
    Route::post('ajax/get_generic_keys', [GenerickeyController::class, 'get_data_ajax']);

    // Generic Key Values
    Route::resource('generickeyvalues', GenerickeyvalueController::class);
    Route::post('ajax/get_generic_key_values', [GenerickeyvalueController::class, 'get_data_ajax']);

    // Categories
    Route::resource('categories', CategoriesController::class);
    Route::post('ajax/get_categories', [CategoriesController::class, 'get_data_ajax']);

    // Videos
    Route::resource('videos', VideosController::class);
    Route::post('ajax/get_videos', [VideosController::class, 'get_data_ajax']);

    // User Payments
    Route::resource('user_payments', UserPaymentsController::class);
    Route::post('ajax/get_user_payments', [UserPaymentsController::class, 'get_data_ajax']);

    // User Reviews
    Route::resource('user_reviews', UserReviewsController::class);
    Route::post('ajax/get_user_reviews', [UserReviewsController::class, 'get_data_ajax']);
    Route::get('user_review_status_update/{id}/{status}', [UserReviewsController::class, 'user_review_status_update']);

    // Notifications
    Route::resource('notifications', NotificationsController::class);
    Route::post('ajax/get_notifications', [NotificationsController::class, 'get_data_ajax']);

    // Banners
    Route::resource('banners', BannersController::class);
    Route::post('ajax/get_banners', [BannersController::class, 'get_data_ajax']);

    // Blog Categories
    Route::resource('blogcategories', BlogcategoriesController::class);
    Route::post('ajax/get_blogcategories', [BlogcategoriesController::class, 'get_data_ajax']);

    // Blogs
    Route::resource('blogs', BlogsController::class);
    Route::post('ajax/get_blogs', [BlogsController::class, 'get_data_ajax']);

    // Screens
    Route::resource('screens', ScreensController::class);
    Route::post('ajax/get_screens', [ScreensController::class, 'get_data_ajax']);

    // Advertisements
    Route::resource('advertisements', AdvertisementsController::class);
    Route::post('ajax/get_advertisements', [AdvertisementsController::class, 'get_data_ajax']);

    // Packages
    Route::resource('packages', PackagesController::class);
    Route::post('ajax/get_packages', [PackagesController::class, 'get_data_ajax']);

    // Cities Groups
    Route::resource('cities_groups', CitiesgroupsController::class);
    Route::post('ajax/get_cities_groups', [CitiesgroupsController::class, 'get_data_ajax']);

    // How It Works
    Route::resource('works', WorksController::class);
    Route::post('ajax/get_works', [WorksController::class, 'get_data_ajax']);

    // Personal Accounts
    Route::resource('personal_accounts', PersonalAccountsController::class);
    Route::post('ajax/get_personal_accounts', [PersonalAccountsController::class, 'get_data_ajax']);

    // Sponsored Accounts
    Route::resource('sponsored_accounts', SponsoredAccountsController::class);
    Route::post('ajax/get_sponsored_accounts', [SponsoredAccountsController::class, 'get_data_ajax']);

    // Business Accounts
    Route::resource('business_accounts', BusinessAccountsController::class);
    Route::post('ajax/get_business_accounts', [BusinessAccountsController::class, 'get_data_ajax']);

    // Chefs Accounts
    Route::resource('chef_accounts', ChefsAccountsController::class);
    Route::post('ajax/get_chef_accounts', [ChefsAccountsController::class, 'get_data_ajax']);

    // Audios
    Route::resource('audios', AudiosController::class);
    Route::post('ajax/get_audios', [AudiosController::class, 'get_data_ajax']);

    // Settings
    Route::resource('settings', SettingsController::class);
    
    // AJAX Requests
    Route::post('ajax/get_country_states', [DashboardController::class, 'get_country_states']);
    Route::post('ajax/get_state_cities', [DashboardController::class, 'get_state_cities']);
    Route::post('ajax/get_country_cities', [DashboardController::class, 'get_country_cities']);
    Route::post('ajax/change_user_status', [DashboardController::class, 'change_user_status']);
    Route::post('ajax/change_video_status', [DashboardController::class, 'change_video_status']);
    Route::post('ajax/change_user_review_visibility', [DashboardController::class, 'change_user_review_visibility']);
    Route::post('ajax/get_front_users_list', [DashboardController::class, 'get_front_users_list']);
    Route::post('ajax/clear_outstanding_balance', [DashboardController::class, 'clear_outstanding_balance']);
    Route::post('ajax/clear_one_time_qr_outstanding_balance', [DashboardController::class, 'clear_one_time_qr_outstanding_balance']);
    Route::post('ajax/get_business_account_details', [DashboardController::class, 'get_business_account_details']);
    Route::post('ajax/update_one_time_qr_data', [DashboardController::class, 'update_one_time_qr_data']);
});

// Cronjob Scripts
Route::get('delete_expire_sponsored_videos', [CronjobController::class, 'delete_expire_sponsored_videos'])->name('delete_expire_sponsored_videos');
Route::get('send_reminder_for_subscription_expiry', [CronjobController::class, 'send_reminder_for_subscription_expiry'])->name('send_reminder_for_subscription_expiry');
Route::get('send_reminder_for_sponsored_video_expiry', [CronjobController::class, 'send_reminder_for_sponsored_video_expiry'])->name('send_reminder_for_sponsored_video_expiry');
Route::get('disable_reported_videos', [CronjobController::class, 'disable_reported_videos'])->name('disable_reported_videos');