<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Citiesgroup;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class CitiesgroupsController extends Controller
{
    private $module_title_singular = 'Cities Groups';
    private $module_title_plural = 'Cities Group';
    private $view_folder_name = 'cities_groups';
    private $uploads_folder_name = 'cities_groups';
    private $permission_initial = 'cities-groups';
    private $table_name = 'cities_groups';
    private $url_path = 'cities_groups';
    function __construct()
    {
         $this->middleware('permission:'.$this->permission_initial.'-list|'.$this->permission_initial.'-create|'.$this->permission_initial.'-edit|'.$this->permission_initial.'-delete', ['only' => ['index','store']]);
         $this->middleware('permission:'.$this->permission_initial.'-create', ['only' => ['create','store']]);
         $this->middleware('permission:'.$this->permission_initial.'-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:'.$this->permission_initial.'-delete', ['only' => ['destroy']]);
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
        $query=DB::table($this->table_name);
        $query->join('countries', $this->table_name.'.country', '=', 'countries.id');
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where($this->table_name.'.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy($this->table_name.'.id', 'ASC');
        $data = $query2->select([$this->table_name.'.*', 'countries.name as country_name'])->get();
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
            $sub_array[]=$sdata->title;
            $sub_array[]=$sdata->country_name;
            $sub_array[]=AppHelper::get_cities_names($sdata->cities);
            $sub_array[]=$status_label;

            $actionshtml="";
            if(auth()->user()->can($this->permission_initial.'-edit')){
                $actionshtml.='<a href="'.route($this->url_path.'.edit',$sdata->id).'" class="btn btn-primary btn-icon waves-effect waves-light"><i class=" ri-edit-line"></i></a>';
            }
            if(auth()->user()->can($this->permission_initial.'-delete')){
                $url = url('admin/'.$this->url_path.'/'.$sdata->id);
                $actionshtml.='<form action="'.$url.'" method="POST" class="d-inline">
                                <input name="_method" type="hidden" value="DELETE">
                                <input type="hidden" name="_token" value="'.$csrfToken.'">
                                    <button class="btn btn-danger btn-icon waves-effect waves-light deleteAction" type="button"><i class="ri-delete-bin-5-line"></i></button>
                                </form>';
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
        $data['site_languages'] = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();

        $query = DB::table('countries');
        $query->where('status', 1);
        $data['countries'] = $query->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        $data['cities'] = array();
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
            'country' => 'required',
            'cities' => 'required',
            'status' => 'required'
        ], $customMessages);
        $input = $request->all();
        if($input['cities'] && !empty($input['cities'])){
            $input['cities']=implode(',', $input['cities']);
        }
        else{
            $input['cities']="";
        }

        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $record = Citiesgroup::create($input);
    
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' created successfully');
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): View{}
    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id): View{
        $m_data = Citiesgroup::find($id);
        $m_data->cities = explode(',', $m_data->cities);
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['uploads_folder_name'] = $this->uploads_folder_name;
        $data['url_path'] = $this->url_path;
        $data['site_languages'] = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();
        $query = DB::table('countries');
        $query->where('status', 1);
        $data['countries'] = $query->select(['id', 'name', 'iso3', 'capital', 'currency', 'currency_symbol'])->get();
        $data['cities'] = DB::table('cities')->where('country_id',$m_data->country)->get();
        return view($this->view_folder_name.'.edit',compact('m_data', 'data'));
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id): RedirectResponse{
        $customMessages = [];
        $this->validate($request, [
            'title' => 'required',
            'country' => 'required',
            'cities' => 'required',
            'status' => 'required'
        ], $customMessages);

        $input = $request->all();
        if($input['cities'] && !empty($input['cities'])){
            $input['cities']=implode(',', $input['cities']);
        }
        else{
            $input['cities']="";
        }
        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $m_data = Citiesgroup::find($id);
        $m_data->update($input);
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' updated successfully');
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): RedirectResponse
    {
        $record = Citiesgroup::find($id);
        $record->delete();
        
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' deleted successfully');
    }
}