<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Exception;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Attendance\PermissionRequest;
use App\Models\Attendance\PermissionRequestLog;

/**
 * Attendance Management → Permission: list, add, edit, view, approve/reject.
 * The workflow rules live in App\Models\Attendance\PermissionRequest.
 */
class PermissionController extends Controller
{
    private const BASE = 'attendance-management/permission/';

    private $request;

    public function __construct()
    {
        $this->request = new PermissionRequest();
    }

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = $this->request->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('request_status', fn ($row) => $this->statusBadge($row))
                    ->addColumn('permission_date', fn ($row) => Displaydateformat($row->permission_date))
                    ->addColumn('time_range', fn ($row) => date('h:i A', strtotime($row->time_from)) . ' - ' . date('h:i A', strtotime($row->time_to)))
                    ->addColumn('approver', fn ($row) => $row->approver_user_id ? e(getUsername($row->approver_user_id)) : '-')
                    ->addColumn('created_by', fn ($row) => e(getUsername($row->created_by)))
                    ->addColumn('created_at', fn ($row) => Displaydatetimeformat($row->created_at))
                    ->addColumn('action', function ($row) {
                        $id = encryptId($row->id);
                        $btn = '<a href="' . admin_url(self::BASE . 'view/' . $id) . '" title="View"><i class="fa-solid fa-eye"></i></a> ';
                        if ($row->canEdit()) {
                            $btn .= '<a href="' . admin_url(self::BASE . 'edit/' . $id) . '" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a> ';
                        }
                        if ($row->canApprove()) {
                            $btn .= '<a href="' . admin_url(self::BASE . 'approval/' . $id) . '" title="Approve / Reject"><i class="fa-solid fa-circle-check text-success"></i></a>';
                        }
                        return $btn;
                    })
                    ->filter(function () {
                    })
                    ->rawColumns(['request_status', 'action'])
                    ->setFilteredRecords($data['filter_records'])
                    ->setTotalRecords($data['total_records'])
                    ->skipPaging()
                    ->make(true);
            } catch (Exception $ex) {
                report($ex);
                return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
            }
        }

        return view('admin.attendance.permission.list', [
            'statusList' => PermissionRequest::STATUS_LABELS,
        ]);
    }

    public function add()
    {
        $isManager = PermissionRequest::isManager();
        $employee = null;

        if (!$isManager) {
            $self = PermissionRequest::employeeForUser();
            if (!$self) {
                Session::flash('error', 'Your login is not linked to an employee record');
                return redirect(admin_url(self::BASE . 'list'));
            }
            $employee = PermissionRequest::employeeDetails($self->id);
        }

        return view('admin.attendance.permission.add', [
            'isManager' => $isManager,
            'employee' => $employee,
            'employeeList' => $isManager ? PermissionRequest::selectableEmployees() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $isManager = PermissionRequest::isManager();

        // A normal employee can only raise a request for themselves.
        if ($isManager) {
            $employeeId = decryptId($request->employee_id);
        } else {
            $employeeId = PermissionRequest::employeeForUser()->id ?? null;
        }

        $employee = $employeeId ? DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first() : null;

        $request->merge(['employee_ref' => $employee->id ?? null]);
        $validator = validator($request->all(), [
            'employee_ref' => ['required'],
            'permission_date' => ['required', 'date'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'employee_ref.required' => 'Please select a valid employee',
            'permission_date.required' => 'Please select the permission date',
            'time_from.required' => 'Please select the from time',
            'time_to.required' => 'Please select the to time',
        ]);

        $validator->after(function ($v) use ($employee, $request) {
            if (!$employee) {
                return;
            }
            if (!PermissionRequest::canRaiseFor($employee->id)) {
                $v->errors()->add('employee_id', 'You cannot raise a request for this employee');
            }
            if ($request->time_from && $request->time_to && strtotime($request->time_to) <= strtotime($request->time_from)) {
                $v->errors()->add('time_to', 'To time must be after the from time');
            }
            if ($request->permission_date && PermissionRequest::hasPendingFor($employee->id, $request->permission_date)) {
                $v->errors()->add('permission_date', 'A permission request is already pending for this employee on this date');
            }
            if (!PermissionRequest::isManager() && !PermissionRequest::reportingManagerUserId($employee)) {
                $v->errors()->add('employee_id', 'No reporting manager with a login is set for this employee, so the request cannot be routed for approval');
            }
            if ($employee && $request->permission_date && $request->time_from && $request->time_to && !$v->errors()->hasAny(['permission_date', 'time_from', 'time_to'])) {
                $this->checkMonthlyQuota($v, $employee->id, $request->permission_date, $request->time_from, $request->time_to);
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $pr = PermissionRequest::raise($employee, $request->permission_date, $request->time_from, $request->time_to, $request->reason);

            Session::flash('success', $pr->is_direct
                ? 'Permission recorded successfully (' . $pr->request_no . ')'
                : 'Permission request ' . $pr->request_no . ' sent to the reporting manager for approval');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Request $request)
    {
        $pr = $this->findOrFail($request->id);
        if (!$pr->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        return view('admin.attendance.permission.edit', [
            'pr' => $pr,
            'employee' => PermissionRequest::employeeDetails($pr->employee_id),
        ]);
    }

    public function update(Request $request)
    {
        $pr = $this->findOrFail($request->id);
        if (!$pr->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $validator = validator($request->all(), [
            'permission_date' => ['required', 'date'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'permission_date.required' => 'Please select the permission date',
            'time_from.required' => 'Please select the from time',
            'time_to.required' => 'Please select the to time',
        ]);
        $validator->after(function ($v) use ($request, $pr) {
            if ($request->time_from && $request->time_to && strtotime($request->time_to) <= strtotime($request->time_from)) {
                $v->errors()->add('time_to', 'To time must be after the from time');
            }
            if ($request->permission_date && PermissionRequest::hasPendingFor($pr->employee_id, $request->permission_date, $pr->id)) {
                $v->errors()->add('permission_date', 'A permission request is already pending for this employee on this date');
            }
            if ($request->permission_date && $request->time_from && $request->time_to && !$v->errors()->hasAny(['permission_date', 'time_from', 'time_to'])) {
                $this->checkMonthlyQuota($v, $pr->employee_id, $request->permission_date, $request->time_from, $request->time_to, $pr->id);
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $pr->revise($request->permission_date, $request->time_from, $request->time_to, $request->reason);
            Session::flash('success', 'Permission request ' . $pr->request_no . ' updated');
            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function view(Request $request)
    {
        $pr = $this->findOrFail($request->id);
        if (!$pr->canView()) {
            abort(403);
        }

        return view('admin.attendance.permission.view', $this->detailData($pr) + ['approvalMode' => false]);
    }

    public function approval(Request $request)
    {
        $pr = $this->findOrFail($request->id);
        if (!$pr->canView()) {
            abort(403);
        }
        if (!$pr->canApprove()) {
            if ($pr->request_status != PermissionRequest::PENDING) {
                Session::flash('error', 'This request was already ' . strtolower(PermissionRequest::STATUS_LABELS[$pr->request_status]));
            }
            return redirect(admin_url(self::BASE . 'view/' . encryptId($pr->id)));
        }

        return view('admin.attendance.permission.view', $this->detailData($pr) + ['approvalMode' => true]);
    }

    public function approvalSubmit(Request $request)
    {
        $pr = $this->findOrFail($request->id);
        if (!$pr->canApprove()) {
            Session::flash('error', 'This request is no longer waiting for your approval');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $decision = $request->has('approved') ? PermissionRequest::APPROVED
            : ($request->has('rejected') ? PermissionRequest::REJECTED : null);

        $validator = validator($request->all() + ['decision' => $decision], [
            'decision' => ['required', Rule::in([PermissionRequest::APPROVED, PermissionRequest::REJECTED])],
            'action_remarks' => [Rule::requiredIf($decision === PermissionRequest::REJECTED), 'nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Please approve or reject the request',
            'action_remarks.required' => 'Please give a reason for rejecting',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $pr->decide($decision, $request->action_remarks);
            Session::flash('success', 'Permission request ' . $pr->request_no . ' '
                . strtolower(PermissionRequest::STATUS_LABELS[$decision]));
            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    /** AJAX: employee details for the manager's employee dropdown. */
    public function employeeDetails(Request $request)
    {
        $employeeId = decryptId($request->employee_id);
        if (!$employeeId || !PermissionRequest::canRaiseFor($employeeId)) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        $emp = PermissionRequest::employeeDetails($employeeId);
        if (!$emp) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'emp_id' => $emp->emp_id,
                'emp_name' => $emp->emp_name,
                'designation' => $emp->designation ?: '-',
                'company_name' => $emp->company_name ?: '-',
                'location_name' => $emp->location_name ?: '-',
                'reporting_manager' => $emp->reporting_manager_name ?: '-',
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** Enforces the MONTHLY_REQUEST_LIMIT / MONTHLY_HOURS_LIMIT quota for the request's month. */
    private function checkMonthlyQuota($validator, int $employeeId, string $permissionDate, string $timeFrom, string $timeTo, $exceptId = null): void
    {
        $usage = PermissionRequest::monthlyUsage($employeeId, $permissionDate, $exceptId);
        $newDuration = PermissionRequest::calculateDuration($timeFrom, $timeTo);

        if ($usage['count'] + 1 > PermissionRequest::MONTHLY_REQUEST_LIMIT) {
            $validator->errors()->add('permission_date',
                'Only ' . PermissionRequest::MONTHLY_REQUEST_LIMIT . ' permission requests are allowed per calendar month');
            return;
        }

        if (round($usage['hours'] + $newDuration, 2) > PermissionRequest::MONTHLY_HOURS_LIMIT) {
            $validator->errors()->add('time_to',
                'Total permission hours for the month cannot exceed ' . PermissionRequest::MONTHLY_HOURS_LIMIT . ' hours ('
                . $usage['hours'] . ' hrs already used this month)');
        }
    }

    private function findOrFail($encryptedId): PermissionRequest
    {
        $pr = PermissionRequest::find(decryptId($encryptedId));
        if (!$pr) {
            abort(404);
        }
        return $pr;
    }

    private function detailData(PermissionRequest $pr): array
    {
        return [
            'pr' => $pr,
            'employee' => PermissionRequest::employeeDetails($pr->employee_id),
            'logs' => $pr->logs()->get(),
            'logLabels' => PermissionRequestLog::LABELS,
            'statusBadge' => $this->statusBadge($pr),
        ];
    }

    private function statusBadge($row): string
    {
        $map = [
            PermissionRequest::PENDING => 'warning',
            PermissionRequest::APPROVED => 'success',
            PermissionRequest::REJECTED => 'danger',
        ];
        $label = PermissionRequest::STATUS_LABELS[$row->request_status] ?? '-';
        if ($row->is_direct && $row->request_status == PermissionRequest::APPROVED) {
            $label .= ' (Direct)';
        }

        return '<span class="badge bg-' . ($map[$row->request_status] ?? 'secondary') . '">' . e($label) . '</span>';
    }
}
