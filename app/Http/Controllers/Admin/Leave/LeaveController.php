<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Exception;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveRequestLog;

/**
 * Leave Management → Apply Leave: list with balance, apply, edit, view,
 * confirm / cancel. The workflow lives in App\Models\Leave\LeaveRequest and
 * the balance rules in App\Models\Leave\LeaveBalance.
 */
class LeaveController extends Controller
{
    private const BASE = 'leave-management/leave/';

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = (new LeaveRequest())->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('leave_status', fn ($row) => $this->statusBadge($row))
                    ->addColumn('leave_type_name', fn ($row) => e($row->leave_type_name ?: '-'))
                    ->addColumn('duration', fn ($row) => e($row->durationLabel()))
                    ->addColumn('from_date', fn ($row) => $row->from_date->format('d-m-Y'))
                    ->addColumn('to_date', fn ($row) => $row->to_date->format('d-m-Y'))
                    ->addColumn('total_days', fn ($row) => LeaveRequest::formatDays($row->total_days))
                    ->addColumn('lop_days', fn ($row) => $row->lop_days > 0
                        ? '<span class="text-danger fw-semibold">' . LeaveRequest::formatDays($row->lop_days) . '</span>'
                        : '0')
                    ->addColumn('approver', fn ($row) => $row->approver_user_id ? e(getUsername($row->approver_user_id)) : '-')
                    ->addColumn('created_at', fn ($row) => Displaydatetimeformat($row->created_at))
                    ->addColumn('action', function ($row) {
                        $id = encryptId($row->id);
                        $btn = '<a href="' . admin_url(self::BASE . 'view/' . $id) . '" title="View"><i class="fa-solid fa-eye"></i></a> ';
                        if ($row->canEdit()) {
                            $btn .= '<a href="' . admin_url(self::BASE . 'edit/' . $id) . '" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a> ';
                        }
                        if ($row->canApprove()) {
                            $btn .= '<a href="' . admin_url(self::BASE . 'approval/' . $id) . '" title="Confirm / Cancel"><i class="fa-solid fa-circle-check text-success"></i></a>';
                        }
                        return $btn;
                    })
                    ->filter(function () {
                    })
                    ->rawColumns(['leave_status', 'lop_days', 'action'])
                    ->setFilteredRecords($data['filter_records'])
                    ->setTotalRecords($data['total_records'])
                    ->skipPaging()
                    ->make(true);
            } catch (Exception $ex) {
                report($ex);
                return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
            }
        }

        $me = LeaveRequest::employeeForUser();

        return view('admin.leave.leave.list', [
            'balances' => $me ? LeaveBalance::summary(LeaveRequest::employee($me->id)) : [],
            'hasEmployee' => (bool) $me,
            'leaveTypeList' => $this->leaveTypes(),
            'statusList' => LeaveRequest::STATUS_LABELS,
            'yearList' => range((int) date('Y') + 1, (int) date('Y') - 2),
        ]);
    }

    public function add()
    {
        $forOthers = LeaveRequest::canApplyForOthers();
        $employee = null;

        if (!$forOthers) {
            $self = LeaveRequest::employeeForUser();
            if (!$self) {
                Session::flash('error', 'Your login is not linked to an employee record');
                return redirect(admin_url(self::BASE . 'list'));
            }
            $employee = LeaveRequest::employeeDetails($self->id);
        }

        return view('admin.leave.leave.add', [
            'forOthers' => $forOthers,
            'employee' => $employee,
            'employeeList' => $forOthers ? LeaveRequest::selectableEmployees() : collect(),
            'leaveTypeList' => $this->leaveTypes(),
        ]);
    }

    public function store(Request $request)
    {
        // A normal employee can only apply for themselves.
        $employeeId = LeaveRequest::canApplyForOthers()
            ? decryptId($request->employee_id)
            : (LeaveRequest::employeeForUser()->id ?? null);
        $employee = $employeeId ? LeaveRequest::employee($employeeId) : null;

        [$validator, $failed, $resolved] = $this->validateLeave($request, $employee);

        if ($failed) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $leave = LeaveRequest::apply($employee, $resolved);

            $message = $leave->is_direct
                ? 'Leave ' . $leave->request_no . ' applied and confirmed'
                : 'Leave ' . $leave->request_no . ' sent to the reporting manager for approval';
            if ($leave->lop_days > 0) {
                $message .= '. ' . LeaveRequest::formatDays($leave->lop_days) . ' day(s) will be Loss of Pay';
            }
            Session::flash('success', $message);

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Request $request)
    {
        $leave = $this->findOrFail($request->id);
        if (!$leave->canEdit()) {
            Session::flash('error', 'Only a pending leave can be edited, by the person who applied it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        return view('admin.leave.leave.edit', [
            'leave' => $leave,
            'forOthers' => false,
            'employee' => LeaveRequest::employeeDetails($leave->employee_id),
            'leaveTypeList' => $this->leaveTypes($leave->leave_type_id),
        ]);
    }

    public function update(Request $request)
    {
        $leave = $this->findOrFail($request->id);
        if (!$leave->canEdit()) {
            Session::flash('error', 'Only a pending leave can be edited, by the person who applied it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $employee = LeaveRequest::employee($leave->employee_id);
        [$validator, $failed, $resolved] = $this->validateLeave($request, $employee, $leave);

        if ($failed) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $leave->revise($resolved);

            $message = 'Leave ' . $leave->request_no . ' updated';
            if ($leave->lop_days > 0) {
                $message .= '. ' . LeaveRequest::formatDays($leave->lop_days) . ' day(s) will be Loss of Pay';
            }
            Session::flash('success', $message);

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function view(Request $request)
    {
        $leave = $this->findOrFail($request->id);
        if (!$leave->canView()) {
            abort(403);
        }

        return view('admin.leave.leave.view', $this->detailData($leave) + ['approvalMode' => false]);
    }

    public function approval(Request $request)
    {
        $leave = $this->findOrFail($request->id);
        if (!$leave->canView()) {
            abort(403);
        }
        if (!$leave->canApprove()) {
            if ($leave->leave_status != LeaveRequest::PENDING) {
                Session::flash('error', 'This leave was already ' . strtolower(LeaveRequest::STATUS_LABELS[$leave->leave_status]));
            }
            return redirect(admin_url(self::BASE . 'view/' . encryptId($leave->id)));
        }

        return view('admin.leave.leave.view', $this->detailData($leave) + ['approvalMode' => true]);
    }

    public function approvalSubmit(Request $request)
    {
        $leave = $this->findOrFail($request->id);
        if (!$leave->canApprove()) {
            Session::flash('error', 'This leave is no longer waiting for your approval');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $decision = $request->has('confirmed') ? LeaveRequest::CONFIRMED
            : ($request->has('cancelled') ? LeaveRequest::CANCELLED : null);

        $validator = validator($request->all() + ['decision' => $decision], [
            'decision' => ['required', Rule::in([LeaveRequest::CONFIRMED, LeaveRequest::CANCELLED])],
            'action_remarks' => [Rule::requiredIf($decision === LeaveRequest::CANCELLED), 'nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Please confirm or cancel the leave',
            'action_remarks.required' => 'Please give a reason for cancelling',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $leave->decide($decision, $request->action_remarks);
            Session::flash('success', 'Leave ' . $leave->request_no . ' ' . strtolower(LeaveRequest::STATUS_LABELS[$decision]));
            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    /**
     * AJAX: employee details plus a live preview of the leave being entered —
     * day count, balance, and how many days are paid vs Loss of Pay.
     */
    public function calculate(Request $request)
    {
        if ($request->filled('leave_id')) {
            $leave = LeaveRequest::find(decryptId($request->leave_id));
            if (!$leave || !$leave->canEdit()) {
                return response()->json(['status' => 'error', 'msg' => 'Leave not found'], 404);
            }
            $employeeId = $leave->employee_id;
            $exceptId = $leave->id;
        } else {
            $employeeId = LeaveRequest::canApplyForOthers()
                ? decryptId($request->employee_id)
                : (LeaveRequest::employeeForUser()->id ?? null);
            $exceptId = null;
        }

        if (!$employeeId || (!$exceptId && !LeaveRequest::canApplyFor($employeeId))) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        $employee = LeaveRequest::employee($employeeId);
        $details = LeaveRequest::employeeDetails($employeeId);
        if (!$employee || !$details) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        $out = [
            'employee' => [
                'emp_id' => $details->emp_id,
                'emp_name' => $details->emp_name,
                'designation' => $details->designation ?: '-',
                'company_name' => $details->company_name ?: '-',
                'location_name' => $details->location_name ?: '-',
                'reporting_manager' => $details->reporting_manager_name ?: '-',
                'joining_date' => $employee->joining_date ? date('d-m-Y', strtotime($employee->joining_date)) : '-',
            ],
            'direct' => LeaveRequest::isDirectFor($employee),
            'balance' => null,
            'leave' => null,
            'error' => null,
        ];

        $leaveType = $request->filled('leave_type_id') ? LeaveRequest::leaveType(decryptId($request->leave_type_id)) : null;
        if (!$leaveType) {
            return response()->json(['status' => 'success', 'data' => $out]);
        }

        $durationType = (int) $request->duration_type;
        $resolved = in_array($durationType, array_keys(LeaveRequest::DURATION_LABELS), true)
            ? LeaveRequest::resolveDates($durationType, $request->from_date, $request->to_date)
            : ['errors' => ['duration_type' => '']];

        if ($resolved['errors']) {
            $out['error'] = trim(implode(' ', $resolved['errors'])) ?: null;
            $out['balance'] = $this->balancePayload(LeaveBalance::for($employee, $leaveType, (int) date('Y'), null, $exceptId));
            return response()->json(['status' => 'success', 'data' => $out]);
        }

        $split = LeaveBalance::split($employee, $leaveType, $resolved['from'], $resolved['total_days'], $exceptId);
        $out['balance'] = $this->balancePayload($split['balance']);
        $out['leave'] = [
            'total_days' => LeaveRequest::formatDays($resolved['total_days']),
            'paid_days' => LeaveRequest::formatDays($split['paid_days']),
            'lop_days' => LeaveRequest::formatDays($split['lop_days']),
            'has_lop' => $split['lop_days'] > 0,
        ];
        if ($clash = LeaveRequest::overlapping($employeeId, $resolved['from'], $resolved['to'], $exceptId)) {
            $out['error'] = 'Overlaps with leave ' . $clash->request_no . ' (' . $clash->dateRangeLabel() . ')';
        }

        return response()->json(['status' => 'success', 'data' => $out]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Validate an application. Returns [validator, failed?, resolved data
     * for LeaveRequest::apply()/revise()]. The resolved data is built by the
     * after() hook, so validation runs here before it is returned.
     */
    private function validateLeave(Request $request, $employee, ?LeaveRequest $existing = null): array
    {
        $resolved = [];

        $request->merge(['employee_ref' => $employee->id ?? null]);
        $validator = validator($request->all(), [
            'employee_ref' => ['required'],
            'leave_type_id' => ['required'],
            'duration_type' => ['required', Rule::in(array_keys(LeaveRequest::DURATION_LABELS))],
            'half_day_session' => [Rule::requiredIf((int) $request->duration_type === LeaveRequest::HALF_DAY), 'nullable', Rule::in(array_keys(LeaveRequest::SESSION_LABELS))],
            'from_date' => ['required'],
            'to_date' => [Rule::requiredIf((int) $request->duration_type === LeaveRequest::MULTI_DAY)],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'employee_ref.required' => 'Please select a valid employee',
            'leave_type_id.required' => 'Please select the leave type',
            'duration_type.required' => 'Please select the leave duration',
            'duration_type.in' => 'Please select the leave duration',
            'half_day_session.required' => 'Please select First Half or Second Half',
            'from_date.required' => 'Please select the leave date',
            'to_date.required' => 'Please select the To date',
            'reason.required' => 'Please enter the reason for leave',
        ]);

        $validator->after(function ($v) use ($request, $employee, $existing, &$resolved) {
            if (!$employee || $v->errors()->hasAny(['duration_type', 'from_date', 'to_date', 'leave_type_id'])) {
                return;
            }
            if (!$existing && !LeaveRequest::canApplyFor($employee->id)) {
                $v->errors()->add('employee_id', 'You cannot apply leave for this employee');
                return;
            }

            $leaveType = LeaveRequest::leaveType(decryptId($request->leave_type_id));
            if (!$leaveType || ($leaveType->status != 1 && (!$existing || $existing->leave_type_id != $leaveType->id))) {
                $v->errors()->add('leave_type_id', 'Please select a valid leave type');
                return;
            }

            $durationType = (int) $request->duration_type;
            $dates = LeaveRequest::resolveDates($durationType, $request->from_date, $request->to_date);
            foreach ($dates['errors'] as $field => $message) {
                $v->errors()->add($field, $message);
            }
            if ($dates['errors']) {
                return;
            }

            $year = (int) $dates['from']->year;
            if ($year < (int) date('Y') || $year > (int) date('Y') + 1) {
                $v->errors()->add('from_date', 'Leave can only be applied for this year or next year');
                return;
            }
            if ($employee->joining_date && $dates['from']->lt(Carbon::parse($employee->joining_date))) {
                $v->errors()->add('from_date', 'Leave cannot start before the joining date (' . date('d-m-Y', strtotime($employee->joining_date)) . ')');
                return;
            }
            if ($clash = LeaveRequest::overlapping($employee->id, $dates['from'], $dates['to'], $existing->id ?? null)) {
                $v->errors()->add('from_date', 'Overlaps with leave ' . $clash->request_no . ' (' . $clash->dateRangeLabel() . ')');
                return;
            }
            if (!LeaveRequest::isDirectFor($employee) && !LeaveRequest::reportingManagerUserId($employee)) {
                $v->errors()->add('employee_id', 'No reporting manager with a login is set for this employee, so the leave cannot be routed for approval');
                return;
            }

            $split = LeaveBalance::split($employee, $leaveType, $dates['from'], $dates['total_days'], $existing->id ?? null);

            $resolved = [
                'leave_type_id' => $leaveType->id,
                'duration_type' => $durationType,
                'half_day_session' => $request->half_day_session ? (int) $request->half_day_session : null,
                'from' => $dates['from'],
                'to' => $dates['to'],
                'total_days' => $dates['total_days'],
                'paid_days' => $split['paid_days'],
                'lop_days' => $split['lop_days'],
                'reason' => $request->reason,
            ];
        });

        $failed = $validator->fails();

        return [$validator, $failed, $resolved];
    }

    private function balancePayload(array $b): array
    {
        return [
            'leave_type_name' => $b['leave_type_name'],
            'year' => $b['year'],
            'monthly_credit' => LeaveRequest::formatDays($b['monthly_credit']),
            'credited' => LeaveRequest::formatDays($b['credited']),
            'used' => LeaveRequest::formatDays($b['used']),
            'pending' => LeaveRequest::formatDays($b['pending']),
            'available' => LeaveRequest::formatDays($b['available']),
        ];
    }

    private function leaveTypes($includeId = null)
    {
        return DB::table('masters_leave_type')
            ->where('trash', 'NO')
            ->where(fn ($q) => $q->where('status', 1)->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderBy('leave_type_name')
            ->get();
    }

    private function findOrFail($encryptedId): LeaveRequest
    {
        $leave = LeaveRequest::find(decryptId($encryptedId));
        if (!$leave) {
            abort(404);
        }
        return $leave;
    }

    private function detailData(LeaveRequest $leave): array
    {
        $employee = LeaveRequest::employee($leave->employee_id);
        $leaveType = LeaveRequest::leaveType($leave->leave_type_id);

        return [
            'leave' => $leave,
            'employee' => LeaveRequest::employeeDetails($leave->employee_id),
            'leaveType' => $leaveType,
            'balance' => $employee && $leaveType ? LeaveBalance::for($employee, $leaveType, (int) $leave->leave_year) : null,
            'logs' => $leave->logs()->get(),
            'logLabels' => LeaveRequestLog::LABELS,
            'statusBadge' => $this->statusBadge($leave),
        ];
    }

    private function statusBadge($row): string
    {
        $map = [
            LeaveRequest::PENDING => 'warning',
            LeaveRequest::CONFIRMED => 'success',
            LeaveRequest::CANCELLED => 'danger',
        ];
        $label = LeaveRequest::STATUS_LABELS[$row->leave_status] ?? '-';
        if ($row->is_direct && $row->leave_status == LeaveRequest::CONFIRMED) {
            $label .= ' (Direct)';
        }

        return '<span class="badge bg-' . ($map[$row->leave_status] ?? 'secondary') . '">' . e($label) . '</span>';
    }
}
