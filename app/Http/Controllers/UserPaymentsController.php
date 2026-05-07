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
    
class UserPaymentsController extends Controller
{
    private $module_title_singular = 'User Payment';
    private $module_title_plural = 'User Payments';
    private $view_folder_name = 'user_payments';
    private $uploads_folder_name = 'user_payments';
    private $permission_initial = 'user-payments';
    private $table_name = 'user_payments';
    private $url_path = 'user_payments';
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
        $data['users'] = DB::table('front_users')->join('entities', 'entities.id', '=', 'front_users.entity')->orderBy('front_users.system_id', 'DESC')->select(['front_users.*', 'entities.name as entity_name'])->get();
        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name.' as up');
        $query->join('front_users as u', 'u.id', '=', 'up.user_id');
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where('up.TranId', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if(isset($input['user']) && $input['user']!=''){
            $query->where('up.user_id', $input['user']);
        }
        if(isset($input['payment_for']) && $input['payment_for']!=''){
            $query->where('up.payment_for', $input['payment_for']);
        }
        if(isset($input['TranId']) && $input['TranId']!=''){
            $query->where('up.TranId', 'LIKE', '%'.$input['TranId'].'%');
        }
        if(isset($input['start_date']) && $input['start_date']!=''){
            $query->whereDate('up.created_at', '>=', $input['start_date']);
        }
        if(isset($input['end_date']) && $input['end_date']!=''){
            $query->whereDate('up.created_at', '<=', $input['end_date']);
        }

        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('up.system_id', 'DESC');
        $data = $query2->select(['up.*', 'u.name as user_name'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            $payment_for_label = "";
            if($sdata->payment_for == 1){
                $payment_for_label = '<label class="badge bg-primary">Subscription</label>';
            }
            else if($sdata->payment_for == 2){
                $payment_for_label = '<label class="badge bg-secondary">Sponsor</label>';
            }
            $sub_array = array();
            $sub_array[] = AppHelper::id_formatter(6, $sdata->system_id);
            $sub_array[] = $sdata->TranId;
            $sub_array[] = AppHelper::currency_formatter($sdata->amount);
            $sub_array[] = $payment_for_label;
            $sub_array[] = $sdata->user_name;
            $sub_array[] = $sdata->PaymentType;
            $sub_array[] = $sdata->cardBrand;
            $sub_array[] = $sdata->maskedPAN;
            $sub_array[] = $sdata->created_at;
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
}