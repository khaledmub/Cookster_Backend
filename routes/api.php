<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ApiController;

use App\Http\Middleware\SetLanguage;

Route::get('/videos/list2', [ApiController::class, 'videos_list2']);

Route::middleware([SetLanguage::class])->group(function () {
    /** General **/
    Route::get('/entities', [ApiController::class, 'entities']);
    Route::get('/site_languages', [ApiController::class, 'site_languages']);
    Route::get('/countries', [ApiController::class, 'countries']);
    Route::get('/states', [ApiController::class, 'states']);
    Route::get('/cities', [ApiController::class, 'cities']);
    Route::get('/generic_key_value', [ApiController::class, 'generic_key_value']);
    Route::get('/site_settings', [ApiController::class, 'site_settings']);
    Route::get('/started_screens', [ApiController::class, 'started_screens']);
    Route::get('/packages/list', [ApiController::class, 'packages_list']);

    /** Auth **/
    Route::get('/registration_settings', [ApiController::class, 'registration_settings']);
    Route::post('/register', [ApiController::class, 'register']);
    Route::post('/validate_register', [ApiController::class, 'validate_register']);
    Route::post('/login', [ApiController::class, 'login']);
    Route::post('/login_with_email', [ApiController::class, 'login_with_email']);
    Route::get('/page', [ApiController::class, 'page_content']);
    Route::post('/forgot_password/verify_email', [ApiController::class, 'forgot_password_verify_email']);
    Route::post('/forgot_password/verify_code', [ApiController::class, 'forgot_password_verify_code']);
    Route::post('/forgot_password/update_password', [ApiController::class, 'forgot_password_update_password']);

    /** Without Auth **/
    Route::post('/search', [ApiController::class, 'search']);
    Route::get('/profile_details', [ApiController::class, 'profile_details']);
    Route::get('/followers_list', [ApiController::class, 'followers_list']);

    // Videos
    Route::get('/videos/settings', [ApiController::class, 'video_settings']);
    Route::post('/videos/list', [ApiController::class, 'videos_list']);
    Route::get('/videos/details', [ApiController::class, 'video_details']);

    // Nearest Business Accounts/Restaurants
    Route::post('/business_accounts/nearest', [ApiController::class, 'nearest_business_accounts']);

    /** With Auth **/
    Route::middleware(['auth:sanctum'])->group( function () {
        // My Account
        Route::get('/profile', [ApiController::class, 'profile']);
        Route::post('/edit_profile', [ApiController::class, 'edit_profile']);
        Route::post('/logout', [ApiController::class, 'logout']);
        Route::post('/delete_account', [ApiController::class, 'delete_account']);
        Route::post('/block_user', [ApiController::class, 'block_user']);
        Route::get('/blocked_users_list', [ApiController::class, 'blocked_users_list']);
        Route::post('/follow_unfollow', [ApiController::class, 'follow_unfollow']);
        Route::post('/remove_follower', [ApiController::class, 'remove_follower']);

        // Videos
        Route::post('/videos/create', [ApiController::class, 'create_video']);
        Route::post('/videos/edit', [ApiController::class, 'edit_video']);
        Route::delete('/videos/delete', [ApiController::class, 'delete_video']);
        Route::post('/videos/contact_for_order', [ApiController::class, 'contact_for_order']);

        Route::post('/videos/save_unsave', [ApiController::class, 'video_save_unsave']);
        Route::post('/videos/saved_list', [ApiController::class, 'saved_videos_list']);
        Route::post('/videos/update_average_rating', [ApiController::class, 'update_video_average_rating']);

        Route::post('/videos/sponsor/add', [ApiController::class, 'add_video_sponsor']);

        // Audios
        Route::post('/audios/list', [ApiController::class, 'audios_list']);

        // Reports
        Route::get('/reports/categories', [ApiController::class, 'report_categories']);
        Route::post('/videos/reports/add', [ApiController::class, 'add_video_report']);

        // Video Comments
        Route::post('/videos/comments/create', [ApiController::class, 'create_video_comment']);

        // Notifications
        Route::get('/notifications/list', [ApiController::class, 'notifications_list']);

        // Subscription
        Route::post('/packages/subscribe', [ApiController::class, 'subscribe_package']);
    });
});