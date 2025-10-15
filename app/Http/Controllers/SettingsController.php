<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
    
class SettingsController extends Controller
{
    private $module_title_singular = 'Setting';
    private $module_title_plural = 'Settings';
    private $view_folder_name = 'settings';
    private $permission_initial = 'settings';
    private $table_name = 'settings';
    private $url_path = 'settings';
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
        
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): View{}
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse{}
    
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
    public function edit($id): View
    {
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['url_path'] = $this->url_path;
        $setting = Setting::find($id);
        return view('settings.edit',compact('data', 'setting'));
    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $this->validate($request, [
            
        ]);
        $input = $request->all();
        $setting = Setting::find($id);
        if(isset($input['allow_general_videos']) && $input['allow_general_videos']==1){
            $input['allow_general_videos'] = 1;
        }
        else{
            $input['allow_general_videos'] = 0;
        }
        if(isset($input['allow_following_videos']) && $input['allow_following_videos']==1){
            $input['allow_following_videos'] = 1;
        }
        else{
            $input['allow_following_videos'] = 0;
        }
        if(isset($input['loyalty_points_status']) && $input['loyalty_points_status']==1){
            $input['loyalty_points_status'] = 1;
        }
        else{
            $input['loyalty_points_status'] = 0;
        }
        $setting->update($input);
        return redirect()->route($this->url_path.'.edit', 1)->with('success',$this->module_title_singular.' updated successfully');
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): RedirectResponse{}
}