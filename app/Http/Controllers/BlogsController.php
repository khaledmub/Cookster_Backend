<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
    
class BlogsController extends Controller
{
    private $module_title_singular = 'Blog';
    private $module_title_plural = 'Blogs';
    private $view_folder_name = 'blogs';
    private $uploads_folder_name = 'blogs';
    private $permission_initial = 'blogs';
    private $table_name = 'blogs';
    private $description_table_name = 'blogs_description';
    private $url_path = 'blogs';
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
        $query->join($this->description_table_name, $this->description_table_name.'.blog_id', '=', $this->table_name.'.id');
        $query->join('site_languages', $this->description_table_name.'.language_id', '=', 'site_languages.id');
        $query->join('blogcategories', $this->table_name.'.blogcategory_id', '=', 'blogcategories.id');
        $query->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id');
        $query->join('site_languages as category_language', 'blogcategories_description.language_id', '=', 'category_language.id');
        $query->where('site_languages.is_default', 1);
        $query->where('category_language.is_default', 1);
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where($this->description_table_name.'.title', 'LIKE', '%'.$input['search']['value'].'%');
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy($this->table_name.'.id', 'ASC');
        $data = $query2->select([$this->table_name.'.*', $this->description_table_name.'.title', 'blogcategories_description.title as category_title'])->get();
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
            $sub_array[]=$sdata->category_title;
            $sub_array[]=$sdata->custom_url;
            $sub_array[]='<img style="max-height: 100px; max-width: 50px;" src="'.asset('storage/'.$this->uploads_folder_name.'/'.$sdata->image).'">';
            $sub_array[]=date('d M, Y', strtotime($sdata->date));
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

