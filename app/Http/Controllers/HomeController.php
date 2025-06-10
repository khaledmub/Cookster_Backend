<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \App\Helpers\AppHelper;
use App;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(){
        $data = array();
        // echo App::getLocale();
        // exit;
        // if(App::getLocale()=='ar'){}
        $language = App::getLocale();

        $query = DB::table('banners');
        $query->join('banners_description', 'banners_description.banner_id', '=', 'banners.id');
        $query->join('site_languages', 'banners_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->orderBy('banners.id', 'ASC');
        $data['banners'] = $query->select(['banners.*', 'banners_description.title', 'banners_description.sub_title', 'banners_description.short_description'])->get();

        return view('frontend.home',compact('data'));
    }
    public function about_us(){
        $data = array();
        $language = App::getLocale();

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 1);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description'])->first();

        return view('frontend.about_us',compact('data'));
    }
    public function privacy_policy(){
        $data = array();
        $language = App::getLocale();

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 2);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description'])->first();
        return view('frontend.privacy_policy',compact('data'));
    }
    public function terms_of_use(){
        $data = array();
        $language = App::getLocale();

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 3);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description'])->first();
        return view('frontend.terms_of_use',compact('data'));
    }
    public function contact_us(){
        $data = array();
        $language = App::getLocale();
        return view('frontend.contact_us',compact('data'));
    }
    public function submit_contact_us(Request $request){
        $data=array();
        $site_settings = AppHelper::get_site_settings();
        $allrequestdata=$request->all();
        
        $email_to = $site_settings->email;
        $html = view('email_templates.admin.contactus', compact('allrequestdata'))->render();
        $subject="Contact Us";
        // DB::table('leads')->insert([
        //     'name' => $request->name,
        //     'email' => $request->email,
        //     'phone' => $request->phone,
        //     'message' => $request->message,
        // ]);
        AppHelper::send_email($allrequestdata['email'], $email_to, $subject, $html);
        return response()->json(['status'=>true, 'message'=>trans('general.contactus_success')]);
    }
}
