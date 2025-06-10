<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class AdvertisementsController extends Controller
{
    private $module_title_singular = 'Advertisement';
    private $module_title_plural = 'Advertisements';
    private $view_folder_name = 'advertisements';
    private $uploads_folder_name = 'advertisements';
    private $permission_initial = 'advertisements';
    private $table_name = 'advertisements';
    private $url_path = 'advertisements';
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
        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name);
        $query->join('generic_key_values_description as gender_description', 'gender_description.value_id', '=', $this->table_name.'.gender');
        $query->join('site_languages as gender_language', 'gender_description.language_id', '=', 'gender_language.id');
        $query->leftJoin('countries', 'countries.id', '=', $this->table_name.'.country');
        $query->leftJoin('states', 'states.id', '=', $this->table_name.'.state');
        $query->leftJoin('cities', 'cities.id', '=', $this->table_name.'.city');
        $query->where('gender_language.is_default', 1);

        if(isset($input['search']['value']) && $input['search']['value']!=''){
            
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy($this->table_name.'.id', 'ASC');
        $data = $query2->select([$this->table_name.'.*', 'gender_description.name as gender_name', 'countries.name as country_name', 'states.name as state_name', 'cities.name as city_name'])->get();
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
            $sub_array[]=$sdata->age;
            $sub_array[]=$sdata->gender_name;
            // $sub_array[]=$sdata->country_name;
            $sub_array[]=$sdata->state_name;
            // $sub_array[]=$sdata->city_name;
            $sub_array[]=date(env('DATE_FORMAT'), strtotime($sdata->start_date));
            $sub_array[]=date(env('DATE_FORMAT'), strtotime($sdata->end_date));
            $sub_array[]='<a target="_blank" href="'.asset('storage/'.$this->uploads_folder_name.'/'.$sdata->file).'">File</a>';
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
        $data['genders'] = AppHelper::get_key_values(3)['values'];
        $data['countries'] = DB::table('countries')->get();
        $data['states'] = DB::table('states')->where('country_id', 194)->get();
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
            'age' => 'required',
            'gender' => 'required',
            // 'country' => 'required',
            'state' => 'required',
            // 'city' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,mp4|max:2048',
            'status' => 'required'
        ], $customMessages);
        $input = $request->all();
        $input['country']=194;
        if($request->file('file')){
            $file = $request->file('file');
            $file_name = time().'.'.$file->extension();
            $fileresponse=$request->file('file')->storeAs('public/'.$this->uploads_folder_name,$file_name);
            $input['file'] = $file_name;
        }else{
            $input['file'] = '';
        }

        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $record = Advertisement::create($input);
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
        $m_data = Advertisement::find($id);
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['uploads_folder_name'] = $this->uploads_folder_name;
        $data['url_path'] = $this->url_path;
        $data['genders'] = AppHelper::get_key_values(3)['values'];
        $data['countries'] = DB::table('countries')->get();
        $data['states'] = DB::table('states')->where('country_id', $m_data->country)->get();
        $data['cities'] = DB::table('cities')->where('state_id', $m_data->state)->get();
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
            'age' => 'required',
            'gender' => 'required',
            // 'country' => 'required',
            'state' => 'required',
            // 'city' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,mp4|max:2048',
            'status' => 'required'
        ], $customMessages);

        $input = $request->all();
        if($request->file('file')){
            $file = $request->file('file');
            $file_name = time().'.'.$file->extension();
            $fileresponse=$request->file('file')->storeAs('public/'.$this->uploads_folder_name,$file_name);
            $input['file'] = $file_name;
        }
        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $m_data = Advertisement::find($id);
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
        $record = Advertisement::find($id);
        if ($record->file) {
            $filePath = storage_path('app/public/' . $this->uploads_folder_name . '/' . $record->file);
            if (file_exists($filePath)) {
                unlink($filePath); // Delete the main image
            }
        }
        $record->delete();
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' deleted successfully');
    }
}