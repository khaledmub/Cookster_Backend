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
    
class SponsoredAccountsController extends Controller
{
    private $module_title_singular = 'Sponsored Account';
    private $module_title_plural = 'Sponsored Accounts';
    private $view_folder_name = 'sponsored_accounts';
    private $uploads_folder_name = 'front_users';
    private $permission_initial = 'sponsored-accounts';
    private $url_path = 'sponsored_accounts';
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

        $query=DB::table('front_users as sponsored_account');
        $query->where('sponsored_account.entity', 8);
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where(function ($q) use ($input) {
                    $searchValue = '%' . $input['search']['value'] . '%';
                    $q->where('sponsored_account.name', 'LIKE', $searchValue)
                      ->orWhere('sponsored_account.email', 'LIKE', $searchValue)
                      ->orWhere('sponsored_account.phone', 'LIKE', $searchValue);
                });
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select(['sponsored_account.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('sponsored_account.system_id', 'ASC');
        $data = $query2->select(['sponsored_account.*'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            if($sdata->status==1){
                $checked = "checked";
            }
            else{
                $checked = "";
            }
            $sub_array=array();
            $sub_array[]=AppHelper::id_formatter(5, $sdata->system_id);
            $sub_array[]=$sdata->name;
            $sub_array[]=$sdata->email;
            $sub_array[]=$sdata->phone;

            if($sdata->is_soft_delete==1){
                $sub_array[]='<span class="badge bg-success">Yes</span>';
            }
            else{
                $sub_array[]='<span class="badge bg-danger">No</span>';
            }
            
            $sub_array[]='<div class="form-check form-switch" dir="ltr"><input class="form-check-input userStatusChanger" data-id="'.$sdata->id.'" type="checkbox" role="switch" id="flexSwitchCheckChecked" '.$checked.'></div>';

            $actionshtml="";
            if(auth()->user()->can($this->permission_initial)){
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
    public function show($id): View{
        $data = array();

        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['permission_initial'] = $this->permission_initial;
        $data['url_path'] = $this->url_path;
        
        $query = DB::table('front_users')->select(['front_users.*']);
        $data['general_data'] = $query->where('front_users.id', $id)->first();

        $query = DB::table('sponsored_account_additional_data')->leftJoin('front_users', 'front_users.id', '=', 'sponsored_account_additional_data.front_user_id');
        $query->leftJoin('countries', 'countries.id', '=', 'front_users.country');
        $query->leftJoin('states', 'states.id', '=', 'front_users.state');
        $query->leftJoin('cities', 'cities.id', '=', 'front_users.city');
        $query->leftJoin('generic_key_values_description as type_of_account_description', 'type_of_account_description.value_id', '=', 'sponsored_account_additional_data.type_of_account')->leftJoin('site_languages as type_of_account_language', 'type_of_account_description.language_id', '=', 'type_of_account_language.id');
        $query->where(function ($q){
            $q->where('type_of_account_language.is_default', 1)
              ->orWhere('sponsored_account_additional_data.type_of_account', 0); 
        });
        $query->where('sponsored_account_additional_data.front_user_id', $id);
        $query->select(['sponsored_account_additional_data.*', 'type_of_account_description.name as type_of_account_name', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name']);
        $data['additional_data'] = $query->first();

        return view($this->view_folder_name.'.show',compact('data'));
    }
}