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

use App\Models\Attendance\CompoffRequest;
use App\Models\Attendance\CompoffRequestLog;

/**
 * Attendance Management → Compensatory Off: list, add, edit, view, approve/reject.
 * The workflow rules live in App\Models\Attendance\CompoffRequest.
 */
class CompoffController extends Controller
{
    private const BASE = 'attendance-management/compo-off/';

    private $request;

    public function __construct()
    {
        $this->request = new CompoffRequest();
    }

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = $this->request->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('request_status', fn ($row) => $this->statusBadge($row))
                    ->addColumn('worked_date', fn ($row) => Displaydateformat($row->worked_date))
                    ->addColumn('compoff_date', fn ($row) => Displaydateformat($row->compoff_date))
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

        return view('admin.attendance.compoff.list', [
            'statusList' => CompoffRequest::STATUS_LABELS,
        ]);
    }

    public function add()
    {
        $isManager = CompoffRequest::isManager();
        $employee = null;

        if (!$isManager) {
            $self = CompoffRequest::employeeForUser();
            if (!$self) {
                Session::flash('error', 'Your login is not linked to an employee record');
                return redirect(admin_url(self::BASE . 'list'));
            }
            $employee = CompoffRequest::employeeDetails($self->id);
        }

        return view('admin.attendance.compoff.add', [
            'isManager' => $isManager,
            'employee' => $employee,
            'employeeList' => $isManager ? CompoffRequest::selectableEmployees() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $isManager = CompoffRequest::isManager();

        // A normal employee can only raise a request for themselves.
        if ($isManager) {
            $employeeId = decryptId($request->employee_id);
        } else {
            $employeeId = CompoffRequest::employeeForUser()->id ?? null;
        }

        $employee = $employeeId ? DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first() : null;

        $request->merge(['employee_ref' => $employee->id ?? null]);
        $validator = validator($request->all(), [
            'employee_ref' => ['required'],
            'worked_date' => ['required', 'date', 'before_or_equal:today'],
            'compoff_date' => ['required', 'date', 'after:worked_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'employee_ref.required' => 'Please select a valid employee',
            'worked_date.required' => 'Please select the date worked',
            'worked_date.before_or_equal' => 'The worked date cannot be in the future',
            'compoff_date.required' => 'Please select the compensatory off date',
            'compoff_date.after' => 'The compensatory off date must be after the worked date',
        ]);

        $validator->after(function ($v) use ($employee, $request) {
            if (!$employee) {
                return;
            }
            if (!CompoffRequest::canRaiseFor($employee->id)) {
                $v->errors()->add('employee_id', 'You cannot raise a request for this employee');
            }
            if ($request->worked_date && CompoffRequest::hasPendingFor($employee->id, $request->worked_date)) {
                $v->errors()->add('worked_date', 'A compensatory off request is already pending for this employee against this worked date');
            }
            if (!CompoffRequest::isManager() && !CompoffRequest::reportingManagerUserId($employee)) {
                $v->errors()->add('employee_id', 'No reporting manager with a login is set for this employee, so the request cannot be routed for approval');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $co = CompoffRequest::raise($employee, $request->worked_date, $request->compoff_date, $request->reason);

            Session::flash('success', $co->is_direct
                ? 'Compensatory off recorded successfully (' . $co->request_no . ')'
                : 'Compensatory off request ' . $co->request_no . ' sent to the reporting manager for approval');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Request $request)
    {
        $co = $this->findOrFail($request->id);
        if (!$co->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        return view('admin.attendance.compoff.edit', [
            'co' => $co,
            'employee' => CompoffRequest::employeeDetails($co->employee_id),
        ]);
    }

    public function update(Request $request)
    {
        $co = $this->findOrFail($request->id);
        if (!$co->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $validator = validator($request->all(), [
            'worked_date' => ['required', 'date', 'before_or_equal:today'],
            'compoff_date' => ['required', 'date', 'after:worked_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'worked_date.required' => 'Please select the date worked',
            'worked_date.before_or_equal' => 'The worked date cannot be in the future',
            'compoff_date.required' => 'Please select the compensatory off date',
            'compoff_date.after' => 'The compensatory off date must be after the worked date',
        ]);
        $validator->after(function ($v) use ($request, $co) {
            if ($request->worked_date && CompoffRequest::hasPendingFor($co->employee_id, $request->worked_date, $co->id)) {
                $v->errors()->add('worked_date', 'A compensatory off request is already pending for this employee against this worked date');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $co->revise($request->worked_date, $request->compoff_date, $request->reason);
            Session::flash('success', 'Compensatory off request ' . $co->request_no . ' updated');
            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function view(Request $request)
    {
        $co = $this->findOrFail($request->id);
        if (!$co->canView()) {
            abort(403);
        }

        return view('admin.attendance.compoff.view', $this->detailData($co) + ['approvalMode' => false]);
    }

    public function approval(Request $request)
    {
        $co = $this->findOrFail($request->id);
        if (!$co->canView()) {
            abort(403);
        }
        if (!$co->canApprove()) {
            if ($co->request_status != CompoffRequest::PENDING) {
                Session::flash('error', 'This request was already ' . strtolower(CompoffRequest::STATUS_LABELS[$co->request_status]));
            }
            return redirect(admin_url(self::BASE . 'view/' . encryptId($co->id)));
        }

        return view('admin.attendance.compoff.view', $this->detailData($co) + ['approvalMode' => true]);
    }

    public function approvalSubmit(Request $request)
    {
        $co = $this->findOrFail($request->id);
        if (!$co->canApprove()) {
            Session::flash('error', 'This request is no longer waiting for your approval');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $decision = $request->has('approved') ? CompoffRequest::APPROVED
            : ($request->has('rejected') ? CompoffRequest::REJECTED : null);

        $validator = validator($request->all() + ['decision' => $decision], [
            'decision' => ['required', Rule::in([CompoffRequest::APPROVED, CompoffRequest::REJECTED])],
            'action_remarks' => [Rule::requiredIf($decision === CompoffRequest::REJECTED), 'nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Please approve or reject the request',
            'action_remarks.required' => 'Please give a reason for rejecting',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $co->decide($decision, $request->action_remarks);
            Session::flash('success', 'Compensatory off request ' . $co->request_no . ' '
                . strtolower(CompoffRequest::STATUS_LABELS[$decision]));
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
        if (!$employeeId || !CompoffRequest::canRaiseFor($employeeId)) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        $emp = CompoffRequest::employeeDetails($employeeId);
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

    private function findOrFail($encryptedId): CompoffRequest
    {
        $co = CompoffRequest::find(decryptId($encryptedId));
        if (!$co) {
            abort(404);
        }
        return $co;
    }

    private function detailData(CompoffRequest $co): array
    {
        return [
            'co' => $co,
            'employee' => CompoffRequest::employeeDetails($co->employee_id),
            'logs' => $co->logs()->get(),
            'logLabels' => CompoffRequestLog::LABELS,
            'statusBadge' => $this->statusBadge($co),
        ];
    }

    private function statusBadge($row): string
    {
        $map = [
            CompoffRequest::PENDING => 'warning',
            CompoffRequest::APPROVED => 'success',
            CompoffRequest::REJECTED => 'danger',
        ];
        $label = CompoffRequest::STATUS_LABELS[$row->request_status] ?? '-';
        if ($row->is_direct && $row->request_status == CompoffRequest::APPROVED) {
            $label .= ' (Direct)';
        }

        return '<span class="badge bg-' . ($map[$row->request_status] ?? 'secondary') . '">' . e($label) . '</span>';
    }
}
