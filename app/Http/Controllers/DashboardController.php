<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Helpers\AppHelper;
use App\Models\Asm;
use App\Models\Staffmember;
use App\Models\Branch;
use App\Models\User;
use Spatie\Permission\Models\Role;
use DB;
use Hash;
use PDF;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\S3Service;


class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(private S3Service $s3Service)
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $data = array();
        $data['personal_accounts_count'] = DB::table('front_users')->where('entity', 1)->count();
        $data['business_accounts_count'] = DB::table('front_users')->where('entity', 2)->count();
        // $data['chef_accounts_count'] = DB::table('front_users')->where('entity', 3)->count();
        $data['sponsored_accounts_count'] = DB::table('front_users')->where('entity', 8)->count();
        $data['videos_count'] = DB::table('videos')->count();

        $query=DB::table('videos as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->orderBy('v.system_id', 'DESC');
        $query->where('v.is_soft_delete', 0);
        $data['latest_videos'] = $query->select(['v.*', 'u.name as user_name'])->limit(8)->get();
        foreach($data['latest_videos'] as $key => $video){
            $video_id = $video->id;
            $comments_count = 0;
            $likes = 0;

            // $url = "https://firestore.googleapis.com/v1/projects/".env('FIREBASE_PROJECT_ID')."/databases/(default)/documents/videos/{$video_id}/comments?key=".env('FIREBASE_KEY');
            // $comments = AppHelper::call_curl_request($url);
            // if(isset($comments['documents'])){
            //     $comments_count = sizeof($comments['documents']);
            // }

            // $url = "https://firestore.googleapis.com/v1/projects/".env('FIREBASE_PROJECT_ID')."/databases/(default)/documents/videos/{$video_id}?key=".env('FIREBASE_KEY');
            // $video_collection = AppHelper::call_curl_request($url);
            // if(isset($video_collection['fields']['likes']['arrayValue']) && isset($video_collection['fields']['likes']['arrayValue']['values'])){
            //     $likes = count($video_collection['fields']['likes']['arrayValue']['values']);
            // }

            $data['latest_videos'][$key]->total_likes = $likes;
            $data['latest_videos'][$key]->total_comments = $comments_count;
        }
        return view('dashboard',compact('data'));
    }
    public function get_country_states(Request $request){
        $input = $request->all();
        $states = DB::table('states')->where('country_id',$input['country'])->get();
        return response()->json(['status' => true, 'states' => $states]);
    }
    public function get_state_cities(Request $request){
        $input = $request->all();
        $cities = DB::table('cities')->where('state_id',$input['state'])->get();
        return response()->json(['status' => true, 'cities' => $cities]);
    }
    public function get_country_cities(Request $request){
        $input = $request->all();
        $cities = DB::table('cities')->where('country_id',$input['country'])->get();
        return response()->json(['status' => true, 'cities' => $cities]);
    }
    public function get_front_users_list(Request $request){
        $input = $request->all();
        $query=DB::table('front_users as user');
        $query->where('user.entity', $input['to_type']);
        $query->where('user.status', 1);
        $users = $query->select(['user.*'])->get();
        return response()->json(['status' => true, 'users' => $users]);
    }
    public function change_user_status(Request $request){
        $input = $request->all();
        $data=array(
            'status' => $input['status']
        );
        DB::table('front_users')->where('id',$input['id'])->update($data);
        return response()->json(['status' => true, 'message' => 'Status changed successfully!']);
    }
    public function change_video_status(Request $request){
        $input = $request->all();
        if($input['status']==0){
            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->orderBy('v.system_id', 'DESC');
            $query->where('v.id',$input['id']);
            $video_details = $query->select(['v.*', 'u.name as user_name', 'u.uuid', 'u.id as front_user_id'])->first();
            if($video_details->uuid){
                $deviceTokens = array($video_details->uuid);
                $notification_data = [
                    'status' => true,
                ];
                $push_notification_text = [
                    'title' => 'Deactivated Video',
                    'text' => 'Your video ('.$video_details->title.') has been deactivated due to some restrictions. Please contact the administrator for more details.',
                    'notification_data' => $notification_data
                ];
                AppHelper::send_push_notification($push_notification_text, $deviceTokens);

                /* Create Notification Start */
                $notification_data = array();
                $notification_data['to_type'] = 2;
                $notification_data['front_user_id'] = $video_details->front_user_id;
                $notification_data['type'] = 3;
                $notification_data['video_id'] = $video_details->id;
                DB::table('notifications')->insert($notification_data);
                /* Create Notification End */
            }
        }
        $data=array(
            'status' => $input['status']
        );
        DB::table('videos')->where('id',$input['id'])->update($data);
        return response()->json(['status' => true, 'message' => 'Status changed successfully!']);
    }
}
