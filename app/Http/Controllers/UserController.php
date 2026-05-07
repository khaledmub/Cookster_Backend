<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use DB;
use Hash;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
    
class UserController extends Controller
{
    function __construct()
    {
         $this->middleware('permission:users-list|users-create|users-edit|users-delete', ['only' => ['index','store']]);
         $this->middleware('permission:users-create', ['only' => ['create','store']]);
         $this->middleware('permission:users-edit', ['only' => ['edit','update']]);
         $this->middleware('permission:users-delete', ['only' => ['destroy']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): View
    {
        $data = array();
  
        return view('users.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_users_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table('users');
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $term = '%'.$input['search']['value'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('users.name', 'LIKE', $term)
                    ->orWhere('users.email', 'LIKE', $term);
            });
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select(['users.*'])->count();
        
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset((int) ($input['start'] ?? 0))->limit((int) $input['length']);
        }
        $data = $query2->select(['users.*'])->orderBy('users.id', 'desc')->get();
        $dataToPass=array();
        foreach($data as $sdata){
            $user = User::where('id',$sdata->id)->first();
            $role_names = "";
            if($user && $user->roles->isNotEmpty()){
                foreach($user->roles as $role){
                    $role_names.='<label class="badge bg-success">'.$role->name.'</label>';
                }
            }
            $sub_array=array();
            $sub_array[]=$sdata->name;
            $sub_array[]=$sdata->email;
            $sub_array[]=$role_names;
            $actionshtml="";
            if(auth()->user()->can('users-edit')){
                $actionshtml.='<a href="'.route('users.edit',$sdata->id).'" class="btn btn-primary btn-icon waves-effect waves-light"><i class=" ri-edit-line"></i></a>';
            }
            if(auth()->user()->can('users-delete')){
                $url = url('admin/users/'.$sdata->id);
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
            "draw"  =>  intval($input['draw'] ?? 0),
            "recordsTotal"  =>  $totalData,
            "recordsFiltered"   =>  $totalData,
            "data"  =>  $dataToPass
        );

        return response()->json($output);
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): View
    {
        $roles = Role::pluck('name','name')->all();
        return view('users.create',compact('roles'));
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'roles' => 'required'
        ]);
    
        $input = $request->only(['name', 'email', 'password', 'system_id']);
        $user = User::create($input);
        $user->assignRole($request->input('roles'));
    
        return redirect()->route('users.index')
                        ->with('success','User created successfully');
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): View
    {
        $user = User::find($id);

        return view('users.show',compact('user'));
    }
    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id): View
    {
        $user = User::find($id);
        $roles = Role::pluck('name','name')->all();
        $userRole = $user->roles->pluck('name','name')->all();
        return view('users.edit',compact('user','roles','userRole'));
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
            'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$id,
            'password' => 'same:confirm-password',
            'roles' => 'required'
        ]);
        $input = $request->all();
        if(!empty($input['password'])){ 
            $input['password'] = Hash::make($input['password']);
        }else{
            $input = Arr::except($input,array('password'));    
        }
    
        $user = User::find($id);
        $user->update($input);
        DB::table('model_has_roles')->where('model_id',$id)->delete();
    
        $user->assignRole($request->input('roles'));
    
        return redirect()->route('users.index')
                        ->with('success','User updated successfully');
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): RedirectResponse
    {
        User::find($id)->delete();
        return redirect()->route('users.index')
                        ->with('success','User deleted successfully');
    }
}