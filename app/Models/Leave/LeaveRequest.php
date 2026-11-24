<?php

namespace App\Models\Leave;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Shift\ShiftChangeRequest;
use App\Scopes\TrashScope;

/**
 * Leave Management → Apply Leave.
 *
 * Workflow
 *  - An employee applies for themselves. The leave goes to their reporting
 *    manager (masters_employee.reporting_manager), who is notified.
 *  - The reporting manager confirms or cancels it; the applicant is notified.
 *  - Leave applied by the employee's own reporting manager, or by an Admin,
 *    is confirmed directly with no approval step.
 *  - Pending leave already holds the balance; Cancelled gives it back.
 *    Days beyond the balance are Loss of Pay (see LeaveBalance).
 *  - Every action is written to leave_request_log.
 *
 * Who-is-who lookups (employee for a login, reporting manager, which
 * employees a manager may act for) are shared with Shift Change Request.
 */
class LeaveRequest extends Model
{
    protected $table = 'leave_request';

    public const PENDING = 1;
    public const CONFIRMED = 2;
    public const CANCELLED = 3;

    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::CONFIRMED => 'Confirmed',
        self::CANCELLED => 'Cancelled',
    ];

    public const ONE_DAY = 1;
    public const HALF_DAY = 2;
    public const MULTI_DAY = 3;

    public const DURATION_LABELS = [
        self::ONE_DAY => 'One Day',
        self::HALF_DAY => 'Half Day',
        self::MULTI_DAY => 'More Than One Day',
    ];

    public const SESSION_LABELS = [
        1 => 'First Half',
        2 => 'Second Half',
    ];

    /** template_notification.notification_type for this module. */
    public const NOTIFICATION_TYPE = 51;

    protected $fillable = [
        'request_no',
        'employee_id',
        'emp_id',
        'leave_type_id',
        'duration_type',
        'half_day_session',
        'from_date',
        'to_date',
        'leave_year',
        'total_days',
        'paid_days',
        'lop_days',
        'reason',
        'leave_status',
        'is_direct',
        'approver_user_id',
        'action_by',
        'action_at',
        'action_remarks',
        'status',
        'trash',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status' => 1,
        'trash' => 'NO',
        'leave_status' => self::PENDING,
    ];

    protected $casts = [
        'is_direct' => 'boolean',
        'from_date' => 'date',
        'to_date' => 'date',
        'action_at' => 'datetime',
        'total_days' => 'float',
        'paid_days' => 'float',
        'lop_days' => 'float',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TrashScope('leave_request'));

        static::created(function ($model) {
            $model->updateQuietly(['request_no' => 'LVR-' . str_pad($model->id, 5, '0', STR_PAD_LEFT)]);
        });
    }

    public function logs()
    {
        return $this->hasMany(LeaveRequestLog::class, 'request_id')->orderBy('id');
    }

    /* ------------------------------------------------------------------
     |  Who is who
     * ------------------------------------------------------------------ */

    /** Managers and Admins may apply on behalf of the employees they handle. */
    public static function canApplyForOthers(): bool
    {
        return ShiftChangeRequest::isManager();
    }

    public static function employeeForUser()
    {
        return ShiftChangeRequest::employeeForUser();
    }

    public static function selectableEmployees()
    {
        return ShiftChangeRequest::selectableEmployees();
    }

    public static function canApplyFor($employeeId): bool
    {
        return ShiftChangeRequest::canRaiseFor($employeeId);
    }

    /** The full masters_employee row (joining_date is needed for the balance). */
    public static function employee($employeeId)
    {
        return DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first();
    }

    public static function employeeDetails($employeeId)
    {
        return ShiftChangeRequest::employeeDetails($employeeId);
    }

    public static function reportingManagerUserId($employee): ?int
    {
        return ShiftChangeRequest::reportingManagerUserId($employee);
    }

    /** Confirmed straight away when an Admin or the employee's own approver applies. */
    public static function isDirectFor($employee): bool
    {
        if (isAdmin()) {
            return true;
        }
        $approver = static::reportingManagerUserId($employee);

        return $approver !== null && $approver === (int) Auth::id();
    }

    public static function leaveType($leaveTypeId)
    {
        return DB::table('masters_leave_type')->where('id', $leaveTypeId)->where('trash', 'NO')->first();
    }

    /* ------------------------------------------------------------------
     |  Permissions on a single request
     * ------------------------------------------------------------------ */

    public function canEdit(): bool
    {
        return $this->leave_status == self::PENDING
            && ((int) $this->created_by === (int) Auth::id() || isAdmin());
    }

    public function canApprove(): bool
    {
        return $this->leave_status == self::PENDING
            && ((int) $this->approver_user_id === (int) Auth::id() || isAdmin());
    }

    public function canView(): bool
    {
        if (isAdmin()) {
            return true;
        }
        $uid = (int) Auth::id();
        $me = static::employeeForUser();

        return (int) $this->created_by === $uid
            || (int) $this->approver_user_id === $uid
            || ($me && (int) $me->id === (int) $this->employee_id);
    }

    /* ------------------------------------------------------------------
     |  Dates and day count
     * ------------------------------------------------------------------ */

    /**
     * Work out the dates and day count for a duration, or return the
     * validation errors keyed by field.
     *
     * @return array{errors: array, from?: Carbon, to?: Carbon, total_days?: float}
     */
    public static function resolveDates(int $durationType, ?string $fromInput, ?string $toInput): array
    {
        $from = static::parseDate($fromInput);
        if (!$from) {
            return ['errors' => ['from_date' => 'Please select a valid leave date']];
        }

        if ($durationType === self::MULTI_DAY) {
            $to = static::parseDate($toInput);
            if (!$to) {
                return ['errors' => ['to_date' => 'Please select a valid To date']];
            }
            if ($to->lte($from)) {
                return ['errors' => ['to_date' => 'To date must be after the From date']];
            }
            if ($to->year !== $from->year) {
                return ['errors' => ['to_date' => 'Leave balance resets every year, so apply leave across 31 December as two requests']];
            }
            $days = LeaveBalance::workingDays($from, $to);
            if ($days === 0) {
                return ['errors' => ['to_date' => 'All selected days are Sundays or holidays']];
            }

            return ['errors' => [], 'from' => $from, 'to' => $to, 'total_days' => (float) $days];
        }

        if ($reason = LeaveBalance::nonWorkingReason($from)) {
            return ['errors' => ['from_date' => $reason . ', please pick a working day']];
        }

        return [
            'errors' => [],
            'from' => $from,
            'to' => $from->copy(),
            'total_days' => $durationType === self::HALF_DAY ? 0.5 : 1.0,
        ];
    }

    public static function parseDate(?string $value): ?Carbon
    {
        if (!$value || !preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('!d-m-Y', $value);
        } catch (\Throwable $e) {
            return null;
        }

        return $date && $date->format('d-m-Y') === $value ? $date : null;
    }

    /** Pending or Confirmed leave of the employee that overlaps the dates. */
    public static function overlapping($employeeId, Carbon $from, Carbon $to, $exceptId = null): ?self
    {
        return static::where('employee_id', $employeeId)
            ->whereIn('leave_status', [self::PENDING, self::CONFIRMED])
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString())
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->first();
    }

    /* ------------------------------------------------------------------
     |  Listing
     * ------------------------------------------------------------------ */

    public function list()
    {
        $request = request();

        $query = static::query()
            ->leftJoin('masters_employee as e', 'e.id', '=', 'leave_request.employee_id')
            ->leftJoin('masters_leave_type as lt', 'lt.id', '=', 'leave_request.leave_type_id')
            ->select('leave_request.*', 'e.emp_name', 'lt.leave_type_name');

        // Admin sees all; everyone else sees what they applied, what is about
        // them, and what waits on their approval.
        if (!isAdmin()) {
            $uid = Auth::id();
            $me = static::employeeForUser();
            $query->where(function ($q) use ($uid, $me) {
                $q->where('leave_request.created_by', $uid)
                    ->orWhere('leave_request.approver_user_id', $uid);
                if ($me) {
                    $q->orWhere('leave_request.employee_id', $me->id);
                }
            });
        }

        $total = (clone $query)->count();

        $search = $request->search['value'] ?? '';
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('leave_request.request_no', 'LIKE', "%{$search}%")
                    ->orWhere('leave_request.emp_id', 'LIKE', "%{$search}%")
                    ->orWhere('e.emp_name', 'LIKE', "%{$search}%")
                    ->orWhere('lt.leave_type_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('leave_status')) {
            $query->where('leave_request.leave_status', (int) $request->leave_status);
        }
        if ($request->filled('leave_type_id')) {
            $query->where('leave_request.leave_type_id', decryptId($request->leave_type_id));
        }
        if ($request->filled('leave_year')) {
            $query->where('leave_request.leave_year', (int) $request->leave_year);
        }
        if ($request->input('mine') === 'approval') {
            $query->where('leave_request.approver_user_id', Auth::id())
                ->where('leave_request.leave_status', self::PENDING);
        }

        $filtered = (clone $query)->count();

        $query->orderBy('leave_request.id', 'DESC');
        if ($request->length != -1 && $request->length !== null) {
            $query->offset($request->start)->limit($request->length);
        }

        return [
            'data' => $query->get(),
            'total_records' => $total,
            'filter_records' => $filtered,
        ];
    }

    /* ------------------------------------------------------------------
     |  Workflow
     * ------------------------------------------------------------------ */

    /**
     * Apply for leave. $data holds leave_type_id, duration_type,
     * half_day_session, from, to, total_days, paid_days, lop_days, reason.
     */
    public static function apply($employee, array $data): self
    {
        return DB::transaction(function () use ($employee, $data) {
            $direct = static::isDirectFor($employee);

            $leave = static::create([
                'employee_id' => $employee->id,
                'emp_id' => $employee->emp_id,
                'approver_user_id' => $direct ? Auth::id() : static::reportingManagerUserId($employee),
                'is_direct' => $direct,
                'created_by' => Auth::id(),
            ] + static::leaveColumns($data));

            LeaveRequestLog::record($leave->id, 'applied', $data['reason'] ?? null);

            if ($direct) {
                $leave->applyDecision(self::CONFIRMED, 'Confirmed directly by the approver', 'direct_confirmed');
                $employeeUser = ShiftChangeRequest::userIdForEmployee($employee);
                if ($employeeUser && $employeeUser !== (int) Auth::id()) {
                    $leave->notify($employeeUser, 'Leave Confirmed',
                        'Leave ' . $leave->request_no . ' (' . $leave->dateRangeLabel() . ') was applied and confirmed for you');
                }
            } else {
                $leave->notify($leave->approver_user_id, 'Leave Request',
                    $employee->emp_name . ' applied for leave on ' . $leave->dateRangeLabel() . ' (' . $leave->request_no . ')',
                    'approval');
            }

            return $leave;
        });
    }

    /** Edit a pending request. */
    public function revise(array $data): void
    {
        DB::transaction(function () use ($data) {
            $this->update(static::leaveColumns($data) + ['updated_by' => Auth::id()]);

            LeaveRequestLog::record($this->id, 'updated', $data['reason'] ?? null);

            $this->notify($this->approver_user_id, 'Leave Request Updated',
                $this->request_no . ' was updated: ' . $this->dateRangeLabel(), 'approval');
        });
    }

    /** Confirm or cancel by the approver; the applicant is notified. */
    public function decide(int $decision, ?string $remarks): void
    {
        DB::transaction(function () use ($decision, $remarks) {
            $word = $decision === self::CONFIRMED ? 'confirmed' : 'cancelled';
            $this->applyDecision($decision, $remarks, $word);

            $message = 'Your leave ' . $this->request_no . ' (' . $this->dateRangeLabel() . ') was ' . $word . ' by ' . Auth::user()->name;
            if ($decision === self::CANCELLED) {
                $message .= '. The days are back in your leave balance.';
            }

            $this->notify($this->created_by, 'Leave ' . ucfirst($word), $message);

            // If someone applied on the employee's behalf, tell the employee too.
            $employeeUser = ShiftChangeRequest::userIdForEmployee(static::employee($this->employee_id));
            if ($employeeUser && $employeeUser !== (int) $this->created_by && $employeeUser !== (int) Auth::id()) {
                $this->notify($employeeUser, 'Leave ' . ucfirst($word), $message);
            }
        });
    }

    private function applyDecision(int $decision, ?string $remarks, string $logAction): void
    {
        $this->update([
            'leave_status' => $decision,
            'action_by' => Auth::id(),
            'action_at' => now(),
            'action_remarks' => $remarks,
            'updated_by' => Auth::id(),
        ]);

        LeaveRequestLog::record($this->id, $logAction, $remarks);
    }

    private static function leaveColumns(array $data): array
    {
        return [
            'leave_type_id' => $data['leave_type_id'],
            'duration_type' => $data['duration_type'],
            'half_day_session' => $data['duration_type'] === self::HALF_DAY ? $data['half_day_session'] : null,
            'from_date' => $data['from']->toDateString(),
            'to_date' => $data['to']->toDateString(),
            'leave_year' => (int) $data['from']->year,
            'total_days' => $data['total_days'],
            'paid_days' => $data['paid_days'],
            'lop_days' => $data['lop_days'],
            'reason' => $data['reason'] ?? null,
        ];
    }

    /* ------------------------------------------------------------------
     |  Display helpers
     * ------------------------------------------------------------------ */

    public function durationLabel(): string
    {
        $label = self::DURATION_LABELS[$this->duration_type] ?? '-';
        if ($this->duration_type == self::HALF_DAY && $this->half_day_session) {
            $label .= ' (' . (self::SESSION_LABELS[$this->half_day_session] ?? '') . ')';
        }

        return $label;
    }

    public function dateRangeLabel(): string
    {
        $from = Carbon::parse($this->from_date)->format('d-m-Y');
        $to = Carbon::parse($this->to_date)->format('d-m-Y');

        return $from === $to ? $from : $from . ' to ' . $to;
    }

    public static function formatDays($days): string
    {
        $days = (float) $days;

        return rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.');
    }

    private function notify($userId, string $title, string $message, string $target = 'view'): void
    {
        if (!$userId) {
            return;
        }

        notificationSave([
            'notification_type' => self::NOTIFICATION_TYPE,
            'module_type' => 'leave_request',
            'notification_message' => $title,
            'mobile_notification' => json_encode([
                'title' => $title,
                'message' => $message,
                'icon' => asset('assets/icons/occupational-therapy.png'),
                'id' => $this->id,
                'module' => 'leave_request',
            ]),
            'web_link' => admin_url('leave-management/leave/' . $target . '/' . encryptId($this->id)),
            'assigned_user' => (string) $userId,
            'viewed_user' => '',
            'created_by' => Auth::id(),
        ]);
    }
}