        $query = DB::table('blogcategories')
            ->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id')
            ->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id')
            ->where('blogcategories.status', 1)
            ->select(
                'blogcategories.id',
                DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 1 THEN blogcategories_description.title END) AS en_title"),
                DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 2 THEN blogcategories_description.title END) AS ar_title")
            )
            ->groupBy('blogcategories.id')
            ->get();

        $data['blogcategories'] = $query;

        // $query=DB::table('blogcategories');
        // $query->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id');
        // $query->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id');
        // // $query->where('site_languages.is_default', 1);
        // $query->select(
        //     'blogcategories.id',
        //     DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 1 THEN blogcategories_description.title END) AS en_title"),
        //     DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 2 THEN blogcategories_description.title END) AS ar_title")
        // );
        // $query->groupBy('blogcategories.id');
        // $query->where('blogcategories.status', 1);
        // $query->select(['blogcategories.*', 'blogcategories_description.title'])->get();
        // $data['blogcategories'] = $query->get();

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
            $customMessages['title.' . $language->id . '.required'] = 'The ' . $language->name . ' title is required.';
        }
        $this->validate($request, [
            'title' => 'required|array',
            'title.*' => 'required',
            'blogcategory_id' => 'required',
            'date' => 'required',
            'custom_url' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|dimensions:max_width=2000,max_height=2000|max:2048',
            'status' => 'required'
        ], $customMessages);
        $input = $request->all();

        if($request->file('image')){
            $image = $request->file('image');
            $image_input['imagename'] = time().'.'.$image->extension();
            $fileresponse=$request->file('image')->storeAs('public/'.$this->uploads_folder_name,$image_input['imagename']);
            $destinationPath = storage_path('app/public/'.$this->uploads_folder_name.'/thumbnail');
            $img = Image::read($image->path());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath.'/'.$image_input['imagename']);
            $input['image'] = $image_input['imagename'];
        }else{
            $input['image'] = '';
        }

        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }

        $record = Blog::create($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'blog_id' => $record->id,
                'language_id' => $language->id,
                'title' => $input['title'][$language->id],
                'short_description' => $input['short_description'][$language->id],
                'description' => $input['description'][$language->id],
                'meta_title' => $input['meta_title'][$language->id],
                'meta_description' => $input['meta_description'][$language->id],
                'meta_keywords' => $input['meta_keywords'][$language->id]
            );
            DB::table($this->description_table_name)->insert($description_data);
        }
    
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
        $m_data = Blog::find($id);
        $m_data_descriptions = DB::table($this->description_table_name)->select()->where('blog_id',$id)->get()->keyBy('language_id');
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['uploads_folder_name'] = $this->uploads_folder_name;
        $data['url_path'] = $this->url_path;
        $data['site_languages'] = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();

        $query = DB::table('blogcategories')
        ->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id')
        ->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id')
        ->where('blogcategories.status', 1)
        ->select(
            'blogcategories.id',
            DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 1 THEN blogcategories_description.title END) AS en_title"),
            DB::raw("GROUP_CONCAT(CASE WHEN blogcategories_description.language_id = 2 THEN blogcategories_description.title END) AS ar_title")
        )
        ->groupBy('blogcategories.id')
        ->get();

        $data['blogcategories'] = $query;

        // $query=DB::table('blogcategories');
        // $query->join('blogcategories_description', 'blogcategories.id', '=', 'blogcategories_description.blogcategory_id');
        // $query->join('site_languages', 'blogcategories_description.language_id', '=', 'site_languages.id');
        // $query->where('site_languages.is_default', 1);
        // $query->where('blogcategories.status', 1);
        // $query->select(['blogcategories.*', 'blogcategories_description.title'])->get();
        // $data['blogcategories'] = $query->get();

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
            $customMessages['title.' . $language->id . '.required'] = 'The ' . $language->name . ' title is required.';
        }
        $this->validate($request, [
            'title' => 'required|array',
            'title.*' => 'required',
            'blogcategory_id' => 'required',
            'date' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|dimensions:max_width=2000,max_height=2000|max:2048',
            'status' => 'required'
        ], $customMessages);

        $input = $request->all();
        if($request->file('image')){
            $image = $request->file('image');
            $image_input['imagename'] = time().'.'.$image->extension();
            $fileresponse=$request->file('image')->storeAs('public/'.$this->uploads_folder_name,$image_input['imagename']);
            $destinationPath = storage_path('app/public/'.$this->uploads_folder_name.'/thumbnail');
            $img = Image::read($image->path());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($destinationPath.'/'.$image_input['imagename']);
            $input['image'] = $image_input['imagename'];
        }
        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $m_data = Blog::find($id);
        $m_data->update($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'blog_id' => $id,
                'language_id' => $language->id,
                'title' => $input['title'][$language->id],
                'short_description' => $input['short_description'][$language->id],
                'description' => $input['description'][$language->id],
                'meta_title' => $input['meta_title'][$language->id],
                'meta_description' => $input['meta_description'][$language->id],
                'meta_keywords' => $input['meta_keywords'][$language->id]
            );
            $validate = DB::table($this->description_table_name)->where('blog_id',$id)->where('language_id', $language->id)->first();
            if(empty($validate)){
                DB::table($this->description_table_name)->insert($description_data);
            }
            else{
                DB::table($this->description_table_name)->where('id',$validate->id)->update($description_data);
            }
        }
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
        $record = Blog::find($id);
        if ($record->image) {
            $imagePath = storage_path('app/public/' . $this->uploads_folder_name . '/' . $record->image);
            $thumbnailPath = storage_path('app/public/' . $this->uploads_folder_name . '/thumbnail/' . $record->image);

            if (file_exists($imagePath)) {
                unlink($imagePath); // Delete the main image
            }

            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath); // Delete the thumbnail
            }
        }
        $record->delete();
        
        DB::table($this->description_table_name)->where('blog_id',$id)->delete();
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' deleted successfully');
    }

    public function upload_editor_picture(Request $request){
        // echo '1324';
        // exit;
        $input=$request->all();
        
        if($request->file('upload')){
            $CKEditorFuncNum = $input['CKEditorFuncNum'];
            $image = $request->file('upload');
            
            $input['imagename'] = time().'.'.$image->extension();
            $fileresponse=$request->file('upload')->storeAs('public/ckeditor',$input['imagename']);
            // $destinationPath = storage_path('app/public/ckeditor/thumbnail');
            // $img = Image::make($image->path());
            // $img->resize(100, 100, function ($constraint) {
            //     $constraint->aspectRatio();
            // })->save($destinationPath.'/'.$input['imagename']);
            $image_name=$input['imagename'];
            $url=asset('storage/ckeditor/'.$image_name);
            $response=array();
            $response['CKEditorFuncNum']=$CKEditorFuncNum;
            $response['url']=$url;
            echo "<script>window.parent.CKEDITOR.tools.callFunction('".$CKEditorFuncNum."', '".$url."', 'Image uploaded successfully')</script>";
//            $re = "<script>window.parent.CKEDITOR.tools.callFunction(".$CKEditorFuncNum.", '".$url."', '".$msg."')</script>"; 
        }
//        else{
//            $re = '<script>alert("Unable to upload the file")</script>';
//        }
//        echo "<pre>".$image_name;
//        var_dump($_FILES);
//        exit;
    }

}