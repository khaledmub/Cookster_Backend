<?php

namespace App\Http\Controllers;

use App\Support\RewardDealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RewardDealsController extends Controller
{
    private string $module_title_singular = 'Reward Deal';

    private string $module_title_plural = 'Reward Deals';

    private string $view_folder_name = 'reward_deals';

    private string $permission_initial = 'reward-deals';

    private string $url_path = 'reward-deals';

    public function __construct(private readonly RewardDealService $deals)
    {
        $this->middleware('permission:'.$this->permission_initial.'-list|'.$this->permission_initial.'-edit', ['only' => ['index', 'show', 'get_data_ajax']]);
        $this->middleware('permission:'.$this->permission_initial.'-edit', ['only' => ['pause', 'blockPartner', 'unblockPartner']]);
    }

    public function index(Request $request): View
    {
        $data = [
            'module_title_singular' => $this->module_title_singular,
            'module_title_plural' => $this->module_title_plural,
            'permission_initial' => $this->permission_initial,
            'url_path' => $this->url_path,
        ];

        return view($this->view_folder_name.'.index', compact('data'));
    }

    public function get_data_ajax(Request $request): void
    {
        $input = $request->all();
        $query = DB::table('reward_deals as d')
            ->leftJoin('front_users as u', 'u.id', '=', 'd.partner_user_id')
            ->leftJoin('reward_partner_settings as s', 's.partner_user_id', '=', 'd.partner_user_id');

        if (! empty($input['search']['value'])) {
            $term = '%'.$input['search']['value'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('d.title', 'LIKE', $term)
                    ->orWhere('u.name', 'LIKE', $term)
                    ->orWhere('u.user_name', 'LIKE', $term);
            });
        }

        $totalData = (clone $query)->count();
        $query2 = clone $query;
        if (isset($input['length']) && (int) $input['length'] !== -1) {
            $query2->offset((int) $input['start'])->limit((int) $input['length']);
        }
        $rows = $query2->orderByDesc('d.created_at')
            ->select([
                'd.*',
                'u.name as partner_name',
                'u.user_name as partner_user_name',
                's.blocked_at as partner_blocked_at',
            ])
            ->get();

        $dataToPass = [];
        foreach ($rows as $row) {
            $status = match ($row->status) {
                'active' => '<label class="badge bg-success">Active</label>',
                'paused' => '<label class="badge bg-warning">Paused</label>',
                'exhausted' => '<label class="badge bg-secondary">Exhausted</label>',
                default => e($row->status),
            };
            if ($row->partner_blocked_at) {
                $status .= ' <label class="badge bg-danger">Partner blocked</label>';
            }

            $actions = '<a href="'.url('admin/reward-deals/'.$row->id).'" class="btn btn-info btn-sm me-1">View</a>';
            if ($row->status === 'active') {
                $actions .= '<form method="POST" action="'.url('admin/reward-deals/'.$row->id.'/pause').'" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-warning btn-sm me-1">Pause</button></form>';
            }
            if ($row->partner_blocked_at) {
                $actions .= '<form method="POST" action="'.url('admin/reward-deals/partners/'.$row->partner_user_id.'/unblock').'" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-success btn-sm">Unblock</button></form>';
            } else {
                $actions .= '<form method="POST" action="'.url('admin/reward-deals/partners/'.$row->partner_user_id.'/block').'" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-danger btn-sm">Block partner</button></form>';
            }

            $dataToPass[] = [
                e($row->partner_name ?: $row->partner_user_id),
                e($row->title),
                ((int) $row->quantity_remaining).' / '.((int) $row->quantity_total),
                $status,
                e((string) $row->created_at),
                $actions,
            ];
        }

        echo json_encode([
            'draw' => intval($input['draw'] ?? 1),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalData,
            'data' => $dataToPass,
        ]);
    }

    public function show(string $id): View|RedirectResponse
    {
        $deal = DB::table('reward_deals as d')
            ->leftJoin('front_users as u', 'u.id', '=', 'd.partner_user_id')
            ->leftJoin('reward_partner_settings as s', 's.partner_user_id', '=', 'd.partner_user_id')
            ->where('d.id', $id)
            ->select(['d.*', 'u.name as partner_name', 'u.user_name as partner_user_name', 's.blocked_at as partner_blocked_at'])
            ->first();

        if ($deal === null) {
            return redirect('admin/reward-deals')->with('error', 'Deal not found.');
        }

        $redemptions = DB::table('reward_redemptions as r')
            ->leftJoin('front_users as c', 'c.id', '=', 'r.client_user_id')
            ->where('r.deal_id', $id)
            ->orderByDesc('r.created_at')
            ->select(['r.*', 'c.name as client_name', 'c.user_name as client_user_name'])
            ->get();

        $data = [
            'module_title_singular' => $this->module_title_singular,
            'module_title_plural' => $this->module_title_plural,
            'permission_initial' => $this->permission_initial,
            'url_path' => $this->url_path,
            'deal' => $deal,
            'redemptions' => $redemptions,
        ];

        return view($this->view_folder_name.'.show', compact('data'));
    }

    public function pause(string $id): RedirectResponse
    {
        $ok = $this->deals->adminPauseDeal($id);

        return redirect('admin/reward-deals/'.$id)
            ->with($ok ? 'success' : 'error', $ok ? 'Deal paused.' : 'Deal is not active.');
    }

    public function blockPartner(string $partnerId): RedirectResponse
    {
        $this->deals->setPartnerBlocked($partnerId, true);
        $active = $this->deals->activeDeal($partnerId);
        if ($active !== null) {
            $this->deals->adminPauseDeal($active->id);
        }

        return back()->with('success', 'Partner blocked from rewards.');
    }

    public function unblockPartner(string $partnerId): RedirectResponse
    {
        $this->deals->setPartnerBlocked($partnerId, false);

        return back()->with('success', 'Partner unblocked.');
    }
}
