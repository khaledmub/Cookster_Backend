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
    
class UserReviewsController extends Controller
{
    private $module_title_singular = 'User Review';
    private $module_title_plural = 'User Reviews';
    private $view_folder_name = 'user_reviews';
    private $uploads_folder_name = 'user_reviews';
    private $permission_initial = 'user-reviews';
    private $table_name = 'user_reviews';
    private $url_path = 'user_reviews';
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
        $data['users'] = DB::table('front_users')->join('entities', 'entities.id', '=', 'front_users.entity')->where('front_users.entity', 2)->orderBy('front_users.system_id', 'DESC')->select(['front_users.*', 'entities.name as entity_name'])->get();
        return view($this->view_folder_name.'.index',compact('data'))
            ->with('i', ($request->input('page', 1) - 1) * 5);
    }
    public function get_data_ajax(Request $request){
        $csrfToken = csrf_token();
        $input=$request->all();
        $query=DB::table($this->table_name.' as ur');
        $query->join('front_users as ru', 'ru.id', '=', 'ur.reviewed_user_id');
        $query->join('front_users as u', 'u.id', '=', 'ur.reviewer_id');
        if(isset($input['search']['value']) && $input['search']['value']!=''){
            $query->where('ur.review', 'LIKE', '%'.$input['search']['value'].'%');
        }
        if(isset($input['user']) && $input['user']!=''){
            $query->where('ur.reviewed_user_id', $input['user']);
        }
        if(isset($input['rating']) && $input['rating']!=''){
            $query->where('ur.rating', '>=', $input['rating'])
                    ->where('ur.rating', '<', $input['rating'] + 1);
        }
        if(isset($input['status']) && $input['status']!=''){
            $query->where('ur.status', $input['status']);
        }
        if(isset($input['start_date']) && $input['start_date']!=''){
            $query->whereDate('ur.created_at', '>=', $input['start_date']);
        }
        if(isset($input['end_date']) && $input['end_date']!=''){
            $query->whereDate('ur.created_at', '<=', $input['end_date']);
        }

        $query1 = clone $query;
        $query2 = clone $query;

        $totalData = $query1->select([$this->table_name.'.id'])->count();
        if(isset($input['length']) && $input['length']!=-1){
            $query2->offset($input['start'])->limit($input['length']);
        }
        $query2->orderBy('ur.system_id', 'DESC');
        $data = $query2->select(['ur.*', 'ru.name as reviewed_user_name', 'u.name as reviewer_name'])->get();
        $dataToPass=array();
        foreach($data as $sdata){
            $status_label = '';
            $actions_html = '';
            if($sdata->status == 1){
                $status_label = '<label class="badge bg-success">Approved</label>';
                $actions_html = '';
            }
            else{
                $status_label = '<label class="badge bg-warning">Pending</label>';
                $actions_html = '<a href="'.url('admin/user_review_status_update/'.$sdata->id.'/1').'" class="btn btn-success btn-sm me-2">Approve</a>';
                $actions_html .= '<a href="'.url('admin/user_review_status_update/'.$sdata->id.'/2').'" class="btn btn-danger btn-sm">Reject</a>';
            }

            if($sdata->is_visible==1){
                $checked = "checked";
            }
            else{
                $checked = "";
            }
            $visibility_html = '<div class="form-check form-switch form-switch-success" dir="ltr"><input class="form-check-input userReviewVisibilityChanger" data-id="'.$sdata->id.'" type="checkbox" role="switch" '.$checked.'></div>';

            $sub_array = array();
            $sub_array[] = AppHelper::id_formatter(7, $sdata->system_id);
            $sub_array[] = $sdata->reviewed_user_name;
            $sub_array[] = $sdata->reviewer_name;
            $sub_array[] = $sdata->rating;
            $sub_array[] = $sdata->review;
            $sub_array[] = $sdata->created_at;
            $sub_array[] = $visibility_html;
            $sub_array[] = $status_label;
            $sub_array[] = $actions_html;
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

    public function user_review_status_update($id, $status){
        if($status == 1){
            // Approve
            $data = array(
                'is_visible' => 1,
                'status' => 1
            );

            DB::table('user_reviews')->where('id', $id)->update($data);

            // Push Notification
            $user_review = DB::table('user_reviews as ur')
                ->join('front_users as ru', 'ru.id', '=', 'ur.reviewed_user_id')
                ->join('front_users as u', 'u.id', '=', 'ur.reviewer_id')
                ->where('ur.id', $id)
                ->select(['ur.*', 'ru.name as reviewed_user_name', 'u.name as reviewer_name', 'u.uuid as reviewer_uuid'])
                ->first();

            if($user_review && $user_review->reviewer_uuid){
                $deviceTokens = array($user_review->reviewer_uuid);
                $notification_data = [
                    'status' => true,
                ];
                $push_notification_text = [
                    'title' => 'User Review Approved',
                    'text' => 'Your review against this profile ('.$user_review->reviewed_user_name.') has been approved.',
                    'notification_data' => $notification_data
                ];
                AppHelper::send_push_notification($push_notification_text, $deviceTokens);
            }
        }
        else if($status == 2){
            // Reject

            // Push Notification
            $user_review = DB::table('user_reviews as ur')
                ->join('front_users as ru', 'ru.id', '=', 'ur.reviewed_user_id')
                ->join('front_users as u', 'u.id', '=', 'ur.reviewer_id')
                ->where('ur.id', $id)
                ->select(['ur.*', 'ru.name as reviewed_user_name', 'u.name as reviewer_name', 'u.uuid as reviewer_uuid'])
                ->first();

            if($user_review && $user_review->reviewer_uuid){
                $deviceTokens = array($user_review->reviewer_uuid);
                $notification_data = [
                    'status' => true,
                ];
                $push_notification_text = [
                    'title' => 'User Review Rejected',
                    'text' => 'Your review against this profile ('.$user_review->reviewed_user_name.') has been rejected.',
                    'notification_data' => $notification_data
                ];
                AppHelper::send_push_notification($push_notification_text, $deviceTokens);
            }

            DB::table('user_reviews')->where('id', $id)->delete();
            DB::table('notifications')->where('user_review_id', $id)->delete();
        }

        return redirect()->route($this->url_path.'.index')->with('success', 'Status updated successfully');
    }
}