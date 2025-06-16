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
    
class PersonalAccountsController extends Controller
{
    private $module_title_singular = 'Personal Account';
    private $module_title_plural = 'Personal Accounts';
    private $view_folder_name = 'personal_accounts';
    private $uploads_folder_name = 'front_users';
    private $permission_initial = 'personal-accounts';
    private $url_path = 'personal_accounts';
    function __construct()
    {
         $this->middleware('permission:'.$this->permission_initial.'', ['only' => ['index','show']]);
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
        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();

        $query=DB::table('front_users as personal_account');
        $query->where('personal_account.entity', 1);
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where(function ($q) use ($input) {
                    $searchValue = '%' . $input['search']['value'] . '%';
                    $q->where('personal_account.name', 'LIKE', $searchValue)
                      ->orWhere('personal_account.email', 'LIKE', $searchValue);
                });
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select(['personal_account.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('personal_account.system_id', 'ASC');
        $data = $query2->select(['personal_account.*'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            if($sdata->status==1){
                $checked = "checked";
            }
            else{
                $checked = "";
            }
            $sub_array = array();
            $sub_array[] = AppHelper::id_formatter(1, $sdata->system_id);
            $sub_array[] = $sdata->name;
            $sub_array[] = $sdata->email;
            $sub_array[] = $sdata->dob? date(env('DATE_FORMAT'), strtotime($sdata->dob)): '';
            
            if($sdata->is_soft_delete==1){
                $sub_array[] = '<span class="badge bg-success">Yes</span>';
            }
            else{
                $sub_array[] = '<span class="badge bg-danger">No</span>';
            }

            $sub_array[] = '<div class="form-check form-switch" dir="ltr"><input class="form-check-input userStatusChanger" data-id="'.$sdata->id.'" type="checkbox" role="switch" id="flexSwitchCheckChecked" '.$checked.'></div>';

            $actionshtml = "";
            if(auth()->user()->can($this->permission_initial)){
                $actionshtml .= '<a href="'.route($this->url_path.'.show',$sdata->id).'" class="btn btn-primary btn-icon waves-effect waves-light"><i class="fa-light fa-eye"></i></a>';
            }
            $sub_array[] = $actionshtml;
            $dataToPass[] = $sub_array;
        }
        $output=array(
            "draw"  =>  intval($input['draw']),
            "recordsTotal"  =>  $totalData,
            "recordsFiltered"   =>  $totalData,
            "data"  =>  $dataToPass
        );
        echo json_encode($output);
    }
    public function show($id): View{
        $data = array();

        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['permission_initial'] = $this->permission_initial;
        $data['url_path'] = $this->url_path;
        
        $query = DB::table('front_users')->leftJoin('countries', 'countries.id', '=', 'front_users.country')->leftJoin('states', 'states.id', '=', 'front_users.state')->leftJoin('cities', 'cities.id', '=', 'front_users.city')->select(['front_users.*', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name']);
        $data['general_data'] = $query->where('front_users.id', $id)->first();

        return view($this->view_folder_name.'.show',compact('data'));
    }
}