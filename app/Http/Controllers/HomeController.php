<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use \App\Helpers\AppHelper;
use App;

class HomeController extends Controller
{
    private string $androidPackageName = 'com.cookster.cooksterapp';
    private string $androidSha256 = '29:EA:D4:28:4B:EB:5A:11:DC:F7:F9:C0:25:CC:F5:36:63:72:4C:C3:85:7D:6A:5A:9E:EB:04:E5:29:2D:80:FF';

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
    public function assetlinks()
    {
        $payload = [[
            'relation' => [
                'delegate_permission/common.handle_all_urls',
                'delegate_permission/common.get_login_creds',
            ],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => env('ANDROID_APP_PACKAGE', $this->androidPackageName),
                'sha256_cert_fingerprints' => [
                    env('ANDROID_APP_SHA256', $this->androidSha256),
                ],
            ],
        ]];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
    public function appleAppSiteAssociation()
    {
        $teamId = trim((string) env('IOS_TEAM_ID', ''));
        $bundleId = trim((string) env('IOS_APP_BUNDLE_ID', ''));
        $details = [];

        if ($teamId !== '' && $bundleId !== '') {
            $details[] = [
                'appID' => $teamId.'.'.$bundleId,
                'paths' => [
                    '/web/visitSingleVideo',
                    '/web/visitSingleVideo/*',
                ],
            ];
        }

        $payload = [
            'applinks' => [
                'apps' => [],
                'details' => $details,
            ],
        ];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
    public function visitSingleVideo(Request $request)
    {
        $videoId = (string) $request->query('id', '');
        abort_if($videoId === '', 404);

        $video = DB::table('videos as v')
            ->leftJoin('front_users as u', 'u.id', '=', 'v.front_user_id')
            ->where('v.id', $videoId)
            ->where('v.is_soft_delete', 0)
            ->select([
                'v.id',
                'v.title',
                'v.description',
                'v.video',
                'v.image',
                'v.status',
                'u.name as user_name',
            ])
            ->first();

        abort_if(!$video, 404);

        AppHelper::decorateVideoRow($video);

        return view('frontend.video_deeplink', [
            'video' => $video,
            'androidPackageName' => env('ANDROID_APP_PACKAGE', $this->androidPackageName),
            'iosAppStoreUrl' => 'https://apps.apple.com/us/app/cookster-كوكستر/id6746804733',
            'androidStoreUrl' => 'https://play.google.com/store/apps/details?id='.env('ANDROID_APP_PACKAGE', $this->androidPackageName),
            'appSchemeUrl' => 'cookster://api/video_details?id='.$video->id,
        ]);
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
        $query->where('blogs.status', 1);

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

        $data['blogs'] = $query->select(['blogs.*', 'blogs_description.custom_url', 'blogs_description.title', 'blogs_description.short_description', 'blogcategories_description.title as category_title'])->orderBy('blogs.date', 'DESC')->paginate(6);

        return view('frontend.blog',compact('data'));
    }
    public function blog_post($locale, $category, $post){
        $data = array();
        App::setLocale($locale);
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
                ->whereRaw('LOWER(REPLACE(blogs_description.custom_url, " ", "-")) = ?', [strtolower($post)])
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

                $data['related_blogs'] = $query->select(['blogs.*', 'blogs_description.custom_url', 'blogs_description.title', 'blogs_description.short_description', 'blogcategories_description.title as category_title'])->inRandomOrder()->limit(3)->get();
            }
        }

        return view('frontend.blog_details',compact('data'));
    }

    public function rebuildSitemap()
    {
        $sitemapPath = public_path('sitemap.xml');

        // Create new sitemap XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        // Get all blogs with matching category descriptions per language
        $blogs = DB::table('blogs')
            ->join('blogs_description', 'blogs.id', '=', 'blogs_description.blog_id')
            ->join('blogcategories', 'blogs.blogcategory_id', '=', 'blogcategories.id')
            ->join('blogcategories_description', function ($join) {
                $join->on('blogcategories.id', '=', 'blogcategories_description.blogcategory_id')
                     ->on('blogs_description.language_id', '=', 'blogcategories_description.language_id');
            })
            ->join('site_languages', 'blogs_description.language_id', '=', 'site_languages.id')
            ->select(
                'blogs.id as blog_id',
                'blogs.date',
                'blogs_description.language_id',
                'blogs_description.custom_url',
                'blogcategories_description.title as category_title',
                'site_languages.code as lang_code'
            )
            ->where('blogs_description.custom_url', '!=', '') // ✅ only non-empty slugs
            ->get();

        foreach ($blogs as $blog) {
            // Arabic: keep native characters, English: slugify
            $categorySlug = $blog->lang_code === 'ar'
                ? str_replace(' ', '-', $blog->category_title)   // keep Arabic script
                : Str::slug($blog->category_title);              // normal slug for English

            $customUrl = $blog->lang_code === 'ar'
                ? str_replace(' ', '-', $blog->custom_url)       // keep Arabic script
                : Str::slug($blog->custom_url);                  // normal slug for English

            $prefix    = $blog->lang_code === 'ar' ? '/ar' : '/en';

            $newUrl = url($prefix . '/blog/' . $categorySlug . '/' . $customUrl);

            $url = $xml->addChild('url');
            $url->addChild('loc', $newUrl);
            $url->addChild('lastmod', now()->toAtomString());
            $url->addChild('priority', '0.64');
        }

        // Save sitemap
        $xml->asXML($sitemapPath);

        return response()->json(['message' => 'Sitemap rebuilt successfully']);
    }
}
