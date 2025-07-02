<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use App\Models\User;
use App\Models\Customer;
use App\Models\WalletTransaction;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use DateTime;
use DateInterval;
use DatePeriod;
use Mail;
use App;
use Carbon\Carbon;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise;

class AppHelper
{
    private static $userids=array();

	public static function send_email($from_email, $to_email, $subject, $message){
        return true;
        Mail::send([], [], function ($inner_message) use ($to_email, $subject, $from_email, $message){
          $inner_message->to($to_email)
            ->subject($subject)
            ->from($from_email)
            ->html($message);
        });
        return true;
	}
	public static function get_site_settings(){
		$data = Setting::where('id', 1)->first();
		return $data;
	}
    public static function send_verification_code($medium, $user){
        // Medium 1 for email, 2 for phone
        $data = array();
        $data['medium'] = $medium;
        $data['front_user_id'] = $user->id;
        $verfication_code = self::generateVerificationCode(5);
        $data['code'] = $verfication_code;
        DB::table('verification_codes')->insert($data);

        if($medium==1){
            $email_to = $user->email;
            $html = view('email_templates.front_user.verification_code', compact('user', 'verfication_code'))->render();
            $subject="Email Verification";
            self::send_email(env('MAIL_FROM_ADDRESS'), $email_to, $subject, $html);
        }
        return true;
    }
    public static function generateVerificationCode($length = 5) {
        $characters = '0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }
    public static function get_works(){
        $language = App::getLocale();
        $query = DB::table('works');
        $query->join('works_description', 'works_description.work_id', '=', 'works.id');
        $query->join('site_languages', 'works_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->orderBy('works.sort_order', 'ASC');
        $works = $query->select(['works.*', 'works_description.title', 'works_description.number', 'works_description.description'])->get();
        return $works;
    }
    public static function custom_number_format($number, $decimals){
        return number_format($number, $decimals, '.', ',');
    }
    public static function currency_formatter($number){
        $site_settings = self::get_site_settings();
        if($site_settings){
            return $site_settings->currency_symbol . number_format($number, 2, '.', ',');
        }
        else{
            return number_format($number, 2, '.', ',');
        }
    }
    public static function get_key_values($key_id, $language = ""){
        $language = App::getLocale();
        $query=DB::table('generic_keys');
        $query->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id');
        $query->join('site_languages as key_language', 'generic_keys_description.language_id', '=', 'key_language.id');
        if($language){
            $query->where('key_language.code', $language);
        }
        else{
            $query->where('key_language.is_default', 1);
        }
        $query->where('generic_keys.id', $key_id);
        $key = $query->select(['generic_keys.*', 'generic_keys_description.name as key_name'])->first();

        $query=DB::table('generic_key_values');
        $query->join('generic_key_values_description', 'generic_key_values_description.value_id', '=', 'generic_key_values.id');
        $query->join('site_languages', 'generic_key_values_description.language_id', '=', 'site_languages.id');
        if($language){
            $query->where('site_languages.code', $language);
        }
        else{
            $query->where('site_languages.is_default', 1);
        }
        $query->where('generic_key_values.status', 1);
        $query->where('generic_key_values.key_id', $key_id);
        $values = $query->select(['generic_key_values.*', 'generic_key_values_description.name'])->get();
        
        return array(
            'key' => $key,
            'values' => $values
        );
    }
    public static function get_key_values_by_value_ids($value_ids, $language = ""){
        $query=DB::table('generic_key_values');
        $query->join('generic_key_values_description', 'generic_key_values_description.value_id', '=', 'generic_key_values.id');
        $query->join('site_languages', 'generic_key_values_description.language_id', '=', 'site_languages.id');
        if($language){
            $query->where('site_languages.code', $language);
        }
        else{
            $query->where('site_languages.is_default', 1);
        }
        $query->where('generic_key_values.status', 1);
        $query->whereIn('generic_key_values.id', $value_ids);
        $values = $query->select(['generic_key_values.*', 'generic_key_values_description.name'])->get();
        return $values;
    }
    public static function id_formatter($type, $id){
        $return_data = '';
        if($type==1){
            $return_data = env('PERSONAL_ACCOUNTS_PREFIX').$id;
        }
        else if($type==2){
            $return_data = env('BUSINESS_ACCOUNTS_PREFIX').$id;
        }
        else if($type==3){
            $return_data = env('CHEF_ACCOUNTS_PREFIX').$id;
        }
        else if($type==4){
            $return_data = env('VIDEO_PREFIX').$id;
        }
        else if($type==5){
            $return_data = env('SPONSORED_ACCOUNTS_PREFIX').$id;
        }
        else if($type==6){
            $return_data = env('USER_PAYMENT_PREFIX').$id;
        }
        return $return_data;
    }
    public static function run_add_tag_script($tags){
        if($tags){
            $tags=explode(',', $tags);
            foreach($tags as $tag){
                $validate_tag = DB::table('tags')->where('name', $tag)->first();
                if(empty($validate_tag)){
                    $data = array();
                    $data['name'] = $tag;
                    DB::table('tags')->insert($data);
                }
            }
        }
        return true;
    }
    public static function get_user_details($user_id){
        return DB::table('front_users')->where('id', $user_id)->first();
    }
    public static function get_sponsor_type_label($type){
        $label = "";
        if($type==1){
            $label = '<label class="badge bg-primary">Basic</label>';
        }
        else if($type==2){
            $label = '<label class="badge bg-info">Premium</label>';
        }
        return $label;
    }
    public static function get_cities_names($ids){
        $city_names = '';
        if($ids){
            $ids = explode(',',$ids);
            $city_names_array = DB::table('cities')->whereIn('id', $ids)->pluck('name')->toArray();
            if($city_names_array){
                $city_names = implode(', ', $city_names_array);
            }
        }
        return $city_names;
    }
    public static function subscribe_user_to_package($user_id, $package_id, $payment_data = NULL){
        $query=DB::table('packages');
        $query->join('packages_description', 'packages_description.package_id', '=', 'packages.id');
        $query->join('site_languages', 'packages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        $query->where('packages.id', $package_id);
        $query->orderBy('packages.system_id', 'ASC');
        $package_details = $query->select(['packages.*', 'packages_description.title', 'packages_description.description'])->first();

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['front_user_id'] = $user_id;
        $ins_data['package_id'] = $package_details->id;
        $ins_data['start_date'] = date('Y-m-d');
        $ins_data['end_date'] = date('Y-m-d', strtotime('+'.$package_details->duration.' months'));
        $ins_data['duration'] = $package_details->duration;
        $ins_data['amount'] = $package_details->amount;
        DB::table('subscription_history')->insert($ins_data);

        $up_data=array();
        $up_data['current_subscription_id'] = $ins_data['id'];
        DB::table('front_users')->where('id',$user_id)->update($up_data);

        if($payment_data){
            self::add_user_payment(1, $ins_data['id'], $user_id, $package_details->amount, $payment_data);
        }

        return true;
    }
    public static function get_unread_notifications(){
        return DB::table('notifications')->where('to_type', 1)->where('read_status', 0)->orderBy('id', 'DESC')->get();
    }
    public static function get_notification_subject_text($notification){
        $details=array();
        $details['href']='';
        if($notification->type==1){
            // Notification for admin when any report submitted by the user against the video
            $video_details = DB::table('video_reports')->where('id', $notification->video_report_id)->first();
            $details['subject']=__('messages.video_reported');
            $details['text']=__('messages.video_reported_msg');
            
            if($video_details){
                $details['href']=url('admin/videos/'.$video_details->video_id.'?notification_id='.$notification->id);
            }
            else{
                $details['text']=__('messages.video_not_found_msg');
            }

            $details['date_time']=date(env('DATE_TIME_FORMAT') ,strtotime($notification->created_at));
        }
        else if($notification->type==2){
            // Notification for front user when admin send the push notification
            $push_notifications_details = DB::table('push_notifications')->where('id', $notification->push_notification_id)->first();
            $details['title']=$push_notifications_details->title;
            $details['text']=$push_notifications_details->text;
            $details['date_time']=date(env('DATE_TIME_FORMAT') ,strtotime($notification->created_at));
        }
        else if($notification->type==3){
            // Notification for front user when admin disable the video
            $video_details = DB::table('videos')->where('id', $notification->video_id)->first();
            $details['title']=__('messages.deactivated_video');
            
            if($video_details){
                $details['text']=__('messages.your_video') . ' ('.$video_details->title.') ' . __('messages.deactivated_video_msg');
            }
            else{
                $details['text']=__('messages.video_not_found_msg');
            }

            $details['date_time']=date(env('DATE_TIME_FORMAT') ,strtotime($notification->created_at));
        }
        return $details;
    }
    public static function send_push_notification($push_notification_text, $deviceTokens){
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        // create middleware
        $middleware = ApplicationDefaultCredentials::getMiddleware($scopes);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        $client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth'
        ]);
      
        $messages = [];

        foreach ($deviceTokens as $token) {
            $notification_data = array_map('strval', $push_notification_text['notification_data']);
            $single_message_data = [
                'token' => $token,
                'notification' => [
                    'title' => $push_notification_text['title'],
                    'body' => $push_notification_text['text'],
                ],
                'data' => $notification_data,
            ];
            $messages[] = $single_message_data;
        }

        ### Create message request promises
        $promises = function() use ($client, $messages) {
            foreach ($messages as $message) {
                yield $client->requestAsync('POST', 'https://fcm.googleapis.com/v1/projects/cockster-e477a/messages:send', [
                    'json' => ['message' => $message],
                ]);
            }
        };
        ### Create response handler
        $handleResponses = function (array $responses) {
            foreach ($responses as $response) {
                if ($response['state'] === Promise\PromiseInterface::FULFILLED) {
                    // $response['value'] is an instance of \Psr\Http\Message\RequestInterface
                    // echo $response['value']->getBody();
                } elseif ($response['state'] === Promise\PromiseInterface::REJECTED) {
                    // $response['reason'] is an exception
                    // echo $response['reason']->getMessage();
                }
            }
        };
        Promise\Utils::settle($promises())
            ->then($handleResponses)
            ->wait();
    }
    public static function call_curl_request($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL issues (not recommended for production)

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            // throw new Exception('cURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($response, true);
    }
    public static function add_user_sponsor_video($video_id, $cities, $sponsor_type, $days, $payment_data = NULL){
        $user = Auth::user();
        $user_entity_details = DB::table('entities')->where('id', $user->entity)->first();
        $settings = DB::table('settings')->where('id', 1)->select(['basic_sponsored_video_price', 'premium_sponsored_video_price', 'sponsor_video_discount'])->first();
        $no_of_cities = count(explode(',', $cities));
        $per_day_price = 0;
        $discount_percentage = 0;
        $discount_amount = 0;
        $total_amount = 0;

        if($sponsor_type==1){
            $per_day_price = $settings->basic_sponsored_video_price;
        }
        else if($sponsor_type==2){
            $per_day_price = $settings->premium_sponsored_video_price;
        }
        $total_amount = $per_day_price*$days*$no_of_cities;
        if($user_entity_details->subscription_required == 1){
            $isExpired = self::check_subscription_expired($user->id);
            if($isExpired){
                return false;
            }

            $discount_percentage = $settings->sponsor_video_discount;
            if($discount_percentage>0){
                $discount_amount = ($total_amount*$discount_percentage)/100;
            }
        }

        $ins_data=array();
        $ins_data['video_id'] = $video_id;
        $ins_data['cities'] = $cities;
        $ins_data['sponsor_type'] = $sponsor_type;
        $ins_data['days'] = $days;
        $ins_data['start_date'] = date('Y-m-d');
        $ins_data['end_date'] = date('Y-m-d', strtotime('+'.$days.' days'));
        $ins_data['per_day_price'] = $per_day_price;
        $ins_data['discount_percentage'] = $discount_percentage;
        $ins_data['discount_amount'] = $discount_amount;
        $ins_data['total_amount'] = $total_amount-$discount_amount;

        $sponsored_videos_data = $ins_data;
        $sponsored_videos_data['id'] = (string) \Str::uuid();
        $sponsored_videos_history_data = $ins_data;
        $sponsored_videos_history_data['id'] = (string) \Str::uuid();

        DB::table('sponsored_videos')->where('video_id', $video_id)->delete();

        DB::table('sponsored_videos')->insert($sponsored_videos_data);
        DB::table('sponsored_videos_history')->insert($sponsored_videos_history_data);

        if($payment_data){
            self::add_user_payment(2, $sponsored_videos_history_data['id'], $user->id, $total_amount-$discount_amount, $payment_data);
        }

        return true;
    }
    public static function check_subscription_expired($front_user_id){
        $isExpired = DB::table('subscription_history')->where('front_user_id', $front_user_id)
            ->orderByDesc('end_date')
            ->first()?->end_date < Carbon::now();
            
        return $isExpired;
    }
    public static function add_user_payment($payment_for, $external_id, $user_id, $amount, $payment_data){
        // $payment_for = 1:Subscription, 2:Sponsor

        $ins_data = array();
        $ins_data['id'] = (string) \Str::uuid();
        $ins_data['payment_for'] = $payment_for;
        $ins_data['external_id'] = $external_id;
        $ins_data['user_id'] = $user_id;
        $ins_data['amount'] = $amount;
        $ins_data['PaymentId'] = $payment_data['PaymentId'];
        $ins_data['TranId'] = $payment_data['TranId'];
        $ins_data['ECI'] = $payment_data['ECI'];
        $ins_data['TrackId'] = $payment_data['TrackId'];
        $ins_data['RRN'] = $payment_data['RRN'];
        $ins_data['cardBrand'] = $payment_data['cardBrand'];
        $ins_data['maskedPAN'] = $payment_data['maskedPAN'];
        $ins_data['PaymentType'] = $payment_data['PaymentType'];

        DB::table('user_payments')->insert($ins_data);

        return true;
    }
}