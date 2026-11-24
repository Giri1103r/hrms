<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Exception;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Attendance\AttendanceRequest;
use App\Models\Attendance\AttendanceRequestLog;

/**
 * Attendance Management → Daily Attendance: an employee marks their own
 * attendance; a Manager (or Admin) also sees their direct reports here and
 * can mark attendance for any of them in the same submit. Approving an
 * employee's self-marked attendance happens in the separate Attendance
 * Approval module. The workflow rules live in App\Models\Attendance\AttendanceRequest.
 */
class AttendanceController extends Controller
{
    private const BASE = 'attendance-management/daily-attendance/';

    private $request;

    public function __construct()
    {
        $this->request = new AttendanceRequest();
    }

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = $this->request->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('request_status', fn ($row) => $this->statusBadge($row))
                    ->addColumn('attendance_date', fn ($row) => Displaydateformat($row->attendance_date))
                    ->addColumn('attendance_type', fn ($row) => AttendanceRequest::TYPE_LABELS[$row->attendance_type] ?? '-')
                    ->addColumn('approver', fn ($row) => $row->approver_user_id ? e(getUsername($row->approver_user_id)) : '-')
                    ->addColumn('created_by', fn ($row) => e(getUsername($row->created_by)))
                    ->addColumn('created_at', fn ($row) => Displaydatetimeformat($row->created_at))
                    ->addColumn('action', function ($row) {
                        $id = encryptId($row->id);
                        return '<a href="' . admin_url(self::BASE . 'view/' . $id) . '" title="View"><i class="fa-solid fa-eye"></i></a>';
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

        return view('admin.attendance.daily.list', [
            'statusList' => AttendanceRequest::STATUS_LABELS,
        ]);
    }

    public function add()
    {
        $self = AttendanceRequest::employeeForUser();
        if (!$self) {
            Session::flash('error', 'Your login is not linked to an employee record');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $isManager = AttendanceRequest::isManager();

        return view('admin.attendance.daily.add', [
            'self' => AttendanceRequest::employeeDetails($self->id),
            'isManager' => $isManager,
            'reports' => $isManager ? AttendanceRequest::reportingEmployees() : collect(),
            'typeList' => AttendanceRequest::TYPE_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $self = AttendanceRequest::employeeForUser();
        if (!$self) {
            Session::flash('error', 'Your login is not linked to an employee record');
            return redirect(admin_url(self::BASE . 'list'));
        }

        $isManager = AttendanceRequest::isManager();
        $reportIds = $isManager ? AttendanceRequest::reportingEmployees()->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        $allowedIds = array_merge([(int) $self->id], $reportIds);

        $selected = collect($request->input('employee_ids', []))
            ->map(fn ($id) => (int) decryptId($id))
            ->filter(fn ($id) => in_array($id, $allowedIds, true))
            ->unique()
            ->values()
            ->all();

        $validator = validator($request->all() + ['selected_employee_ids' => $selected], [
            'selected_employee_ids' => ['required', 'array', 'min:1'],
            'attendance_date' => ['required', 'date', 'before_or_equal:today'],
            'attendance_type' => ['required', 'in:' . implode(',', array_keys(AttendanceRequest::TYPE_LABELS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'selected_employee_ids.required' => 'Please select at least one employee',
            'selected_employee_ids.min' => 'Please select at least one employee',
            'attendance_date.required' => 'Please select the attendance date',
            'attendance_date.before_or_equal' => 'Attendance cannot be marked for a future date',
            'attendance_type.required' => 'Please select the attendance type',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $result = AttendanceRequest::markBulk(
                $selected,
                $request->attendance_date,
                (int) $request->attendance_type,
                $request->reason,
                $self->id
            );

            $messages = [];
            if (count($result['created'])) {
                $messages[] = count($result['created']) . ' attendance entr' . (count($result['created']) === 1 ? 'y' : 'ies') . ' marked successfully';
            }
            if (count($result['skipped'])) {
                foreach ($result['skipped'] as $s) {
                    $messages[] = $s['emp_name'] . ': ' . $s['reason'];
                }
            }

            Session::flash(count($result['created']) ? 'success' : 'error', implode(' | ', $messages) ?: 'Nothing was marked');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    public function view(Request $request)
    {
        $ar = $this->findOrFail($request->id);
        if (!$ar->canView()) {
            abort(403);
        }

        return view('admin.attendance.daily.view', [
            'ar' => $ar,
            'employee' => AttendanceRequest::employeeDetails($ar->employee_id),
            'logs' => $ar->logs()->get(),
            'logLabels' => AttendanceRequestLog::LABELS,
            'statusBadge' => $this->statusBadge($ar),
        ]);
    }

    public function missed()
    {
        $self = AttendanceRequest::employeeForUser();
        if (!$self) {
            Session::flash('error', 'Your login is not linked to an employee record');
            return redirect(admin_url(self::BASE . 'list'));
        }

        return view('admin.attendance.daily.missed', [
            'dates' => collect(AttendanceRequest::missedDates($self->id)),
            'typeList' => AttendanceRequest::TYPE_LABELS,
            'lookbackDays' => AttendanceRequest::MISSED_LOOKBACK_DAYS,
        ]);
    }

    public function missedSubmit(Request $request)
    {
        $self = AttendanceRequest::employeeForUser();
        if (!$self) {
            Session::flash('error', 'Your login is not linked to an employee record');
            return redirect(admin_url(self::BASE . 'list'));
        }

        // Re-derive the allowed dates server-side rather than trusting the posted list.
        $eligible = AttendanceRequest::missedDates($self->id);
        $selected = collect($request->input('dates', []))
            ->filter(fn ($d) => in_array($d, $eligible, true))
            ->unique()
            ->values()
            ->all();

        $validator = validator($request->all() + ['selected_dates' => $selected], [
            'selected_dates' => ['required', 'array', 'min:1'],
            'attendance_type' => ['required', 'in:' . implode(',', array_keys(AttendanceRequest::TYPE_LABELS))],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'selected_dates.required' => 'Please select at least one date',
            'selected_dates.min' => 'Please select at least one date',
            'attendance_type.required' => 'Please select the attendance type',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $result = AttendanceRequest::markMissedDates($self->id, $selected, (int) $request->attendance_type, $request->reason);

            $messages = [];
            if (count($result['created'])) {
                $messages[] = count($result['created']) . ' date' . (count($result['created']) === 1 ? '' : 's') . ' marked successfully';
            }
            foreach ($result['skipped'] as $s) {
                $messages[] = Displaydateformat($s['date']) . ': ' . $s['reason'];
            }

            Session::flash(count($result['created']) ? 'success' : 'error', implode(' | ', $messages) ?: 'Nothing was marked');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect()->back()->withInput();
        }
    }

    /* ------------------------------------------------------------------ */

    private function findOrFail($encryptedId): AttendanceRequest
    {
        $ar = AttendanceRequest::find(decryptId($encryptedId));
        if (!$ar) {
            abort(404);
        }
        return $ar;
    }

    private function statusBadge($row): string
    {
        $map = [
            AttendanceRequest::PENDING => 'warning',
            AttendanceRequest::APPROVED => 'success',
            AttendanceRequest::REJECTED => 'danger',
        ];
        $label = AttendanceRequest::STATUS_LABELS[$row->request_status] ?? '-';
        if ($row->is_direct && $row->request_status == AttendanceRequest::APPROVED) {
            $label .= ' (Direct)';
        }

        return '<span class="badge bg-' . ($map[$row->request_status] ?? 'secondary') . '">' . e($label) . '</span>';
    }
}
