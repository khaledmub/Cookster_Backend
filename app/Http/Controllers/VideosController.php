<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Video;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class VideosController extends Controller
{
    private $module_title_singular = 'Video';
    private $module_title_plural = 'Videos';
    private $view_folder_name = 'videos';
    private $uploads_folder_name = 'videos';
    private $permission_initial = 'videos';
    private $table_name = 'videos';
    private $url_path = 'videos';
    function __construct()
    {
         $this->middleware('permission:'.$this->permission_initial.'-list', ['only' => ['index','show']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): View
    {
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['permission_initial'] = $this->permission_initial;
        $data['url_path'] = $this->url_path;
        $data['video_types'] = AppHelper::get_key_values(2);
        $data['users'] = DB::table('front_users')->join('entities', 'entities.id', '=', 'front_users.entity')->orderBy('front_users.system_id', 'DESC')->select(['front_users.*', 'entities.name as entity_name'])->get();
        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name.' as v');
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
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('v.system_id', 'DESC');
        $data = $query2->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            $status_label = "";
            if($sdata->status==0){
                $status_label = '<label class="badge bg-danger">Inactive</label>';
            }
            else if($sdata->status==1){
                $status_label = '<label class="badge bg-success">Active</label>';
            }
            $sub_array=array();
            $sub_array[]=AppHelper::id_formatter(4, $sdata->system_id);
            $sub_array[]=$sdata->user_name;
            $sub_array[]=$sdata->title;
            $sub_array[]=$sdata->video_type_name;
            $sub_array[]='<img style="max-height: 100px; max-width: 50px;" src="'.env('AWS_CLOUD_FRONT_PATH')."videos/".$sdata->image.'">';
            $sub_array[]=$status_label;

            $actionshtml="";
            if(auth()->user()->can($this->permission_initial.'-list')){
                $actionshtml.='<a href="'.route($this->url_path.'.show',$sdata->id).'" class="btn btn-primary btn-icon waves-effect waves-light"><i class="fa-light fa-eye"></i></a>';
            }
            $sub_array[]=$actionshtml;
            $dataToPass[]=$sub_array;
        }
        $output=array(
            "draw"  =>  intval($input['draw']),
            "recordsTotal"  =>  $totalData,
            "recordsFiltered"   =>  $totalData,
            "data"  =>  $dataToPass
        );
        echo json_encode($output);
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request): View{
        if(isset($request->notification_id) && $request->notification_id>0){
            $up_data = array('read_status' => 1);
            DB::table('notifications')->where('id',$request->notification_id)->update($up_data);
        }
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['permission_initial'] = $this->permission_initial;
        $data['url_path'] = $this->url_path;
        
        $query=DB::table($this->table_name.' as v');
        $query->join('front_users as u', 'u.id', '=', 'v.front_user_id');
        $query->leftJoin('generic_key_values_description as video_type_description', 'video_type_description.value_id', '=', 'v.video_type')->leftJoin('site_languages as video_type_language', 'video_type_description.language_id', '=', 'video_type_language.id')->leftJoin('countries', 'countries.id', '=', 'v.country')->leftJoin('cities', 'cities.id', '=', 'v.city');
        $query->where(function ($q){
            $q->where('video_type_language.is_default', 1)
              ->orWhere('v.video_type', 0); 
        });
        $data['video_details'] = $query->select(['v.*', 'video_type_description.name as video_type_name', 'u.name as user_name', 'countries.name as country_name', 'cities.name as city_name'])->where('v.id', $id)->first();

        if($data['video_details']){
            $video_id = $id;
            // $video_id = "35f91fbf-eda0-4396-bcd4-4b477f42dda0";
            $url = "https://firestore.googleapis.com/v1/projects/".env('FIREBASE_PROJECT_ID')."/databases/(default)/documents/videos/{$video_id}/comments?key=".env('FIREBASE_KEY');
            // Fetch documents
            // $response = file_get_contents($url);
            
            $comments = AppHelper::call_curl_request($url);
            
            if(isset($comments['documents'])){
                $data['comments'] = $comments['documents'];
            }
            else{
                $data['comments'] = array();
            }

            $url = "https://firestore.googleapis.com/v1/projects/".env('FIREBASE_PROJECT_ID')."/databases/(default)/documents/videos/{$video_id}?key=".env('FIREBASE_KEY');
            // Fetch documents
            $video_collection = AppHelper::call_curl_request($url);
            $likes = 0;
            if(isset($video_collection['fields']['likes']['arrayValue']) && isset($video_collection['fields']['likes']['arrayValue']['values'])){
                $likes = count($video_collection['fields']['likes']['arrayValue']['values']);
            }
            $data['likes'] = $likes;

            $url = "https://firestore.googleapis.com/v1/projects/".env('FIREBASE_PROJECT_ID')."/databases/(default)/documents/countContactClick/{$video_id}?key=".env('FIREBASE_KEY');
            $video_collection = AppHelper::call_curl_request($url);
            $order_clicks = 0;
            if(isset($video_collection['fields']['totalClicks']['integerValue'])){
                $order_clicks = $video_collection['fields']['totalClicks']['integerValue'];
            }
            $data['order_clicks'] = $order_clicks;
            // $data['comments'] = DB::table('video_comments as c')->join('front_users as u', 'u.id', '=', 'c.front_user_id')
            // ->select('c.id', 'c.system_id', 'c.video_id', 'c.comment', 'c.created_at', 'u.name as user_name', 'u.image as user_image')
            // ->where('c.video_id', $id)
            // ->whereNull('c.parent_id') // Fetch only top-level comments
            // ->orderBy('c.created_at', 'ASC')
            // ->get()
            // ->map(function ($comment) {
            //     // Fetch replies for each comment
            //     $comment->replies = DB::table('video_comments as c2')->join('front_users as u2', 'u2.id', '=', 'c2.front_user_id')
            //         ->select('c2.id', 'c2.system_id', 'c2.video_id', 'c2.comment', 'c2.created_at', 'u2.name as user_name', 'u2.image as user_image')
            //         ->where('c2.parent_id', $comment->id)
            //         ->orderBy('c2.created_at', 'ASC')
            //         ->get();

            //     return $comment;
            // });

            // echo "<pre>";
            // var_dump($data['comments']);
            // exit;

            $query=DB::table('video_reports as r');
            $query->join('front_users as ru', 'ru.id', '=', 'r.reported_by');
            $query->leftJoin('categories_description as category_description', 'category_description.category_id', '=', 'r.category_id')->leftJoin('site_languages as category_language', 'category_description.language_id', '=', 'category_language.id');
            $query->where(function ($q){
                $q->where('category_language.is_default', 1)
                ->orWhere('r.category_id', null); 
            });
            $query->where('r.video_id', $id);
            $query->orderBy('r.system_id', 'DESC');
            $reports = $query->select(['r.*', 'ru.name as reported_by_name', 'ru.image as user_image', 'category_description.name as category_name'])->get();
            $data['reports'] = $reports;

            $query = DB::table('sponsored_videos_history')->select(['sponsored_videos_history.*']);
            $data['sponsored_history'] = $query->where('sponsored_videos_history.video_id', $id)->orderBy('sponsored_videos_history.system_id', 'DESC')->get();
        }

        return view($this->view_folder_name.'.show',compact('data'));
    }
}