<?php

namespace App\Http\Controllers\Admin\Shift;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Exception;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Master\Shift;
use App\Models\Shift\ShiftChangeRequest;
use App\Models\Shift\ShiftChangeRequestLog;

/**
 * Shift Management → Shift Change Request: list, add, edit, view, approve/reject.
 * The workflow rules live in App\Models\Shift\ShiftChangeRequest.
 */
class ShiftChangeRequestController extends Controller
{
    private const BASE = 'shift-management/shift-change-request/';

    private $request;

    public function __construct()
    {
        $this->request = new ShiftChangeRequest();
    }

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = $this->request->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('request_status', fn ($row) => $this->statusBadge($row))
                    ->addColumn('current_shift_name', fn ($row) => e($row->current_shift_name ?: '-'))
                    ->addColumn('requested_shift_name', fn ($row) => e($row->requested_shift_name ?: '-'))
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

        return view('admin.shift.change-request.list', [
            'shiftList' => (new Shift())->getShift(),
            'statusList' => ShiftChangeRequest::STATUS_LABELS,
        ]);
    }

    public function add()
    {
        $isManager = ShiftChangeRequest::isManager();
        $employee = null;

        if (!$isManager) {
            $self = ShiftChangeRequest::employeeForUser();
            if (!$self) {
                Session::flash('error', 'Your login is not linked to an employee record');
                return redirect(admin_url(self::BASE . 'list'));
            }
            $employee = ShiftChangeRequest::employeeDetails($self->id);
        }

        return view('admin.shift.change-request.add', [
            'isManager' => $isManager,
            'employee' => $employee,
            'employeeList' => $isManager ? ShiftChangeRequest::selectableEmployees() : collect(),
            'shiftList' => (new Shift())->getShift(),
        ]);
    }

    public function store(Request $request)
    {
        $isManager = ShiftChangeRequest::isManager();

        // A normal employee can only raise a request for themselves.
        if ($isManager) {
            $employeeId = decryptId($request->employee_id);
        } else {
            $employeeId = ShiftChangeRequest::employeeForUser()->id ?? null;
        }

        $employee = $employeeId ? DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first() : null;

        $request->merge(['employee_ref' => $employee->id ?? null]);
        $validator = validator($request->all(), [
            'employee_ref' => ['required'],
            'requested_shift_id' => ['required'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'employee_ref.required' => 'Please select a valid employee',
            'requested_shift_id.required' => 'Please select the shift you need',
        ]);

        $validator->after(function ($v) use ($employee, $request) {
            if (!$employee) {
                return;
            }
            $shiftId = (int) decryptId($request->requested_shift_id);
            if (!ShiftChangeRequest::canRaiseFor($employee->id)) {
                $v->errors()->add('employee_id', 'You cannot raise a request for this employee');
            }
            if (!DB::table('masters_shift')->where('id', $shiftId)->where('trash', 'NO')->exists()) {
                $v->errors()->add('requested_shift_id', 'Please select a valid shift');
            } elseif ($shiftId === (int) $employee->shift) {
                $v->errors()->add('requested_shift_id', 'This is already the current shift');
            }
            if (ShiftChangeRequest::hasPendingFor($employee->id)) {
                $v->errors()->add('employee_id', 'A shift change request is already pending for this employee');
            }
            if (!ShiftChangeRequest::isManager() && !ShiftChangeRequest::reportingManagerUserId($employee)) {
                $v->errors()->add('employee_id', 'No reporting manager with a login is set for this employee, so the request cannot be routed for approval');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $scr = ShiftChangeRequest::raise($employee, (int) decryptId($request->requested_shift_id), $request->reason);

            Session::flash('success', $scr->is_direct
                ? 'Shift changed successfully (' . $scr->request_no . ')'
                : 'Shift change request ' . $scr->request_no . ' sent to the reporting manager for approval');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function edit(Request $request)
    {
        $scr = $this->findOrFail($request->id);
        if (!$scr->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        return view('admin.shift.change-request.edit', [
            'scr' => $scr,
            'employee' => ShiftChangeRequest::employeeDetails($scr->employee_id),
            'shiftList' => (new Shift())->getShift(),
        ]);
    }

    public function update(Request $request)
    {
        $scr = $this->findOrFail($request->id);
        if (!$scr->canEdit()) {
            Session::flash('error', 'Only a pending request can be edited, by the person who raised it');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $validator = validator($request->all(), [
            'requested_shift_id' => ['required'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'requested_shift_id.required' => 'Please select the shift you need',
        ]);
        $validator->after(function ($v) use ($request, $scr) {
            $shiftId = (int) decryptId($request->requested_shift_id);
            if (!DB::table('masters_shift')->where('id', $shiftId)->where('trash', 'NO')->exists()) {
                $v->errors()->add('requested_shift_id', 'Please select a valid shift');
            } elseif ($shiftId === (int) $scr->current_shift_id) {
                $v->errors()->add('requested_shift_id', 'This is already the current shift');
            }
        });

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $scr->revise((int) decryptId($request->requested_shift_id), $request->reason);
            Session::flash('success', 'Shift change request ' . $scr->request_no . ' updated');
            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function view(Request $request)
    {
        $scr = $this->findOrFail($request->id);
        if (!$scr->canView()) {
            abort(403);
        }

        return view('admin.shift.change-request.view', $this->detailData($scr) + ['approvalMode' => false]);
    }

    public function approval(Request $request)
    {
        $scr = $this->findOrFail($request->id);
        if (!$scr->canView()) {
            abort(403);
        }
        if (!$scr->canApprove()) {
            // Already decided, or not the approver: show the read-only page.
            if ($scr->request_status != ShiftChangeRequest::PENDING) {
                Session::flash('error', 'This request was already ' . strtolower(ShiftChangeRequest::STATUS_LABELS[$scr->request_status]));
            }
            return redirect(admin_url(self::BASE . 'view/' . encryptId($scr->id)));
        }

        return view('admin.shift.change-request.view', $this->detailData($scr) + ['approvalMode' => true]);
    }

    public function approvalSubmit(Request $request)
    {
        $scr = $this->findOrFail($request->id);
        if (!$scr->canApprove()) {
            Session::flash('error', 'This request is no longer waiting for your approval');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $decision = $request->has('approved') ? ShiftChangeRequest::APPROVED
            : ($request->has('rejected') ? ShiftChangeRequest::REJECTED : null);

        $validator = validator($request->all() + ['decision' => $decision], [
            'decision' => ['required', Rule::in([ShiftChangeRequest::APPROVED, ShiftChangeRequest::REJECTED])],
            'action_remarks' => [Rule::requiredIf($decision === ShiftChangeRequest::REJECTED), 'nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Please approve or reject the request',
            'action_remarks.required' => 'Please give a reason for rejecting',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $scr->decide($decision, $request->action_remarks);
            Session::flash('success', 'Shift change request ' . $scr->request_no . ' '
                . strtolower(ShiftChangeRequest::STATUS_LABELS[$decision]));
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
        if (!$employeeId || !ShiftChangeRequest::canRaiseFor($employeeId)) {
            return response()->json(['status' => 'error', 'msg' => 'Employee not found'], 404);
        }

        $emp = ShiftChangeRequest::employeeDetails($employeeId);
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
                'shift_name' => $this->shiftLabel($emp),
                'shift_id' => $emp->shift ? encryptId($emp->shift) : '',
                'reporting_manager' => $emp->reporting_manager_name ?: '-',
                'has_pending' => ShiftChangeRequest::hasPendingFor($employeeId),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function findOrFail($encryptedId): ShiftChangeRequest
    {
        $scr = ShiftChangeRequest::find(decryptId($encryptedId));
        if (!$scr) {
            abort(404);
        }
        return $scr;
    }

    private function detailData(ShiftChangeRequest $scr): array
    {
        $employee = ShiftChangeRequest::employeeDetails($scr->employee_id);

        return [
            'scr' => $scr,
            'employee' => $employee,
            'currentShift' => DB::table('masters_shift')->where('id', $scr->current_shift_id)->first(),
            'requestedShift' => DB::table('masters_shift')->where('id', $scr->requested_shift_id)->first(),
            'logs' => $scr->logs()->get(),
            'logLabels' => ShiftChangeRequestLog::LABELS,
            'statusBadge' => $this->statusBadge($scr),
        ];
    }

    private function statusBadge($row): string
    {
        $map = [
            ShiftChangeRequest::PENDING => 'warning',
            ShiftChangeRequest::APPROVED => 'success',
            ShiftChangeRequest::REJECTED => 'danger',
        ];
        $label = ShiftChangeRequest::STATUS_LABELS[$row->request_status] ?? '-';
        if ($row->is_direct && $row->request_status == ShiftChangeRequest::APPROVED) {
            $label .= ' (Direct)';
        }

        return '<span class="badge bg-' . ($map[$row->request_status] ?? 'secondary') . '">' . e($label) . '</span>';
    }

    private function shiftLabel($emp): string
    {
        if (!$emp->shift_name) {
            return '-';
        }
        return $emp->shift_name . ($emp->start_time
            ? ' (' . date('h:i A', strtotime($emp->start_time)) . ' - ' . date('h:i A', strtotime($emp->end_time)) . ')'
            : '');
    }
}
