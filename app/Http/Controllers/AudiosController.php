<?php
    
namespace App\Http\Controllers;
    
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Audio;
use DB;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use \App\Helpers\AppHelper;
use Image;
use App\Services\S3Service;
use getID3;
    
class AudiosController extends Controller
{
    private $module_title_singular = 'Audio';
    private $module_title_plural = 'Audios';
    private $view_folder_name = 'audios';
    private $uploads_folder_name = 'audios';
    private $permission_initial = 'audios';
    private $table_name = 'audios';
    private $description_table_name = 'audios_description';
    private $url_path = 'audios';
    function __construct(private S3Service $s3Service)
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
        $query->join($this->description_table_name, $this->description_table_name.'.audio_id', '=', $this->table_name.'.id');
        $query->join('site_languages', $this->description_table_name.'.language_id', '=', 'site_languages.id');
        $query->where('site_languages.is_default', 1);
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where(function ($q) use ($input) {
                $searchValue = '%' . $input['search']['value'] . '%';
                $q->where($this->description_table_name.'.title', 'LIKE', $searchValue)
                  ->orWhere($this->description_table_name.'.artist', 'LIKE', $searchValue);
            });
        }
        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy($this->table_name.'.id', 'ASC');
        $data = $query2->select([$this->table_name.'.*', $this->description_table_name.'.title', $this->description_table_name.'.artist'])->get();
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
            $sub_array[]=$sdata->artist;
            $sub_array[]=$sdata->duration;
            $sub_array[]='<img style="max-height: 100px; max-width: 50px;" src="'.env('AWS_CLOUD_FRONT_PATH')."audios/".$sdata->image.'">';
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
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'file' => 'required|mimetypes:audio/mpeg,audio/wav,audio/aac,audio/ogg,audio/x-ms-wma,audio/mp4,audio/flac|max:10240',
            'status' => 'required'
        ], $customMessages);
        $input = $request->all();
        if ($request->file('image')) {
            $image = $request->file('image');

            // Create unique name
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            // Initialize S3 service

            // Upload original image to S3
            $this->s3Service->storeFile('audios/' . $imageName, file_get_contents($image));

            // Generate thumbnail locally
            $thumbnailLocalPath = storage_path('app/temp-thumbnails');
            if (!file_exists($thumbnailLocalPath)) {
                mkdir($thumbnailLocalPath, 0755, true);
            }

            $thumbnailFullLocalPath = $thumbnailLocalPath . '/' . $imageName;

            $img = Image::read($image->getRealPath());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($thumbnailFullLocalPath);

            // Upload thumbnail to S3
            $this->s3Service->storeFile('audios/thumbnail/' . $imageName, file_get_contents($thumbnailFullLocalPath));

            // Delete local thumbnail
            if (file_exists($thumbnailFullLocalPath)) {
                unlink($thumbnailFullLocalPath);
            }

            // Save only the name or optionally full S3 URLs
            $input['image'] = $imageName;

            // Optional: Save full URL
            // $data['image_url'] = Storage::disk('s3')->url('videos/' . $imageName);
            // $data['thumbnail_url'] = Storage::disk('s3')->url('videos/thumbnail/' . $imageName);
        }
        if ($request->file('file')) {
            $file = $request->file('file');
            $file_name = time() . '1.' . $file->extension();

            // Get the file content
            $contents = file_get_contents($file->getRealPath());

            // Get audio duration using getID3
            $getID3 = new getID3;
            $fileInfo = $getID3->analyze($file->getRealPath());

            // Make sure 'playtime_seconds' exists
            $duration = isset($fileInfo['playtime_seconds']) ? $fileInfo['playtime_seconds'] : null;

            // Optional: Format duration (e.g., mm:ss)
            $formattedDuration = gmdate("i:s", (int)$duration);
            // Initialize S3 service
            $s3Service = app(S3Service::class);

            // Upload the file to S3
            $uploaded = $s3Service->storeFile('audios/' . $file_name, $contents);

            if ($uploaded) {
                $input['file'] = $file_name;  // Save the filename or S3 path if you need
            } else {

            }
        }

        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $input['id'] = (string) \Str::uuid();
        $input['duration'] = $formattedDuration;
        $record = Audio::create($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'id' => (string) \Str::uuid(),
                'audio_id' => $input['id'],
                'language_id' => $language->id,
                'title' => $input['title'][$language->id],
                'artist' => $input['artist'][$language->id]
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
        $m_data = Audio::find($id);
        $m_data_descriptions = DB::table($this->description_table_name)->select()->where('audio_id',$id)->get()->keyBy('language_id');
        $data = array();
        $data['module_title_singular'] = $this->module_title_singular;
        $data['module_title_plural'] = $this->module_title_plural;
        $data['uploads_folder_name'] = $this->uploads_folder_name;
        $data['url_path'] = $this->url_path;
        $data['site_languages'] = DB::table('site_languages')->where('status',1)->orderBy('sort_order', 'ASC')->get();
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'file' => 'nullable|mimetypes:audio/mpeg,audio/wav,audio/aac,audio/ogg,audio/x-ms-wma,audio/mp4,audio/flac|max:10240',
            'status' => 'required'
        ], $customMessages);

        $input = $request->all();
        if ($request->file('image')) {
            $image = $request->file('image');

            // Create unique name
            $imageName = time() . '.' . $image->getClientOriginalExtension();

            // Initialize S3 service

            // Upload original image to S3
            $this->s3Service->storeFile('audios/' . $imageName, file_get_contents($image));

            // Generate thumbnail locally
            $thumbnailLocalPath = storage_path('app/temp-thumbnails');
            if (!file_exists($thumbnailLocalPath)) {
                mkdir($thumbnailLocalPath, 0755, true);
            }

            $thumbnailFullLocalPath = $thumbnailLocalPath . '/' . $imageName;

            $img = Image::read($image->getRealPath());
            $img->resize(100, 100, function ($constraint) {
                $constraint->aspectRatio();
            })->save($thumbnailFullLocalPath);

            // Upload thumbnail to S3
            $this->s3Service->storeFile('audios/thumbnail/' . $imageName, file_get_contents($thumbnailFullLocalPath));

            // Delete local thumbnail
            if (file_exists($thumbnailFullLocalPath)) {
                unlink($thumbnailFullLocalPath);
            }

            // Save only the name or optionally full S3 URLs
            $input['image'] = $imageName;

            // Optional: Save full URL
            // $data['image_url'] = Storage::disk('s3')->url('videos/' . $imageName);
            // $data['thumbnail_url'] = Storage::disk('s3')->url('videos/thumbnail/' . $imageName);
        }
        if ($request->file('file')) {
            $file = $request->file('file');
            $file_name = time() . '1.' . $file->extension();

            // Get the file content
            $contents = file_get_contents($file->getRealPath());

            // Get audio duration using getID3
            $getID3 = new getID3;
            $fileInfo = $getID3->analyze($file->getRealPath());

            // Make sure 'playtime_seconds' exists
            $duration = isset($fileInfo['playtime_seconds']) ? $fileInfo['playtime_seconds'] : null;

            // Optional: Format duration (e.g., mm:ss)
            $formattedDuration = gmdate("i:s", (int)$duration);
            $input['duration'] = $formattedDuration;
            // Initialize S3 service
            $s3Service = app(S3Service::class);

            // Upload the file to S3
            $uploaded = $s3Service->storeFile('audios/' . $file_name, $contents);

            if ($uploaded) {
                $input['file'] = $file_name;  // Save the filename or S3 path if you need
            } else {

            }
        }
        if(isset($input['status']) && $input['status']==1){
            $input['status']=1;
        }
        else{
            $input['status']=0;
        }
        $m_data = Audio::find($id);
        $m_data->update($input);

        foreach ($site_languages as $language) {
            $description_data = array(
                'audio_id' => $id,
                'language_id' => $language->id,
                'title' => $input['title'][$language->id],
                'artist' => $input['artist'][$language->id]
            );
            $validate = DB::table($this->description_table_name)->where('audio_id',$id)->where('language_id', $language->id)->first();
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
        $record = Audio::find($id);
        $s3 = app(S3Service::class);
        if (!empty($record->file)) {
            $s3->deleteFile('audios/' . $record->file);
        }

        if (!empty($record->image)) {
            $s3->deleteFile('audios/' . $record->image); // original image
            $s3->deleteFile('audios/thumbnail/' . $record->image); // thumbnail
        }

        $record->delete();
        
        DB::table($this->description_table_name)->where('audio_id',$id)->delete();
        return redirect()->route($this->url_path.'.index')->with('success',$this->module_title_singular.' deleted successfully');
    }
}