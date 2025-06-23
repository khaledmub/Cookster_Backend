<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Hash;
use Illuminate\Support\Facades\DB;
use \App\Helpers\AppHelper;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\FrontUser;
use Auth;
use App;
use DatePeriod;
use DateTime;
use DateInterval;
use Image;
use Illuminate\Support\Facades\Validator;
use App\Services\S3Service;
use App\Services\ProfanityFilterService;

class ApiController extends Controller
{
    public function __construct(private S3Service $s3Service) {}

    public function validate_register(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:front_users,email',
            'phone' => 'nullable|unique:front_users,phone',
            'password' => 'required|string|min:8',
            'entity' => 'required',
            'uuid' => 'required',
        ]);
        // Add conditional validation based on the value of 'entity'
        $validator->sometimes(['business_type', 'contact_phone', 'contact_email', 'location', 'latitude', 'longitude'], ['required'], function ($input) {
            return $input->entity == 2;
        });
        $validator->sometimes(['state', 'contact_phone', 'contact_email'], ['required'], function ($input) {
            return $input->entity == 3;
        });
        $validator->sometimes(['type_of_account'], ['required'], function ($input) {
            return $input->entity == 8;
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        else{
            return response()->json([
                'status' => true,
            ], 200);
        }
    }
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:front_users,email',
            'phone' => 'nullable|unique:front_users,phone',
            'password' => 'required|string|min:8',
            'entity' => 'required',
            'uuid' => 'required',
        ]);
        // Add conditional validation based on the value of 'entity'
        $validator->sometimes(['business_type', 'contact_phone', 'contact_email', 'location', 'latitude', 'longitude'], ['required'], function ($input) {
            return $input->entity == 2;
        });
        $validator->sometimes(['state', 'contact_phone', 'contact_email'], ['required'], function ($input) {
            return $input->entity == 3;
        });
        $validator->sometimes(['type_of_account'], ['required'], function ($input) {
            return $input->entity == 8;
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $input = $request->all();
        $user = FrontUser::create([
            'id' => (string) \Str::uuid(),
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => isset($input['phone'])? $input['phone']: NULL,
            'password' => Hash::make($input['password']),
            'dob' => isset($input['dob'])? date('Y-m-d', strtotime($input['dob'])): NULL,
            'country' => $input['country']? $input['country']: 0,
            'state' => 0,
            'city' => $input['city']? $input['city']: 0,
            'uuid' => $input['uuid'],
            'entity' => $input['entity'],
        ]);

        $user = FrontUser::where('email', $request->email)->first();

        if($request->entity==2){
            $additional_data = array();
            $additional_data['front_user_id'] = $user->id;
            $additional_data['business_type'] = $request->business_type;
            $additional_data['contact_phone'] = $request->contact_phone;
            $additional_data['contact_email'] = $request->contact_email;
            $additional_data['website'] = $request->website;
            $additional_data['location'] = $request->location;
            $additional_data['latitude'] = $request->latitude;
            $additional_data['longitude'] = $request->longitude;
            DB::table('business_account_additional_data')->insert($additional_data);
        }
        if($request->entity==3){
            $additional_data = array();
            $additional_data['front_user_id'] = $user->id;
            $additional_data['country'] = 194;
            $additional_data['state'] = $request->state;
            $additional_data['city'] = 0;
            $additional_data['contact_phone'] = $request->contact_phone;
            $additional_data['contact_email'] = $request->contact_email;
            DB::table('chef_account_additional_data')->insert($additional_data);
        }
        if($request->entity==8){
            $additional_data = array();
            $additional_data['front_user_id'] = $user->id;
            $additional_data['type_of_account'] = $request->type_of_account;
            DB::table('sponsored_account_additional_data')->insert($additional_data);
        }
        $token = $user->createToken('FrontUserToken')->plainTextToken;
        $language = App::getLocale();

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }
        else{
            $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }

        $user->entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();
        if(isset($input['package_id']) && $input['package_id']!='' && $input['package_id']!=null){
            $payment_data = array();
            $payment_data['PaymentId'] = $request->PaymentId;
            $payment_data['TranId'] = $request->TranId;
            $payment_data['ECI'] = $request->ECI;
            $payment_data['TrackId'] = $request->TrackId;
            $payment_data['RRN'] = $request->RRN;
            $payment_data['cardBrand'] = $request->cardBrand;
            $payment_data['maskedPAN'] = $request->maskedPAN;
            $payment_data['PaymentType'] = $request->PaymentType;

            AppHelper::subscribe_user_to_package($user->id, $input['package_id'], $payment_data);
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.user_registered'),
            'user' => $user,
            'token' => $token,
        ], 201);
    }
    public function login(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'uuid' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Attempt login using email and password
        if (Auth::guard('front')->attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::guard('front')->user();

            // Check if the user account is soft deleted
            if ($user->is_soft_delete == 1) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.invalid_credentials'),
                ], 401);
            }

            // Check if the user account is deactivated
            if ($user->status == 0) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.account_deactivated'),
                ], 403);
            }

            $language = App::getLocale();

            // Generate a token
            $token = $user->createToken('FrontUserToken')->plainTextToken;

            if($language == 'ar'){
                $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
            }
            else{
                $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
            }

            $user->entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();

            $up_data=array();
            $up_data['uuid'] = $request->uuid;
            DB::table('front_users')->where('id',$user->id)->update($up_data);

            return response()->json([
                'status' => true,
                'message' => __('messages.login_successful'),
                'token' => $token,
                'user' => $user,
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => __('messages.invalid_credentials'),
        ], 401);
    }
    public function login_with_email(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'uuid' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Retrieve user by email
        $user = FrontUser::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }
        else if ($user->status == 0) {
            return response()->json([
                'status' => false,
                'message' => __('messages.account_deactivated'),
            ], 403);
        }

        $language = App::getLocale();

        // Generate a token
        $token = $user->createToken('FrontUserToken')->plainTextToken;

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }
        else{
            $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }

        $user->entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();

        $up_data=array();
        $up_data['uuid'] = $request->uuid;
        DB::table('front_users')->where('id',$user->id)->update($up_data);

        return response()->json([
            'status' => true,
            'message' => __('messages.login_successful'),
            'token' => $token,
            'user' => $user,
        ], 200);
    }
    public function forgot_password_verify_email(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Retrieve user by email
        $user = FrontUser::where('email', $request->email)->select(['id', 'name', 'email', 'phone', 'status'])->first();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.invalid_email'),
            ], 401);
        }
        else{
            if ($user->status == 0) {
                return response()->json([
                    'status' => false,
                    'message' => __('messages.account_deactivated'),
                ], 403);
            }
            else{
                AppHelper::send_verification_code(1,$user);
                return response()->json([
                    'status' => true,
                    'message' => __('messages.forgot_code_email_sent'),
                    'user' => $user,
                    'medium' => 1,
                ], 200);
            }
        }
    }
    public function forgot_password_verify_code(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'code' => 'required',
            'medium' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        else{
            $verify_code = DB::table('verification_codes')->where('medium', $request->medium)->where('front_user_id', $request->user_id)->where('code', $request->code)->first();
            if(empty($verify_code)){
                return response()->json([
                    'status' => false,
                    'message' => __('messages.invalid_verification_code'),
                ], 401);
            }
            else{
                DB::table('verification_codes')->where('medium', $request->medium)->where('front_user_id', $request->user_id)->where('code', $request->code)->delete();
                return response()->json([
                    'status' => true,
                    'message' => __('messages.verified_verification_code'),
                ], 200);
            }
        }
    }
    public function forgot_password_update_password(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'password' => 'required|string|min:8'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        else{
            $language = App::getLocale();
            $user = FrontUser::where('id', $request->user_id)->first();
            $user->password = Hash::make($request->input('password'));
            $user->save();

            // Generate a token
            $token = $user->createToken('FrontUserToken')->plainTextToken;

            if($language == 'ar'){
                $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
            }
            else{
                $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
            }

            $user->entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();
            return response()->json([
                'status' => true,
                'user' => $user,
                'message' => __('messages.password_update_success'),
                'token' => $token
            ]);
        }
    }
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.logout_successful'),
        ], 200);
    }
    public function delete_account(Request $request){
        // Soft delete
        $user = Auth::user();
        $user->sd_email = $user->email;
        $user->email = 'sd_' . $user->id . '@cookster.com';
        $user->is_soft_delete = 1;
        $user->save();
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.account_deleted_successfully'),
        ], 200);
    }
    public function blocked_users_list(){
        $user = Auth::user();

        // Blocked Users list
        $blocked_users = DB::table('blocked_users')->leftJoin('front_users', 'front_users.id', '=', 'blocked_users.blocked_user')->where('blocked_users.blocked_by', $user->id)->select(['front_users.id', 'front_users.name', 'front_users.email', 'front_users.image'])->get();
            
        $return_data = array(
            'status' => true,
            'blocked_users' => $blocked_users
        );
        return response()->json($return_data, 200);
    }
    public function followers_list(){
        $user = Auth::user();

        // Followers
        $followers = DB::table('followers')->leftJoin('front_users', 'front_users.id', '=', 'followers.follower_id')->where('followers.following_id', $user->id)->select(['front_users.id', 'front_users.name', 'front_users.email', 'front_users.image'])->get();

        // Following
        $following = DB::table('followers')->leftJoin('front_users', 'front_users.id', '=', 'followers.following_id')->where('followers.follower_id', $user->id)->select(['front_users.id', 'front_users.name', 'front_users.email', 'front_users.image'])->get();
            
        $return_data = array(
            'status' => true,
            'followers' => $followers,
            'following' => $following
        );
        return response()->json($return_data, 200);
    }
    public function profile(){
        $user = Auth::user();
        $language = App::getLocale();
        $additional_data = array();
        $form_settings = array();
        $cities = array();

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }
        else{
            $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }
        
        $user->entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();

        if($user->entity==2){
            $form_settings['business_types'] = AppHelper::get_key_values(1);
            $additional_data = DB::table('business_account_additional_data as b')->leftJoin('generic_key_values_description as bt', 'bt.value_id', '=', 'b.business_type')->join('site_languages as key_language', 'bt.language_id', '=', 'key_language.id')->where('key_language.code', $language)->where('b.front_user_id', $user->id)->select('b.*', 'bt.name as business_type_name')->first();
        }
        else if($user->entity==3){
            $additional_data = DB::table('chef_account_additional_data')->where('front_user_id', $user->id)->first();
        }
        else if($user->entity==8){
            $form_settings['type_of_account'] = AppHelper::get_key_values(4);
            $additional_data = DB::table('sponsored_account_additional_data')->where('front_user_id', $user->id)->first();
        }
        $form_settings['countries'] = DB::table('countries')->where('status', 1)->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        if($user->country > 0){
            $cities = DB::table('cities')->where('country_id', $user->country)->select(['id', 'name', 'state_id'])->get();
        }
        $form_settings['cities'] = $cities;

        if($user->entity==2 || $user->entity==3){
            // $form_settings['states'] = DB::table('states')->where('country_id', $additional_data->country)->select(['id', 'name', 'country_id'])->get();
            // if($additional_data->state > 0){
            //     $cities = DB::table('cities')->where('state_id', $additional_data->state)->select(['id', 'name', 'state_id'])->get();
            // }
            // $form_settings['cities'] = $cities;
        }

        $video_types = AppHelper::get_key_values(2)['values'];
        foreach($video_types as $key => $video_type){
            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
            $query->leftJoin('sponsored_videos as sv', 'sv.video_id', '=', 'v.id');
            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query->where('v.video_type', $video_type->id);
            $query->where('v.front_user_id', $user->id);
            $query1 = clone $query;
            $query2 = clone $query;
            $totalData = $query1->select(['v.id'])->count();
            $query2->orderBy('v.system_id', 'DESC');
            $videos = $query2->select(['v.*', 'u.name as user_name', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude', 'sv.sponsor_type', 'sv.cities', 'sv.days', 'sv.start_date', 'sv.end_date', 'sv.per_day_price', 'sv.discount_percentage', 'sv.discount_amount', 'sv.total_amount'])->get();

            foreach($videos as $video){
                if(!empty($video->cities)){
                    $cityIds = explode(',', $video->cities);
                    $cityNames = DB::table('cities')->whereIn('id', $cityIds)->pluck('name')->toArray();
                    $video->city_names = $cityNames? implode(',', $cityNames): '';
                }
                else{
                    $video->city_names = '';
                }
            }

            $video_types[$key]->videos = $videos;
        }

        $followers = DB::table('followers')->where('following_id', $user->id)->pluck('follower_id'); // Followers
        $following = DB::table('followers')->where('follower_id', $user->id)->pluck('following_id'); // Following

        $subscription = DB::table('subscription_history')->join('packages', 'packages.id', '=', 'subscription_history.package_id')->join('packages_description', 'packages_description.package_id', '=', 'packages.id')->join('site_languages', 'packages_description.language_id', '=', 'site_languages.id')->where('site_languages.code', $language)->where('subscription_history.front_user_id', $user->id)->orderBy('subscription_history.system_id', 'DESC')->select(['subscription_history.*', 'packages_description.title', 'packages_description.description'])->first();
            
        $return_data = array(
            'status' => true,
            'user' => $user,
            'additional_data' => $additional_data,
            'form_settings' => $form_settings,
            'video_types' => $video_types,
            'followers' => $followers,
            'following' => $following,
            'subscription' => $subscription
        );
        return response()->json($return_data, 200);
    }
    public function edit_profile(Request $request){
        $input = $request->all();
        $user = Auth::user();
        // $validator = Validator::make($request->all(), [
        //     'name' => 'required',
        //     'password' => 'nullable|string|min:8', // Makes password validation conditional if it's posted
        //     'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        // ]);
        // Add conditional validation based on the value of 'entity'
        // $validator->sometimes(['business_type', 'state', 'contact_phone', 'contact_email', 'website', 'location', 'latitude', 'longitude'], ['required'], function ($input) use ($user) {
        //     return $user->entity == 2;
        // });
        // $validator->sometimes(['state', 'contact_phone', 'contact_email'], ['required'], function ($input) use ($user) {
        //     return $user->entity == 3;
        // });

        // if ($validator->fails()) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => __('messages.validation_failed'),
        //         'errors' => $validator->errors(),
        //     ], 422);
        // }
        if($request->input('name')){
            $user->name = $request->input('name');
        }
        if($request->input('dob')){
            $user->dob = date('Y-m-d', strtotime($request->input('dob')));
        }

        // If password is provided, hash it and update
        if ($request->has('password') && !empty($request->input('password'))) {
            $user->password = Hash::make($request->input('password'));
        }
        if($request->input('country')){
            $user->country = $request->input('country');
        }
        if($request->input('city')){
            $user->city = $request->input('city');
        }

        if($request->input('phone')){
            $user->phone = $request->input('phone');
        }

        if($request->file('image')){
            $image = $request->file('image');
            $image_input['imagename'] = time().'.'.$image->extension();
            $fileresponse=$request->file('image')->storeAs('public/front_users',$image_input['imagename']);
            $destinationPath = storage_path('app/public/front_users/thumbnail');
            $img = Image::read($image->path());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath.'/'.$image_input['imagename']);
            $user->image = $image_input['imagename'];
        }
        if($request->file('cover_image')){
            $image = $request->file('cover_image');
            $image_input['imagename'] = time().'.'.$image->extension();
            $fileresponse=$request->file('cover_image')->storeAs('public/front_users',$image_input['imagename']);
            $destinationPath = storage_path('app/public/front_users/thumbnail');
            $img = Image::read($image->path());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath.'/'.$image_input['imagename']);
            $user->cover_image = $image_input['imagename'];
        }
        $user->save();
        if($user->entity==2){
            $additional_data = array();
            if($request->input('business_type')){
                $additional_data['business_type'] = $request->input('business_type');
            }
            // if($request->input('country')){
            //     $additional_data['country'] = $request->input('country');
            // }
            // $additional_data['country'] = 194;
            // if($request->input('state')){
            //     $additional_data['state'] = $request->input('state');
            // }
            // if($request->input('city')){
            //     $additional_data['city'] = $request->input('city');
            // }
            if($request->input('contact_phone')){
                $additional_data['contact_phone'] = $request->input('contact_phone');
            }
            if($request->input('contact_email')){
                $additional_data['contact_email'] = $request->input('contact_email');
            }
            if($request->input('website')){
                $additional_data['website'] = $request->input('website');
            }
            if($request->input('location')){
                $additional_data['location'] = $request->input('location');
            }
            if($request->input('latitude')){
                $additional_data['latitude'] = $request->input('latitude');
            }
            if($request->input('longitude')){
                $additional_data['longitude'] = $request->input('longitude');
            }
            if(!empty($additional_data)){
                $validate_additional_data = DB::table('business_account_additional_data')->where('front_user_id', $user->id)->first();
                if(empty($validate_additional_data)){
                    $additional_data['front_user_id'] = $user->id;
                    DB::table('business_account_additional_data')->insert($additional_data);
                }
                else{
                    DB::table('business_account_additional_data')->where('id',$validate_additional_data->id)->update($additional_data);
                }
            }
        }
        else if($user->entity==3){
            $additional_data = array();
            // if($request->input('country')){
            //     $additional_data['country'] = $request->input('country');
            // }
            $additional_data['country'] = 194;
            if($request->input('state')){
                $additional_data['state'] = $request->input('state');
            }
            // if($request->input('city')){
            //     $additional_data['city'] = $request->input('city');
            // }
            if($request->input('contact_phone')){
                $additional_data['contact_phone'] = $request->input('contact_phone');
            }
            if($request->input('contact_email')){
                $additional_data['contact_email'] = $request->input('contact_email');
            }
            if(!empty($additional_data)){
                $validate_additional_data = DB::table('chef_account_additional_data')->where('front_user_id', $user->id)->first();
                if(empty($validate_additional_data)){
                    $additional_data['front_user_id'] = $user->id;
                    DB::table('chef_account_additional_data')->insert($additional_data);
                }
                else{
                    DB::table('chef_account_additional_data')->where('id',$validate_additional_data->id)->update($additional_data);
                }
            }
        }
        if($user->entity==8){
            $additional_data = array();
            if($request->input('type_of_account')){
                $additional_data['type_of_account'] = $request->input('type_of_account');
            }
            if(!empty($additional_data)){
                $validate_additional_data = DB::table('sponsored_account_additional_data')->where('front_user_id', $user->id)->first();
                if(empty($validate_additional_data)){
                    $additional_data['front_user_id'] = $user->id;
                    DB::table('sponsored_account_additional_data')->insert($additional_data);
                }
                else{
                    DB::table('sponsored_account_additional_data')->where('id',$validate_additional_data->id)->update($additional_data);
                }
            }
        }

        return response()->json([
            'status' => true,
            'user' => $user,
            'message' => __('messages.user_updated')
        ]);
    }
    public function notifications_list(){
        $user = Auth::user();
        $query = DB::table('notifications');
        $query->where(function ($q) use ($user){
            $q->where('notifications.front_user_category', 0)->orWhere('notifications.front_user_category', $user->entity);
        });
        $query->where(function ($q) use ($user){
            $q->where('notifications.front_user_id', $user->id)->orWhereNull('notifications.front_user_id');
        });
        $query->whereDate('notifications.created_at', '>=', $user->created_at);
        $query->where('to_type', 2)->where('read_status', 0);
        $notifications = $query->limit(30)->orderBy('id', 'DESC')->select(['id', 'to_type', 'front_user_category', 'type', 'push_notification_id', 'video_id', 'read_status', 'status', 'created_at'])->get();
        foreach($notifications as $key => $notification){
            $notifications[$key]->details = AppHelper::get_notification_subject_text($notification);
        }
        return response()->json([
            'status' => true,
            'notifications' => $notifications,
        ], 200);
    }
    public function registration_settings(){
        $language = App::getLocale();

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'subscription_required', 'is_sponsored'];
        }
        else{
            $e_select = ['id', 'name', 'subscription_required', 'is_sponsored'];
        }

        $entities = DB::table('entities')->where('status', 1)->select($e_select)->orderBy('sort_order', 'ASC')->get();
        $countries = DB::table('countries')->where('status', 1)->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        $states = DB::table('states')->where('country_id', 194)->select(['id', 'name', 'country_id'])->get();
        $business_types = AppHelper::get_key_values(1);
        $type_of_account = AppHelper::get_key_values(4);

        $query=DB::table('packages');
        $query->join('packages_description', 'packages_description.package_id', '=', 'packages.id');
        $query->join('site_languages', 'packages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('packages.status', 1);
        $query->orderBy('packages.system_id', 'ASC');
        $packages = $query->select(['packages.*', 'packages_description.title', 'packages_description.description'])->get();
        
        return response()->json([
            'status' => true,
            'entities' => $entities,
            'countries' => $countries,
            'business_types' => $business_types,
            'type_of_account' => $type_of_account,
            'packages' => $packages,
        ], 200);
    }
    public function started_screens(){
        $language = App::getLocale();
        $query=DB::table('screens');
        $query->join('screens_description', 'screens_description.screen_id', '=', 'screens.id');
        $query->join('site_languages', 'screens_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $screens = $query->select(['screens.*', 'screens_description.title', 'screens_description.sub_title', 'screens_description.short_description'])->get();

        return response()->json([
            'status' => true,
            'screens' => $screens,
        ], 200);
    }
    public function page_content(Request $request){
        $language = App::getLocale();
        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', $request->type);
        $page = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description'])->first();
        return response()->json([
            'status' => true,
            'page' => $page,
        ], 200);
    }
    public function block_user(Request $request){
        $validator = Validator::make($request->all(), [
            'blocked_user' => 'required|exists:front_users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();

        $blocked_by = $user->id; // Authenticated user
        $blocked_user = $request->blocked_user;

        if ($blocked_by == $blocked_user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.cannot_block_yourself'),
            ], 400);
        }

        $existingBlocked = DB::table('blocked_users')
            ->where('blocked_by', $blocked_by)
            ->where('blocked_user', $blocked_user)
            ->first();

        if ($existingBlocked) {
            // Unblock
            DB::table('blocked_users')
                ->where('blocked_by', $blocked_by)
                ->where('blocked_user', $blocked_user)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => __('messages.user_unblocked_success'),
            ], 200);
        } else {
            // Block
            DB::table('blocked_users')->insert([
                'blocked_by' => $blocked_by,
                'blocked_user' => $blocked_user,
                'created_at' => now(),
            ]);

            // Delete following
            DB::table('followers')
                ->where('follower_id', $blocked_by)
                ->where('following_id', $blocked_user)
                ->delete();

            // Delete follower
            DB::table('followers')
                ->where('follower_id', $blocked_user)
                ->where('following_id', $blocked_by)
                ->delete();

            // Delete blocked user saved videos
            $blocked_user_videos = DB::table('videos')->where('front_user_id', $blocked_user)->pluck('id');
            if($blocked_user_videos){
                DB::table('user_saved_videos')
                    ->where('front_user_id', $blocked_by)
                    ->whereIn('video_id', $blocked_user_videos)
                    ->delete();
            }

            return response()->json([
                'status' => true,
                'message' => __('messages.user_blocked_success'),
            ], 200);
        }
    }
    public function follow_unfollow(Request $request){
        $validator = Validator::make($request->all(), [
            'following_id' => 'required|exists:front_users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();

        $follower_id = $user->id; // Authenticated user
        $following_id = $request->following_id;

        if ($follower_id == $following_id) {
            return response()->json([
                'status' => false,
                'message' => __('messages.cannot_follow_yourself'),
            ], 400);
        }

        $existingFollow = DB::table('followers')
            ->where('follower_id', $follower_id)
            ->where('following_id', $following_id)
            ->first();

        if ($existingFollow) {
            // Unfollow
            DB::table('followers')
                ->where('follower_id', $follower_id)
                ->where('following_id', $following_id)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => __('messages.unfollow_success'),
            ], 200);
        } else {
            // Follow
            DB::table('followers')->insert([
                'follower_id' => $follower_id,
                'following_id' => $following_id,
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => __('messages.follow_success'),
            ], 200);
        }
    }
    public function remove_follower(Request $request){
        $validator = Validator::make($request->all(), [
            'follower_id' => 'required|exists:front_users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();

        $following_id = $user->id; // Authenticated user
        $follower_id = $request->follower_id;
        
        DB::table('followers')
            ->where('follower_id', $follower_id)
            ->where('following_id', $following_id)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.follower_removed'),
        ], 200);
    }
    public function search(Request $request){
        $user = Auth::guard('sanctum')->user();
        $input = $request->all();
        $keywords = $input['keywords'];
        $videos = array();
        $business_accounts = array();
        $chef_accounts = array();

        if($user){
            $follower_id = $user->id;
            $followingIds = DB::table('followers')
                                    ->where('follower_id', $follower_id)
                                    ->pluck('following_id');
            $blocked_users = DB::table('blocked_users')
                                    ->where('blocked_by', $user->id)
                                    ->pluck('blocked_user');
            // $country = $user->country;
            // $city = $user->city;
        }

        $country = 0;
        $city = 0;
        if(isset($input['country']) && $input['country']!=''){
            $country_details = DB::table('countries')->whereRaw('LOWER(name) = ?', [strtolower($input['country'])])->first();
            if(isset($country_details->id)){
                $country = $country_details->id;
            }
        }
        if(isset($input['city']) && $input['city']!=''){
            $city_details = DB::table('cities')->where('country_id', $country)->whereRaw('LOWER(name) = ?', [strtolower($input['city'])])->first();
            if(isset($city_details->id)){
                $city = $city_details->id;
            }
        }
        $city_group = DB::table('cities_groups')->whereRaw('FIND_IN_SET(?, cities)', [$city])->first();
        if(!empty($city_group)){
            $cities_ids = explode(',', $city_group->cities);
        }
        else if($city != 0){
            $cities_ids = array($city);
        }
        else{
            $cities_ids = array();
        }

        $page = $input['page'] ?? 1; // Default to page 1 if not provided
        $length = 100000; // Number of records per page
        $start = ($page - 1) * $length;

        // is_following = 1, this bit is used to get only following videos

        if($input['type']==1){
            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
            $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
            $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
            $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
            $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
            $query->where(function ($q){
                $q->where('video_type_language.is_default', 1)
                  ->orWhere('v.video_type', 0); 
            });
            $query->where(function ($q) {
                $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
            });
            $query->where(function ($q) use($user){
                $q->where('v.publish_type', 2); 
                
                if($user){
                    $q->orWhere('v.publish_type', 1);
                }
            });
            $query->where(function ($q) use ($keywords){
                $q->where('v.title', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.description', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.tags', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.menu', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('video_type_description.name', 'LIKE', '%'.$keywords.'%');
            });

            if($user && isset($input['is_following']) && $input['is_following'] == 1){
                $query->whereIn('v.front_user_id', $followingIds);
            }
            else{
                if($country != 0){
                    $query->where('v.country', $country);
                }

                if(!empty($cities_ids)){
                    $query->whereIn('v.city', $cities_ids);
                }
            }

            // Exclude those videos which are from blocked user
            if($user){
                if($blocked_users){
                    $query->whereNotIn('v.front_user_id', $blocked_users);
                }
            }

            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query1 = clone $query;
            $query2 = clone $query;

            $totalData = $query1->select(['v.id'])->count();
            $query2->offset($start)->limit($length);
            $videos = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.email as user_email', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'), DB::raw('COALESCE(following.following_count, 0) as following_count')])->inRandomOrder()->get();
        }
        if($input['type']==2){
            // $query=DB::table('front_users as ba');
            // $query->join('business_account_additional_data as ad', 'ad.front_user_id', '=', 'ba.id');
            // $query->leftJoin('generic_key_values_description as business_type_description', 'business_type_description.value_id', '=', 'ad.business_type')->leftJoin('site_languages as business_type_language', 'business_type_description.language_id', '=', 'business_type_language.id');
            // $query->where(function ($q){
            //     $q->where('business_type_language.is_default', 1)
            //       ->orWhere('ad.business_type', 0); 
            // });
            // $query->where(function ($q) use ($keywords){
            //     $q->where('ba.name', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ba.email', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ba.phone', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.contact_phone', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.contact_email', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.website', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.location', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('business_type_description.name', 'LIKE', '%'.$keywords.'%');
            // });
            // $query->where('ba.entity', 2);
            // $query->where('ba.status', 1);
            // $query1 = clone $query;
            // $query2 = clone $query;
            // $business_accounts = $query2->select(['ba.*', 'ad.contact_phone', 'ad.contact_email', 'ad.website', 'ad.location', 'ad.latitude', 'ad.longitude', 'business_type_description.name as business_type_name'])->inRandomOrder()->get();

            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
            $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
            $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
            $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
            $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
            $query->where(function ($q){
                $q->where('video_type_language.is_default', 1)
                  ->orWhere('v.video_type', 0); 
            });
            $query->where(function ($q) {
                $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
            });
            $query->where(function ($q) use($user){
                $q->where('v.publish_type', 2); 
                
                if($user){
                    $q->orWhere('v.publish_type', 1);
                }
            });
            $query->where(function ($q) use ($keywords){
                $q->where('v.title', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.description', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.tags', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.menu', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('video_type_description.name', 'LIKE', '%'.$keywords.'%');
            });

            if($user && isset($input['is_following']) && $input['is_following'] == 1){
                $query->whereIn('v.front_user_id', $followingIds);
            }
            else{
                if($country != 0){
                    $query->where('v.country', $country);
                }

                if(!empty($cities_ids)){
                    $query->whereIn('v.city', $cities_ids);
                }
            }

            // Exclude those videos which are from blocked user
            if($user){
                if($blocked_users){
                    $query->whereNotIn('v.front_user_id', $blocked_users);
                }
            }

            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query->where('u.entity', 2);
            $query1 = clone $query;
            $query2 = clone $query;

            $totalData = $query1->select(['v.id'])->count();
            $query2->offset($start)->limit($length);
            $videos = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.email as user_email', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'), DB::raw('COALESCE(following.following_count, 0) as following_count')])->inRandomOrder()->get();
        }
        if($input['type']==3){
            // $query=DB::table('front_users as ca');
            // $query->join('chef_account_additional_data as ad', 'ad.front_user_id', '=', 'ca.id');
            // $query->where(function ($q) use ($keywords){
            //     $q->where('ca.name', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ca.email', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ca.phone', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.contact_phone', 'LIKE', '%'.$keywords.'%');
            //     $q->orWhere('ad.contact_email', 'LIKE', '%'.$keywords.'%');
            // });
            // $query->where('ca.entity', 3);
            // $query->where('ca.status', 1);
            // $query1 = clone $query;
            // $query2 = clone $query;
            // $chef_accounts = $query2->select(['ca.*', 'ad.contact_phone', 'ad.contact_email'])->inRandomOrder()->get();

            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
            $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
            $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
            $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
            $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
            $query->where(function ($q){
                $q->where('video_type_language.is_default', 1)
                  ->orWhere('v.video_type', 0); 
            });
            $query->where(function ($q) {
                $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
            });
            $query->where(function ($q) use($user){
                $q->where('v.publish_type', 2); 
                
                if($user){
                    $q->orWhere('v.publish_type', 1);
                }
            });
            $query->where(function ($q) use ($keywords){
                $q->where('v.title', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.description', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.tags', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.menu', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('video_type_description.name', 'LIKE', '%'.$keywords.'%');
            });

            // Exclude those videos which are from blocked user
            if($user){
                if($blocked_users){
                    $query->whereNotIn('v.front_user_id', $blocked_users);
                }
            }

            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query->where('u.entity', 3);
            $query1 = clone $query;
            $query2 = clone $query;

            $totalData = $query1->select(['v.id'])->count();
            $query2->offset($start)->limit($length);
            $videos = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.email as user_email', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'), DB::raw('COALESCE(following.following_count, 0) as following_count')])->inRandomOrder()->get();
        }
        else if($input['type']==4){
            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
            $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
            $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
            $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
            $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
            $query->where(function ($q){
                $q->where('video_type_language.is_default', 1)
                  ->orWhere('v.video_type', 0); 
            });
            $query->where(function ($q) {
                $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
            });
            $query->where(function ($q) use($user){
                $q->where('v.publish_type', 2); 
                
                if($user){
                    $q->orWhere('v.publish_type', 1);
                }
            });
            $query->where(function ($q) use ($keywords){
                $q->where('v.title', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.description', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.tags', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('v.menu', 'LIKE', '%'.$keywords.'%');
                $q->orWhere('video_type_description.name', 'LIKE', '%'.$keywords.'%');
            });
            
            if($user && isset($input['is_following']) && $input['is_following'] == 1){
                $query->whereIn('v.front_user_id', $followingIds);
            }
            else{
                if($country != 0){
                    $query->where('v.country', $country);
                }

                if(!empty($cities_ids)){
                    $query->whereIn('v.city', $cities_ids);
                }
            }

            // Exclude those videos which are from blocked user
            if($user){
                if($blocked_users){
                    $query->whereNotIn('v.front_user_id', $blocked_users);
                }
            }

            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query->where('v.average_rating', 5);
            $query1 = clone $query;
            $query2 = clone $query;

            $totalData = $query1->select(['v.id'])->count();
            $query2->offset($start)->limit($length);
            $videos = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.email as user_email', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'), DB::raw('COALESCE(following.following_count, 0) as following_count')])->inRandomOrder()->get();
        }

        return response()->json([
            'status' => true,
            'videos' => $videos,
            'business_accounts' => $business_accounts,
            'chef_accounts' => $chef_accounts,
        ], 200);
    }
    public function profile_details(Request $request){
        $input = $request->all();
        $language = App::getLocale();
        $user = DB::table('front_users')->where('id', $request->id)->first();
        $additional_data = array();
        if($user->entity==2){
            $additional_data = DB::table('business_account_additional_data as b')->leftJoin('generic_key_values_description as bt', 'bt.value_id', '=', 'b.business_type')->join('site_languages as key_language', 'bt.language_id', '=', 'key_language.id')->where('key_language.code', $language)->where('b.front_user_id', $user->id)->select('b.*', 'bt.name as business_type_name')->first();
        }
        else if($user->entity==3){
            $additional_data = DB::table('chef_account_additional_data')->where('front_user_id', $user->id)->first();
        }
        else if($user->entity==8){
            $additional_data = DB::table('sponsored_account_additional_data')->where('front_user_id', $user->id)->first();
        }
        $video_types = AppHelper::get_key_values(2)['values'];
        foreach($video_types as $key => $video_type){
            $query=DB::table('videos as v');
            $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
            $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
            $query->where('v.status', 1);
            $query->where('v.is_soft_delete', 0);
            $query->where('v.video_type', $video_type->id);
            $query->where('v.front_user_id', $user->id);
            $query->where(function ($q) {
                $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
            });
            $query1 = clone $query;
            $query2 = clone $query;
            $totalData = $query1->select(['v.id'])->count();
            $query2->orderBy('v.system_id', 'DESC');
            $videos = $query2->select(['v.*', 'u.name as user_name', 'u.image as user_image'])->get();
            $video_types[$key]->videos = $videos;
        }

        $followers = DB::table('followers')->where('following_id', $user->id)->count(); // Count of followers
        $following = DB::table('followers')->where('follower_id', $user->id)->count(); // Count of following

        return response()->json([
            'status' => true,
            'user' => $user,
            'additional_data' => $additional_data,
            'video_types' => $video_types,
            'followers' => $followers,
            'following' => $following,
        ], 200);
    }

    // Videos
    public function video_settings(){
        $language = App::getLocale();
        $video_types = AppHelper::get_key_values(2);
        $countries = DB::table('countries')->where('status', 1)->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        return response()->json([
            'status' => true,
            'video_types' => $video_types,
            'countries' => $countries,
        ], 200);
    }
    public function create_video(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'video_type' => 'required',
            // 'description' => 'required',
            // 'menu' => 'required',
            'publish_type' => 'required',
            'country' => 'required',
            'city' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if content has bad words, if has then throw error
        $title = $request->title;
        $description = $request->description;
        $tags = $request->tags;

        $filter = new ProfanityFilterService();

        if((!empty($title) && $filter->hasProfanity($title)) || (!empty($description) && $filter->hasProfanity($description))){
            return response()->json([
                'status' => false,
                'message' => __('messages.inappropriate_content_detected')
            ], 422);
        }

        if(!empty($tags)){
            $tagList = array_map('trim', explode(',', $tags));
            foreach($tagList as $tag){
                if($filter->hasProfanity($tag)){
                    return response()->json([
                        'status' => false,
                        'message' => __('messages.inappropriate_content_detected')
                    ], 422);
                }
            }
        }

        $user = Auth::user();
        $language = App::getLocale();

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }
        else{
            $e_select = ['id', 'name', 'sort_order', 'subscription_required', 'is_sponsored', 'status', 'created_at', 'updated_at'];
        }

        $user_entity_details = DB::table('entities')->select($e_select)->where('id', $user->entity)->first();
        $input = $request->all();

        if($user_entity_details->subscription_required == 1){
            $isExpired = AppHelper::check_subscription_expired($user->id);
            if($isExpired){
                return response()->json([
                    'status' => false,
                    'message' => __('messages.subscription_expired_msg')
                ], 422);
            }
        }

        $data = array();
        $data['id'] = (string) \Str::uuid();
        $data['front_user_id'] = $user->id;
        $data['title'] = $request->title;
        $data['video_type'] = $request->video_type;
        $data['description'] = $request->description;
        $data['tags'] = $request->tags;
        if(isset($request->menu)){
            $data['menu'] = $request->menu;
        }
        $data['publish_type'] = $request->publish_type;
        $data['allow_comments'] = $request->allow_comments;
        $data['take_order'] = $request->take_order;
        $data['country'] = $request->country;
        $data['city'] = $request->city;
        if(isset($request->is_image) && $request->is_image==1){
            $data['is_image'] = $request->is_image;
        }
        // $data['location'] = $request->location;
        if ($request->file('image')) {
            $image = $request->file('image');

            // Create unique name
            $imageName = time() . rand(1000, 9999) . '.' . $image->getClientOriginalExtension();

            // Initialize S3 service

            // Upload original image to S3
            $this->s3Service->storeFile('videos/' . $imageName, file_get_contents($image));

            // Generate thumbnail locally
            $thumbnailLocalPath = storage_path('app/temp-thumbnails');
            if (!file_exists($thumbnailLocalPath)) {
                mkdir($thumbnailLocalPath, 0755, true);
            }

            $thumbnailFullLocalPath = $thumbnailLocalPath . '/' . $imageName;

            $img = Image::read($image->getRealPath());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($thumbnailFullLocalPath);

            // Upload thumbnail to S3
            $this->s3Service->storeFile('videos/thumbnail/' . $imageName, file_get_contents($thumbnailFullLocalPath));

            // Delete local thumbnail
            if (file_exists($thumbnailFullLocalPath)) {
                unlink($thumbnailFullLocalPath);
            }

            // Save only the name or optionally full S3 URLs
            $data['image'] = $imageName;

            // Optional: Save full URL
            // $data['image_url'] = Storage::disk('s3')->url('videos/' . $imageName);
            // $data['thumbnail_url'] = Storage::disk('s3')->url('videos/thumbnail/' . $imageName);
        }
        if ($request->file('video')) {
            $video = $request->file('video');
            $video_name = time() . rand(1000, 9999) . '1.' . $video->extension();

            // Get the video content
            $contents = file_get_contents($video->getRealPath());

            // Initialize S3 service
            $s3Service = app(S3Service::class);

            // Upload the video to S3
            $uploaded = $s3Service->storeFile('videos/' . $video_name, $contents);

            if ($uploaded) {
                $data['video'] = $video_name;  // Save the filename or S3 path if you need
            } else {

            }
        }

        if($user_entity_details->is_sponsored == 1){
            $data['is_sponsored'] = 1;
        }
        
        DB::table('videos')->insert($data);

        if($request->tags){
            AppHelper::run_add_tag_script($request->tags);
        }

        if($user_entity_details->is_sponsored == 1){
            $video_id = $data['id'];

            $payment_data = array();
            $payment_data['PaymentId'] = $request->PaymentId;
            $payment_data['TranId'] = $request->TranId;
            $payment_data['ECI'] = $request->ECI;
            $payment_data['TrackId'] = $request->TrackId;
            $payment_data['RRN'] = $request->RRN;
            $payment_data['cardBrand'] = $request->cardBrand;
            $payment_data['maskedPAN'] = $request->maskedPAN;
            $payment_data['PaymentType'] = $request->PaymentType;

            AppHelper::add_user_sponsor_video($video_id, $request->cities, $request->sponsor_type, $request->days, $payment_data);
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.video_create_success'),
        ], 201);
    }
    public function edit_video(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'title' => 'required',
            'video_type' => 'required',
            'publish_type' => 'required',
            'country' => 'required',
            'city' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if content has bad words, if has then throw error
        $title = $request->title;
        $description = $request->description;
        $tags = $request->tags;

        $filter = new ProfanityFilterService();
        
        if((!empty($title) && $filter->hasProfanity($title)) || (!empty($description) && $filter->hasProfanity($description))){
            return response()->json([
                'status' => false,
                'message' => __('messages.inappropriate_content_detected')
            ], 422);
        }

        if(!empty($tags)){
            $tagList = array_map('trim', explode(',', $tags));
            foreach($tagList as $tag){
                if($filter->hasProfanity($tag)){
                    return response()->json([
                        'status' => false,
                        'message' => __('messages.inappropriate_content_detected')
                    ], 422);
                }
            }
        }

        $data = array();
        $data['title'] = $request->title;
        $data['video_type'] = $request->video_type;
        $data['description'] = $request->description;
        $data['tags'] = $request->tags;
        if(isset($request->menu)){
            $data['menu'] = $request->menu;
        }
        $data['publish_type'] = $request->publish_type;
        $data['allow_comments'] = $request->allow_comments;
        $data['take_order'] = $request->take_order;
        $data['country'] = $request->country;
        $data['city'] = $request->city;
        DB::table('videos')->where('id',$request->video_id)->update($data);

        if($request->tags){
            AppHelper::run_add_tag_script($request->tags);
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.video_edit_success'),
        ], 201);
    }
    public function delete_video(Request $request){
        $user = Auth::user();
        $input = $request->all();
        $video = DB::table('videos')->where('id', $request->id)->first();
        if (!$video) {
            return response()->json([
                'status' => false,
                'message' => __('messages.video_not_found'),
            ], 404);
        }

        /* Soft Delete */
        $data = array();
        $data['is_soft_delete'] = 1;
        DB::table('videos')->where('id', $request->id)->update($data);

        DB::table('user_saved_videos')->where('video_id', $request->id)->delete();
        /* Soft Delete */

        // $videoReportIds = DB::table('video_reports')
        // ->where('video_id', $request->id)
        // ->pluck('id');

        // DB::table('notifications')
        // ->whereIn('video_report_id', $videoReportIds)
        // ->delete();

        // DB::table('video_reports')->where('video_id', $request->id)->delete();

        // $s3 = app(S3Service::class);

        // if (!empty($video->video)) {
        //     $s3->deleteFile('videos/' . $video->video);
        // }

        // if (!empty($video->image)) {
        //     $s3->deleteFile('videos/' . $video->image); // original image
        //     $s3->deleteFile('videos/thumbnail/' . $video->image); // thumbnail
        // }
        // DB::table('videos')->where('id', $request->id)->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.video_delete_success'),
        ], 201);
    }
    public function videos_list(Request $request){
        $user = Auth::guard('sanctum')->user();
        $input = $request->all();
        $page = $input['page'] ?? 1; // Default to page 1 if not provided
        $length = 100000; // Number of records per page
        $start = ($page - 1) * $length;
        $cities_ids = array();
        $country = 0;
        $city = 0;

        if(isset($input['country']) && $input['country']!=''){
            $country_details = DB::table('countries')->whereRaw('LOWER(name) = ?', [strtolower($input['country'])])->first();
            if(isset($country_details->id)){
                $country = $country_details->id;
            }
        }
        if(isset($input['city']) && $input['city']!=''){
            $city_details = DB::table('cities')->where('country_id', $country)->whereRaw('LOWER(name) = ?', [strtolower($input['city'])])->first();
            if(isset($city_details->id)){
                $city = $city_details->id;
            }
        }
        if($city){
            $city_group = DB::table('cities_groups')->whereRaw('FIND_IN_SET(?, cities)', [$city])->first();
            if(!empty($city_group)){
                $cities_ids = explode(',', $city_group->cities);
            }
            else{
                $cities_ids = array($city);
            }
        }

        // Base query to clone for all types
        $baseQuery = DB::table('videos as v')
        ->join('front_users as u', 'u.id', '=', 'v.front_user_id')
        ->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id')
        ->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')
        ->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id')
        ->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id')
        ->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id')
        ->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id')
        ->where(function ($q) {
            $q->where('video_type_language.is_default', 1)
              ->orWhere('v.video_type', 0);
        })
        ->where('v.status', 1)
        ->where('v.is_soft_delete', 0);

        if (isset($input['search']['value']) && $input['search']['value'] != '') {
            $baseQuery->where('v.title', 'LIKE', '%' . $input['search']['value'] . '%');
        }
        if (isset($input['user']) && $input['user'] != '') {
            $baseQuery->where('v.front_user_id', $input['user']);
        }
        if (isset($input['video_type']) && $input['video_type'] != '') {
            $baseQuery->where('v.video_type', $input['video_type']);
        }
        if (isset($input['title']) && $input['title'] != '') {
            $baseQuery->where('v.title', 'LIKE', '%' . $input['title'] . '%');
        }
        if (isset($input['tags']) && $input['tags'] != '') {
            $baseQuery->where('v.tags', 'LIKE', '%' . $input['tags'] . '%');
        }
        $baseQuery->where(function ($query) {
            $query->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
        });

        // Exclude those videos which are from blocked user
        if($user){
            $blocked_users = DB::table('blocked_users')
                                    ->where('blocked_by', $user->id)
                                    ->pluck('blocked_user');
            if($blocked_users){
                $baseQuery->whereNotIn('v.front_user_id', $blocked_users);
            }
        }
            
        // NORMAL VIDEOS
        $normalQuery = clone $baseQuery;

        // is_following = 1, this bit is used to get only following videos

        if(isset($input['is_following']) && $input['is_following'] == 1){
            // do nothing
        }
        else{
            if($country){
                $normalQuery->where('v.country', $country);
            }
            if(!empty($cities_ids)){
                $normalQuery->whereIn('v.city', $cities_ids);
            }
        }

        if($user){
            $follower_id = $user->id;
            $followingIds = DB::table('followers')
                                    ->where('follower_id', $follower_id)
                                    ->pluck('following_id');

            if(isset($input['is_following']) && $input['is_following'] == 1){
                $normalQuery->where(function ($q) use ($followingIds) {
                    $q->where('v.publish_type', 2)
                        ->orWhere('v.publish_type', 1);
                });
                $normalQuery->whereIn('v.front_user_id', $followingIds);
            }
            else{
                $normalQuery->where(function ($q) use ($followingIds) {
                    $q->where('v.publish_type', 2)
                        ->orWhere(function ($iq) use ($followingIds) {
                            $iq->where('v.publish_type', 1)
                                ->whereIn('v.front_user_id', $followingIds);
                        });
                });
            }
        }
        else{
            $normalQuery->where('v.publish_type', 2);
        }
        
        $normalQuery->leftJoin('sponsored_videos as sv', function ($join) use ($cities_ids, $input) {
            $join->on('sv.video_id', '=', 'v.id');

            // Add OR conditions using FIND_IN_SET
            if(isset($input['is_following']) && $input['is_following'] == 1){
                // do nothing
            }
            else{
                $join->where(function ($query) use ($cities_ids) {
                    foreach ($cities_ids as $cityId) {
                        $query->orWhereRaw('FIND_IN_SET(?, sv.cities)', [$cityId]);
                    }
                });
            }
        });

        $normalQuery->where(function ($q) {
            $q->where('v.is_sponsored', 0)
                ->whereNull('sv.video_id');
        });

        $normalVideos = $normalQuery->inRandomOrder()->select([
            'v.*', 'sv.sponsor_type',
            'video_type_description.name as video_type_name',
            'u.name as user_name',
            'u.email as user_email',
            'u.image as user_image',
            'ba.contact_phone',
            'ba.contact_email',
            'ba.website',
            'ba.location',
            'ba.latitude',
            'ba.longitude',
            DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
            DB::raw('COALESCE(following.following_count, 0) as following_count')
        ])->get();

        // SPONSORED VIDEOS
        $sponsoredQuery = clone $baseQuery;
        $sponsoredQuery->join('sponsored_videos as sv', 'sv.video_id', '=', 'v.id')
        ->where(function ($query) use ($cities_ids) {
            foreach ($cities_ids as $cityId) {
                $query->orWhereRaw('FIND_IN_SET(?, sv.cities)', [$cityId]);
            }
        })
        ->where('sv.sponsor_type', 1);

        $sponsoredVideos = $sponsoredQuery->inRandomOrder()->select([
            'v.*', 'sv.sponsor_type',
            'video_type_description.name as video_type_name',
            'u.name as user_name',
            'u.email as user_email',
            'u.image as user_image',
            'ba.contact_phone',
            'ba.contact_email',
            'ba.website',
            'ba.location',
            'ba.latitude',
            'ba.longitude',
            DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
            DB::raw('COALESCE(following.following_count, 0) as following_count')
        ])->get();

        // PREMIUM SPONSORED VIDEOS
        $premiumQuery = clone $baseQuery;
        $premiumQuery->join('sponsored_videos as sv', 'sv.video_id', '=', 'v.id')
            ->where(function ($query) use ($cities_ids) {
            foreach ($cities_ids as $cityId) {
                $query->orWhereRaw('FIND_IN_SET(?, sv.cities)', [$cityId]);
            }
        })
        ->where('sv.sponsor_type', 2);

        $premiumSponsoredVideos = $premiumQuery->inRandomOrder()->select([
            'v.*', 'sv.sponsor_type',
            'video_type_description.name as video_type_name',
            'u.name as user_name',
            'u.email as user_email',
            'u.image as user_image',
            'ba.contact_phone',
            'ba.contact_email',
            'ba.website',
            'ba.location',
            'ba.latitude',
            'ba.longitude',
            DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
            DB::raw('COALESCE(following.following_count, 0) as following_count')
        ])->get();

        // MERGE VIDEO LOGIC
        $finalList = [];
        $normalCount = 0;
        $sponsoredIndex = 0;
        $premiumIndex = 0;
        $sponsoredCount = 0;
        $totalNormal = count($normalVideos);
        $i = 0;
        $normal_videos_cc = 5; // This counter is used to show the sponor video after xyz number of normal videos

        while ($i < $totalNormal) {
            for ($j = 0; $j < $normal_videos_cc && $i < $totalNormal; $j++, $i++) {
                $finalList[] = $normalVideos[$i];
            }

            if (isset($sponsoredVideos[$sponsoredIndex])) {
                $finalList[] = $sponsoredVideos[$sponsoredIndex++];
                $sponsoredCount++;

                if ($sponsoredCount % 3 == 0 && isset($premiumSponsoredVideos[$premiumIndex])) {
                    $finalList[] = $premiumSponsoredVideos[$premiumIndex++];
                }
            }
        }

        while (isset($sponsoredVideos[$sponsoredIndex])) {
            $finalList[] = $sponsoredVideos[$sponsoredIndex++];
        }

        while (isset($premiumSponsoredVideos[$premiumIndex])) {
            $finalList[] = $premiumSponsoredVideos[$premiumIndex++];
        }
        
        return response()->json([
            'status' => true,
            'videos' => $finalList,
        ], 200);
    }
    public function videos_list2(Request $request){
        $input = $request->all();
        $page = $input['page'] ?? 1; // Default to page 1 if not provided
        $length = 100000; // Number of records per page
        $start = ($page - 1) * $length;
        
        $query=DB::table('videos as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
        $query->where(function ($q){
            $q->where('video_type_language.is_default', 1)
              ->orWhere('v.video_type', 0); 
        });
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where('v.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if(isset($input['user']) && $input['user']!=''){
            $query->where('v.front_user_id', $input['user']);
        }
        if(isset($input['video_type']) && $input['video_type']!=''){
            $query->where('v.video_type', $input['video_type']);
        }
        if(isset($input['title']) && $input['title']!=''){
            $query->where('v.title', 'LIKE', '%'.$input['title'].'%');
        }
        $query->where('v.status', 1);
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select(['v.id'])->count();
        $query2->offset($start)->limit($length);
        // $query2->orderBy('v.system_id', 'DESC');
        $videos = $query2->select(['v.video'])->inRandomOrder()->get();
        
        return response()->json([
            'status' => true,
            'videos' => $videos,
        ], 200);
    }
    public function video_details(Request $request){
        $input = $request->all();

        $query=DB::table('videos as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
        $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
        $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
        $query->where(function ($q){
            $q->where('video_type_language.is_default', 1)
              ->orWhere('v.video_type', 0); 
        });
        $query->where('v.status', 1);
        $query->where('v.id', $request->id);
        $video = $query->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.image as user_image', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
        DB::raw('COALESCE(following.following_count, 0) as following_count')])->first();
        
        return response()->json([
            'status' => true,
            'video' => $video,
        ], 200);
    }
    public function contact_for_order(Request $request){
        $data=array();
        $site_settings = AppHelper::get_site_settings();
        $allrequestdata=$request->all();

        $query=DB::table('videos as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->leftJoin('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id');
        $query->where('v.id', $request->video_id);
        $video_details = $query->select(['v.*', 'u.name as user_name', 'u.image as user_image', 'ba.contact_phone', 'ba.contact_email', 'ba.website', 'ba.location', 'ba.latitude', 'ba.longitude'])->first();
        
        $email_to = $site_settings->email;
        $html = view('email_templates.front_user.order_request', compact('allrequestdata', 'video_details'))->render();
        $subject="New Order Request";
        AppHelper::send_email($allrequestdata['email'], $video_details->contact_phone, $subject, $html);
        return response()->json([
            'status' => true,
            'message' => __('messages.order_request_success'),
        ], 200);
    }
    public function report_categories(Request $request){
        $user = Auth::user();
        $input = $request->all();
        $language = App::getLocale();

        $query=DB::table('categories');
        $query->join('categories_description', 'categories_description.category_id', '=', 'categories.id');
        $query->join('site_languages', 'categories_description.language_id', '=', 'site_languages.id');
        if($language){
            $query->where('site_languages.code', $language);
        }
        else{
            $query->where('site_languages.is_default', 1);
        }
        $query->where('categories.status', 1);
        $query->where('categories.type', $request->type);
        $query->orderBy('categories.system_id', 'ASC');
        $categories = $query->select(['categories.*', 'categories_description.name'])->get();
        
        return response()->json([
            'status' => true,
            'categories' => $categories,
        ], 200);
    }
    public function add_video_report(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'category_id' => 'required',
            'comments' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();
        $input = $request->all();

        $data = array();
        $data['id'] = (string) \Str::uuid();
        $data['reported_by'] = $user->id;
        $data['video_id'] = $request->video_id;
        $data['category_id'] = $request->category_id;
        $data['comments'] = $request->comments;
        DB::table('video_reports')->insert($data);

        /* Create Notification Start */
        $notification_data = array();
        $notification_data['to_type'] = 1;
        $notification_data['type'] = 1;
        $notification_data['video_report_id'] = $data['id'];
        DB::table('notifications')->insert($notification_data);
        /* Create Notification End */

        $query=DB::table('video_reports as r');
        $query->join('front_users as ru', 'ru.id', '=', 'r.reported_by');
        $query->leftJoin('categories_description as category_description', 'category_description.category_id', '=', 'r.category_id')->leftJoin('site_languages as category_language', 'category_description.language_id', '=', 'category_language.id');
        $query->where(function ($q){
            $q->where('category_language.is_default', 1)
              ->orWhere('r.category_id', null); 
        });
        $query->where('r.id', $data['id']);
        $report = $query->select(['r.*', 'ru.name as reported_by_name', 'ru.email as reported_by_email', 'ru.image as user_image', 'category_description.name as category_name'])->first();

        /* Email Notification Start */
        $site_settings = AppHelper::get_site_settings();
        $email_to = $site_settings->email;
        $html = view('email_templates.admin.video_reported', compact('report'))->render();
        $subject="Video Reported";
        AppHelper::send_email($report->reported_by_email, $email_to, $subject, $html);
        /* Email Notification End */

        return response()->json([
            'status' => true,
            'message' => __('messages.video_report_create_success'),
        ], 201);
    }
    

    // Video Comments
    public function create_video_comment(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'comment' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();
        $input = $request->all();

        $data = array();
        $data['id'] = (string) \Str::uuid();
        $data['front_user_id'] = $user->id;
        $data['video_id'] = $request->video_id;
        if($request->parent_id){
            $data['parent_id'] = $request->parent_id;
        }
        $data['comment'] = $request->comment;
        DB::table('video_comments')->insert($data);
        return response()->json([
            'status' => true,
            'message' => __('messages.video_comment_create_success'),
        ], 201);
    }

    public function video_save_unsave(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();
        $input = $request->all();
        $validate_saved_video = DB::table('user_saved_videos')->where('front_user_id', $user->id)->where('video_id', $input['video_id'])->first();
        if(empty($validate_saved_video)){
            $data = array();
            $data['id'] = (string) \Str::uuid();
            $data['front_user_id'] = $user->id;
            $data['video_id'] = $input['video_id'];
            DB::table('user_saved_videos')->insert($data);
            $mesage = __('messages.video_saved_success');
        }
        else{
            DB::table('user_saved_videos')->where('front_user_id', $user->id)->where('video_id', $input['video_id'])->delete();
            $mesage = __('messages.video_unsaved_success');
        }
        return response()->json([
            'status' => true,
            'message' => $mesage,
        ], 201);
    }
    public function update_video_average_rating(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'average_rating' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }
        $user = Auth::user();
        $input = $request->all();
        $up_data=array();
        $up_data['average_rating'] = $request->average_rating;
        DB::table('videos')->where('id',$request->video_id)->update($up_data);
        $mesage = __('messages.rating_updated_success');
        return response()->json([
            'status' => true,
            'message' => $mesage,
        ], 201);
    }
    public function add_video_sponsor(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required',
            'cities' => 'required',
            'sponsor_type' => 'required',
            'days' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('messages.validation_failed'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment_data = array();
        $payment_data['PaymentId'] = $request->PaymentId;
        $payment_data['TranId'] = $request->TranId;
        $payment_data['ECI'] = $request->ECI;
        $payment_data['TrackId'] = $request->TrackId;
        $payment_data['RRN'] = $request->RRN;
        $payment_data['cardBrand'] = $request->cardBrand;
        $payment_data['maskedPAN'] = $request->maskedPAN;
        $payment_data['PaymentType'] = $request->PaymentType;

        $res = AppHelper::add_user_sponsor_video($request->video_id, $request->cities, $request->sponsor_type, $request->days, $payment_data);
        if($res){
            return response()->json([
                'status' => true,
                'message' => __('messages.sponsor_add_success')
            ], 201);
        }
        else{
            return response()->json([
                'status' => false,
                'message' => __('messages.subscription_expired_msg')
            ], 422);
        }
    }
    public function saved_videos_list(Request $request){
        $user = Auth::user();
        $input = $request->all();
        $page = $input['page'] ?? 1; // Default to page 1 if not provided
        $length = 100000; // Number of records per page
        $start = ($page - 1) * $length;

        $follower_id = $user->id;
        $followingIds = DB::table('followers')
        ->where('follower_id', $follower_id)
        ->pluck('following_id');
        
        $query=DB::table('videos as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->join('user_saved_videos as sv', 'sv.video_id', '=', 'v.id');
        $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id');
        $query->leftJoin(DB::raw('(SELECT following_id, COUNT(follower_id) as followers_count FROM followers GROUP BY following_id) as followers'), 'followers.following_id', '=', 'u.id');
        $query->leftJoin(DB::raw('(SELECT follower_id, COUNT(following_id) as following_count FROM followers GROUP BY follower_id) as following'), 'following.follower_id', '=', 'u.id');
        $query->leftJoin('subscription_history as sh', 'sh.id', '=', 'u.current_subscription_id');
        $query->where(function ($q){
            $q->where('video_type_language.is_default', 1)
              ->orWhere('v.video_type', 0); 
        });
        $query->where(function ($q) {
            $q->whereDate('sh.end_date', '>=', now()->toDateString())->orWhereNull('sh.end_date');
        });
        
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where('v.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if(isset($input['user']) && $input['user']!=''){
            $query->where('v.front_user_id', $input['user']);
        }
        if(isset($input['video_type']) && $input['video_type']!=''){
            $query->where('v.video_type', $input['video_type']);
        }
        if(isset($input['title']) && $input['title']!=''){
            $query->where('v.title', 'LIKE', '%'.$input['title'].'%');
        }
        $query->where('v.status', 1);
        $query->where('sv.front_user_id', $user->id);
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select(['v.id'])->count();
        $query2->offset($start)->limit($length);
        // $query2->orderBy('v.system_id', 'DESC');
        $videos = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'u.image as user_image', DB::raw('COALESCE(followers.followers_count, 0) as followers_count'),
        DB::raw('COALESCE(following.following_count, 0) as following_count')])->inRandomOrder()->get();
        
        return response()->json([
            'status' => true,
            'videos' => $videos,
        ], 200);
    }

    public function nearest_business_accounts(Request $request){
        $input = $request->all();
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->radius; // in kilometers

        $query = DB::table('front_users as u')
        ->join('business_account_additional_data as ba', 'ba.front_user_id', '=', 'u.id')
        ->select('u.id', 'u.name', 'u.email', 'u.phone', 'u.image', 'ba.location', 'ba.latitude', 'ba.longitude', 'ba.contact_phone', 'ba.contact_email', DB::raw("
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(ba.latitude)) *
                cos(radians(ba.longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(ba.latitude))
            )) AS distance
        "))
        ->where('u.status', 1)
        ->where('u.entity', 2)
        ->having('distance', '<=', $radius)
        ->orderBy('distance');

        // Now add the bindings using mergeBindings and pass them to the query
        $results = $query->addBinding([$latitude, $longitude, $latitude], 'select')->get();
        return response()->json([
            'status' => true,
            'accounts' => $results,
        ], 200);
    }
    public function subscribe_package(Request $request){
        $language = App::getLocale();
        $user = Auth::user();
        $input = $request->all();

        $payment_data = array();
        $payment_data['PaymentId'] = $request->PaymentId;
        $payment_data['TranId'] = $request->TranId;
        $payment_data['ECI'] = $request->ECI;
        $payment_data['TrackId'] = $request->TrackId;
        $payment_data['RRN'] = $request->RRN;
        $payment_data['cardBrand'] = $request->cardBrand;
        $payment_data['maskedPAN'] = $request->maskedPAN;
        $payment_data['PaymentType'] = $request->PaymentType;

        AppHelper::subscribe_user_to_package($user->id, $input['package_id'], $payment_data);

        return response()->json([
            'status' => true,
            'message' => __('messages.subscribe_success')
        ]);
    }

    // Audios
    public function audios_list(Request $request){
        $input=$request->all();
        $language = App::getLocale();
        $perPage = 20;

        $query=DB::table('audios');
        $query->join('audios_description', 'audios_description.audio_id', '=', 'audios.id');
        $query->join('site_languages', 'audios_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('audios.status', 1);
        if(isset($input['search']) && $input['search']!=''){
            $query->where(function ($q) use ($input) {
                $searchValue = '%' . $input['search'] . '%';
                $q->where('audios_description.title', 'LIKE', $searchValue)
                  ->orWhere('audios_description.artist', 'LIKE', $searchValue);
            });
        }
        $query->orderBy('audios_description.title', 'ASC');
        $audios = $query->select(['audios.*', 'audios_description.title', 'audios_description.artist'])->paginate($perPage);

        return response()->json([
            'status' => true,
            'audios' => $audios,
        ], 200);
    }
    

    // General
    public function packages_list(){
        $language = App::getLocale();
        $query=DB::table('packages');
        $query->join('packages_description', 'packages_description.package_id', '=', 'packages.id');
        $query->join('site_languages', 'packages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('packages.status', 1);
        $query->orderBy('packages.system_id', 'ASC');
        $packages = $query->select(['packages.*', 'packages_description.title', 'packages_description.description'])->get();

        return response()->json([
            'status' => true,
            'packages' => $packages,
        ], 200);
    }
    public function entities(){
        $language = App::getLocale();

        if($language == 'ar'){
            $e_select = ['id', 'name_ar as name', 'subscription_required', 'is_sponsored'];
        }
        else{
            $e_select = ['id', 'name', 'subscription_required', 'is_sponsored'];
        }

        $entities = DB::table('entities')->where('status', 1)->select($e_select)->orderBy('sort_order', 'ASC')->get();
        return response()->json([
            'status' => true,
            'entities' => $entities,
        ], 200);
    }
    public function site_languages(){
        $site_languages = DB::table('site_languages')->where('status', 1)->select(['id', 'name', 'code', 'image', 'direction', 'is_default'])->orderBy('sort_order', 'ASC')->get();
        return response()->json([
            'status' => true,
            'site_languages' => $site_languages,
        ], 200);
    }
    public function countries(Request $request){
        $query = DB::table('countries');
        if($request->input('id')){
            $query->where('id', $request->input('id'));
        }
        $query->where('status', 1);
        $countries = $query->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        return response()->json([
            'status' => true,
            'countries' => $countries,
        ], 200);
    }
    public function states(Request $request){
        $query = DB::table('states');
        if($request->input('country_id')){
            $query->where('country_id', $request->input('country_id'));
        }
        $states = $query->select(['id', 'name', 'country_id'])->get();
        return response()->json([
            'status' => true,
            'states' => $states,
        ], 200);
    }
    public function cities(Request $request){
        $query = DB::table('cities');
        if($request->input('state_id')){
            $query->where('state_id', $request->input('state_id'));
        }
        if($request->input('country_id')){
            $query->where('country_id', $request->input('country_id'));
        }
        $cities = $query->select(['id', 'name', 'state_id'])->get();
        return response()->json([
            'status' => true,
            'cities' => $cities,
        ], 200);
    }
    public function generic_key_value(Request $request){
        $data = AppHelper::get_key_values($request->input('key_id'));
        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }
    public function site_settings(){
        $settings = DB::table('settings')->where('id', 1)->select(['email', 'phone', 'address', 'facebook', 'twitter', 'instagram', 'linkedin', 'basic_sponsored_video_price', 'premium_sponsored_video_price', 'sponsor_video_discount', 'allow_general_videos', 'currency_symbol', 'allow_following_videos'])->first();
        return response()->json([
            'status' => true,
            'settings' => $settings,
        ], 200);
    }
}

