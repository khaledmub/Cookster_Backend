<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
use Carbon\Carbon;
    
class CronjobController extends Controller
{
    
    function __construct(){}

    public function delete_expire_sponsored_videos(Request $request){
        // http://localhost/cookster_admin/public/delete_expire_sponsored_videos
        DB::table('sponsored_videos')->where('end_date', '<', Carbon::today())->delete();
        return response()->json([
            'status' => true,
            'message' => 'Executed Successfully!',
        ], 201);
    }
    public function send_reminder_for_subscription_expiry(Request $request){
        // http://localhost/cookster_admin/public/send_reminder_for_subscription_expiry
        $days = 5; // Users going to expire in these days
        $expiringUsers = DB::table('front_users as fu')
        ->join('subscription_history as sh', 'sh.id', '=', 'fu.current_subscription_id')
        ->whereDate('sh.end_date', '=', now()->addDays(5)->toDateString())
        ->select('fu.*', 'sh.end_date as subscription_end_date')
        ->get();

        // echo "<pre>";
        // var_dump($expiringUsers);
        // exit;
        foreach($expiringUsers as $user){
            $deviceTokens = [$user->uuid];
            $notification_data = [
                'status' => true,
            ];
            $push_notification_text = [
                'title' => "Account Subscription Reminder",
                'text' => "Dear, ".$user->name." Your account subscription will expire at ".date(env('DATE_FORMAT'), strtotime($user->subscription_end_date))." ",
                'notification_data' => $notification_data
            ];
            AppHelper::send_push_notification($push_notification_text, $deviceTokens);
        }
        return response()->json([
            'status' => true,
            'message' => 'Executed Successfully!',
        ], 201);
    }
}