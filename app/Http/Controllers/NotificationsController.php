<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class NotificationsController extends Controller
{
    private $module_title_singular = 'Notification';
    private $module_title_plural = 'Notifications';
    private $view_folder_name = 'notifications';
    private $uploads_folder_name = 'notifications';
    private $permission_initial = 'notifications';
    private $table_name = 'push_notifications';
    private $url_path = 'notifications';
    private $default_date_time_format = 'd-M-Y h:i A';
    function __construct()
    {
         $this->middleware('permission:'.$this->permission_initial.'-list', ['only' => ['index']]);
         $this->middleware('permission:'.$this->permission_initial.'-create', ['only' => ['create','store']]);
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
        return view($this->view_folder_name.'.index',compact('data'));
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name.' as n');
        $query->leftJoin('front_users as u', 'u.id', '=', 'n.front_user_id');
        $query->leftJoin('entities as e', 'e.id', '=', 'n.to_type');
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where('n.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('n.id', 'DESC');
        $data = $query2->select(['n.*', 'u.name', 'e.name as entity_name'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            $to_label = "";
            if($sdata->to_type==0){
                $to_label = '<label class="badge bg-primary">All</label>';
            }
            else if($sdata->entity_name){
                $to_label = '<label class="badge bg-primary">'.$sdata->entity_name.'</label>';
            }
            if($sdata->name){
                $user_name = $sdata->name;
            }
            else{
                $user_name = "All";
            }
            $sub_array=array();
            $sub_array[]=$sdata->title;
            $sub_array[]=$to_label;
            $sub_array[]=$user_name;
            $formattedDate = $this->formatDateTime($sdata->created_at ?? null);
            $sub_array[] = $formattedDate;

            $actionshtml="";
            if(auth()->user()->can($this->permission_initial.'-list')){
                $actionshtml .= '<a href="javascript:void(0)" data-title="'.$sdata->title.'" data-text="'.$sdata->text.'" data-date="'.$formattedDate.'" class="btn btn-primary btn-icon waves-effect waves-light viewNotificationDetails"><i class="fa-light fa-eye"></i></a>';
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

    private function formatDateTime($rawDateTime): string
    {
        if (empty($rawDateTime)) {
            return '-';
        }

        $format = env('DATE_TIME_FORMAT');
        if (!is_string($format) || trim($format) === '') {
            $format = $this->default_date_time_format;
        }

        $timestamp = strtotime((string) $rawDateTime);
        if ($timestamp === false) {
            return '-';
        }

        return date($format, $timestamp);
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): View
    {
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['url_path'] = $this->url_path;
        $data['entities'] = DB::table('entities')->where('status', 1)->select(['id', 'name'])->orderBy('sort_order', 'ASC')->get();
        return view($this->view_folder_name.'.create',compact('data'));
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse{
        $customMessages = [];
        $this->validate($request, [
            'title' => 'required',
            'to_type' => 'required',
            'text' => 'required'
        ], $customMessages);
        $input = $request->all();

        /* Push Notification Code Start */
        $push_notification_text = [
            'title' => $request->title,
            'text' => $request->text,
            'notification_data' => [
                'status' => true
            ]
        ];

        if(empty($request->front_user_id)){
            if((int)$request->to_type == 0){
                AppHelper::send_push_notification_topic($push_notification_text, 'cookster');
            }
            else{
                $topic = 'type_' . $request->to_type;
                AppHelper::send_push_notification_topic($push_notification_text, $topic);
            }
        }
        else{
            $query = DB::table('front_users')->where('id', $request->front_user_id);

            if($request->to_type > 0){
                $query->where('entity', $request->to_type);
            }

            $deviceTokens = $query->whereNotNull('uuid')->pluck('uuid')->toArray();

            if(!empty($deviceTokens)){
                AppHelper::send_push_notification($push_notification_text, $deviceTokens);
            }
        }
        /* Push Notification Code End */

        $record = Notification::create($input);

        /* Create Notification Start */
        $notification_data = array();
        $notification_data['to_type'] = 2;
        $notification_data['front_user_id'] = $request->front_user_id;
        $notification_data['front_user_category'] = $input['to_type'];
        $notification_data['type'] = 2;
        $notification_data['push_notification_id'] = $record->id;
        DB::table('notifications')->insert($notification_data);
        /* Create Notification End */
        
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' created successfully');
    }
}