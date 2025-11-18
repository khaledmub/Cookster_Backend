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

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 5);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();

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
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();

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
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();
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
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();
        return view('frontend.terms_of_use',compact('data'));
    }
    public function contact_us(){
        $data = array();
        $language = App::getLocale();

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 4);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();

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
    public function blog($category = null){
        $data = array();
        $language = App::getLocale();

        $query = DB::table('pages');
        $query->join('pages_description', 'pages_description.page_id', '=', 'pages.id');
        $query->join('site_languages', 'pages_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $query->where('pages.id', 6);
        $data['page'] = $query->select(['pages.*', 'pages_description.title', 'pages_description.sub_title', 'pages_description.short_description', 'pages_description.description', 'pages_description.meta_title', 'pages_description.meta_description', 'pages_description.meta_keywords'])->first();

        $query = DB::table('blogcategories');
        $query->join('blogcategories_description', 'blogcategories_description.blogcategory_id', '=', 'blogcategories.id');
        $query->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.code', $language);
        $data['blogcategories'] = $query->select(['blogcategories.*', 'blogcategories_description.title'])->orderBy('blogcategories_description.title', 'ASC')->get();

        $query = DB::table('blogs');
        $query->join('blogs_description', 'blogs_description.blog_id', '=', 'blogs.id');
        $query->join('site_languages', 'blogs_description.language_id', '=', 'site_languages.id');
        $query->join('blogcategories', 'blogs.blogcategory_id', '=', 'blogcategories.id');
        $query->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id');
        $query->join('site_languages as category_language', 'blogcategories_description.language_id', '=', 'category_language.id');
        $query->where('site_languages.code', $language);
        $query->where('category_language.code', $language);

        $data['category_details'] = [];
        if($category){
            $data['category_details'] = DB::table('blogcategories')
                ->join('blogcategories_description', 'blogcategories_description.blogcategory_id', '=', 'blogcategories.id')
                ->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id')
                ->where('site_languages.code', $language)
                ->whereRaw('LOWER(REPLACE(blogcategories_description.title, " ", "-")) = ?', [strtolower($category)])
                ->select('blogcategories.*', 'blogcategories_description.title', 'blogcategories_description.meta_title', 'blogcategories_description.meta_description', 'blogcategories_description.meta_keywords')
                ->first();

            if($data['category_details']){
                $query->where('blogs.blogcategory_id', $data['category_details']->id);
            }
        }

        $data['blogs'] = $query->select(['blogs.*', 'blogs_description.title', 'blogs_description.short_description', 'blogcategories_description.title as category_title'])->orderBy('blogs.date', 'DESC')->paginate(6);

        return view('frontend.blog',compact('data'));
    }
    public function blog_post($category, $post){
        $data = array();
        $language = App::getLocale();
        $data['blog_details'] = [];
        $data['related_blogs'] = [];

        if($post){
            $data['blog_details'] = DB::table('blogs')
                ->join('blogs_description', 'blogs_description.blog_id', '=', 'blogs.id')
                ->join('site_languages', 'blogs_description.language_id', '=', 'site_languages.id')
                ->join('blogcategories', 'blogs.blogcategory_id', '=', 'blogcategories.id')
                ->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id')
                ->join('site_languages as category_language', 'blogcategories_description.language_id', '=', 'category_language.id')
                ->where('site_languages.code', $language)
                ->where('category_language.code', $language)
                // ->whereRaw('LOWER(REPLACE(blogs_description.title, " ", "-")) = ?', [strtolower($post)])
                ->whereRaw('LOWER(REPLACE(blogs.custom_url, " ", "-")) = ?', [strtolower($post)])
                ->select('blogs.*', 'blogs_description.title', 'blogs_description.short_description', 'blogs_description.description', 'blogs_description.meta_title', 'blogs_description.meta_description', 'blogs_description.meta_keywords', 'blogcategories_description.title as category_title')
                ->first();

            if($data['blog_details']){
                $query = DB::table('blogs');
                $query->join('blogs_description', 'blogs_description.blog_id', '=', 'blogs.id');
                $query->join('site_languages', 'blogs_description.language_id', '=', 'site_languages.id');
                $query->join('blogcategories', 'blogs.blogcategory_id', '=', 'blogcategories.id');
                $query->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id');
                $query->join('site_languages as category_language', 'blogcategories_description.language_id', '=', 'category_language.id');
                $query->where('site_languages.code', $language);
                $query->where('category_language.code', $language);
                $query->where('blogs.id', '!=', $data['blog_details']->id);

                $data['related_blogs'] = $query->select(['blogs.*', 'blogs_description.title', 'blogs_description.short_description', 'blogcategories_description.title as category_title'])->inRandomOrder()->limit(3)->get();
            }
        }

        return view('frontend.blog_details',compact('data'));
    }
}
