<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Generickeyvalue;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class GenerickeyvalueController extends Controller
{
    private $module_title_singular = 'Generic Key Value';
    private $module_title_plural = 'Generic Key Values';
    private $view_folder_name = 'generickeyvalues';
    private $permission_initial = 'generic-key-values';
    private $table_name = 'generic_key_values';
    private $description_table_name = 'generic_key_values_description';
    private $url_path = 'generickeyvalues';
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

        $query=DB::table('generic_keys');
        $query->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id');
        $query->join('site_languages', 'generic_keys_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        $query->where('generic_keys.status', 1);
        $query->select(['generic_keys.*', 'generic_keys_description.name'])->get();
        $data['generic_keys'] = $query->get();

        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name);
        $query->join($this->description_table_name, $this->description_table_name.'.value_id', '=', $this->table_name.'.id');
        $query->join('site_languages', $this->description_table_name.'.language_id', '=', 'site_languages.id');
        $query->join('generic_keys', $this->table_name.'.key_id', '=', 'generic_keys.id');
        $query->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id');
        $query->join('site_languages as key_language', 'generic_keys_description.language_id', '=', 'key_language.id');
        $query->where('site_languages.is_default', 1);
        $query->where('key_language.is_default', 1);
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where($this->description_table_name.'.name', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if(isset($input['key_id']) && $input['key_id']!=''){
            $query->where($this->table_name.'.key_id', $input['key_id']);
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy($this->table_name.'.id', 'ASC');
        $data = $query2->select([$this->table_name.'.*', 'generic_keys_description.name as key_name', $this->description_table_name.'.name'])->get();
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
            $sub_array[]=$sdata->key_name;
            $sub_array[]=$sdata->name;
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

        $query=DB::table('generic_keys');
        $query->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id');
        $query->join('site_languages', 'generic_keys_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        $query->where('generic_keys.status', 1);
        $query->select(['generic_keys.*', 'generic_keys_description.name'])->get();
        $data['generic_keys'] = $query->get();

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
        $site_languages = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();
        foreach ($site_languages as $language) {
            $customMessages['name.' . $language->id . '.required'] = 'The ' . $language->name . ' name is required.';
        }
        $this->validate($request, [
            'key_id' => 'required',
            'name' => 'required|array',
            'name.*' => 'required',
            'status' => 'required'
        ], $customMessages);
        $input = $request->all();

        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $record = Generickeyvalue::create($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'value_id' => $record->id,
                'language_id' => $language->id,
                'name' => $input['name'][$language->id]
            );
            DB::table($this->description_table_name)->insert($description_data);
        }
    
        return redirect()->route($this->url_path.'.index', ['key_id' => $record->key_id])->with('success',$this->module_title_singular.' created successfully');
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
        $m_data = Generickeyvalue::find($id);
        $m_data_descriptions = DB::table($this->description_table_name)->select()->where('value_id',$id)->get()->keyBy('language_id');
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['url_path'] = $this->url_path;
        $data['site_languages'] = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();

        $query=DB::table('generic_keys');
        $query->join('generic_keys_description', 'generic_keys.id', '=', 'generic_keys_description.key_id');
        $query->join('site_languages', 'generic_keys_description.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        $query->where('generic_keys.status', 1);
        $query->select(['generic_keys.*', 'generic_keys_description.name'])->get();
        $data['generic_keys'] = $query->get();

        return view($this->view_folder_name.'.edit',compact('m_data', 'm_data_descriptions', 'data'));
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
        $site_languages = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();
        foreach ($site_languages as $language) {
            $customMessages['name.' . $language->id . '.required'] = 'The ' . $language->name . ' name is required.';
        }
        $this->validate($request, [
            'key_id' => 'required',
            'name' => 'required|array',
            'name.*' => 'required',
            'status' => 'required'
        ], $customMessages);

        $input = $request->all();
        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $m_data = Generickeyvalue::find($id);
        $m_data->update($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'value_id' => $id,
                'language_id' => $language->id,
                'name' => $input['name'][$language->id]
            );
            $validate = DB::table($this->description_table_name)->where('value_id',$id)->where('language_id', $language->id)->first();
            if(empty($validate)){
                DB::table($this->description_table_name)->insert($description_data);
            }
            else{
                DB::table($this->description_table_name)->where('id',$validate->id)->update($description_data);
            }
        }
        return redirect()->route($this->url_path.'.index', ['key_id' => $m_data->key_id])->with('success',$this->module_title_singular.' updated successfully');
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): RedirectResponse
    {
        $reord = Generickeyvalue::find($id);
        Generickeyvalue::find($id)->delete();
        DB::table($this->description_table_name)->where('value_id',$id)->delete();
        return redirect()->route($this->url_path.'.index', ['key_id' => $reord->key_id])->with('success',$this->module_title_singular.' deleted successfully');
    }
}