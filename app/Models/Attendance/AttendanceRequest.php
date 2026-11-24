<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\Holiday\Holiday;
use App\Models\Leave\LeaveRequest;
use App\Scopes\TrashScope;

/**
 * Attendance Management → Daily Attendance.
 *
 * Workflow
 *  - An employee marks their own attendance for a day. It goes to their
 *    reporting manager (masters_employee.reporting_manager), who is notified.
 *  - A Manager (or Admin) marking their own attendance, or marking it for one
 *    of their direct reports, is approved directly with no approval step.
 *  - Approving/rejecting an employee's self-marked attendance happens only in
 *    the separate Attendance Approval module (bulk, Manager-only).
 *  - An employee cannot mark attendance on a date already covered by a
 *    pending or confirmed leave (see hasLeaveConflict()).
 *  - Every action is written to attendance_request_log.
 */
class AttendanceRequest extends Model
{
    protected $table = 'attendance_request';

    public const PENDING = 0;
    public const APPROVED = 1;
    public const REJECTED = 2;

    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    public const PRESENT = 1;
    public const HALF_DAY = 2;
    public const WORK_FROM_HOME = 3;

    public const TYPE_LABELS = [
        self::PRESENT => 'Present',
        self::HALF_DAY => 'Half Day',
        self::WORK_FROM_HOME => 'Work From Home',
    ];

    /** template_notification.notification_type for this module. */
    public const NOTIFICATION_TYPE = 54;

    /** How far back "missed attendance" looks for unmarked working days. */
    public const MISSED_LOOKBACK_DAYS = 30;

    protected $fillable = [
        'request_no',
        'employee_id',
        'emp_id',
        'attendance_date',
        'attendance_type',
        'reason',
        'request_status',
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
        'request_status' => self::PENDING,
        'attendance_type' => self::PRESENT,
    ];

    protected $casts = [
        'is_direct' => 'boolean',
        'action_at' => 'datetime',
        'attendance_date' => 'date',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TrashScope('attendance_request'));

        static::created(function ($model) {
            $model->updateQuietly(['request_no' => 'DA-' . str_pad($model->id, 5, '0', STR_PAD_LEFT)]);
        });
    }

    public function logs()
    {
        return $this->hasMany(AttendanceRequestLog::class, 'request_id')->orderBy('id');
    }

    /* ------------------------------------------------------------------
     |  Who is who
     * ------------------------------------------------------------------ */

    /** Managers (and Admins) get their own attendance, and their reports', marked directly. */
    public static function isManager(): bool
    {
        return CheckUserRole(ROLE_MANAGER) || isAdmin();
    }

    /** The masters_employee row linked to a login. */
    public static function employeeForUser($user = null)
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return null;
        }

        return DB::table('masters_employee')
            ->where('trash', 'NO')
            ->where(function ($q) use ($user) {
                $q->where('login_id', $user->id);
                if ($user->employee_id) {
                    $q->orWhere('emp_id', $user->employee_id);
                }
            })
            ->orderByRaw('login_id = ? DESC', [$user->id])
            ->first();
    }

    /** users.id of an employee's login, or null. */
    public static function userIdForEmployee($employee): ?int
    {
        if (!$employee) {
            return null;
        }
        if ($employee->login_id) {
            return (int) $employee->login_id;
        }
        $id = DB::table('users')->where('employee_id', $employee->emp_id)->where('trash', 'NO')->value('id');

        return $id ? (int) $id : null;
    }

    /** users.id of the employee's reporting manager, or null when none is set. */
    public static function reportingManagerUserId($employee): ?int
    {
        if (!$employee || !$employee->reporting_manager) {
            return null;
        }
        $manager = DB::table('masters_employee')
            ->where('emp_id', $employee->reporting_manager)
            ->where('trash', 'NO')
            ->first();

        return static::userIdForEmployee($manager);
    }

    /** Employee with company, location and designation for the form / view. */
    public static function employeeDetails($employeeId)
    {
        $employee = DB::table('masters_employee as e')
            ->leftJoin('company_management as c', 'c.id', '=', 'e.company')
            ->leftJoin('masters_location as l', 'l.id', '=', 'e.location')
            ->select(
                'e.id',
                'e.emp_id',
                'e.emp_name',
                'e.designation',
                'e.reporting_manager',
                'e.login_id',
                'c.company_name',
                'l.location_name'
            )
            ->where('e.id', $employeeId)
            ->where('e.trash', 'NO')
            ->first();

        if ($employee) {
            $employee->reporting_manager_name = $employee->reporting_manager
                ? DB::table('masters_employee')->where('emp_id', $employee->reporting_manager)->where('trash', 'NO')->value('emp_name')
                : null;
        }

        return $employee;
    }

    /** The direct reports of the logged-in manager (does not include the manager themselves). */
    public static function reportingEmployees()
    {
        $me = static::employeeForUser();
        if (!$me) {
            return collect();
        }

        return DB::table('masters_employee')
            ->select('id', 'emp_id', 'emp_name', 'designation')
            ->where('trash', 'NO')
            ->where('status', 1)
            ->where('reporting_manager', $me->emp_id)
            ->orderBy('emp_name')
            ->get();
    }

    /* ------------------------------------------------------------------
     |  Guards
     * ------------------------------------------------------------------ */

    /** Whether this employee already has a live (pending/approved) attendance entry for this date. */
    public static function hasMarkedFor($employeeId, $date, $exceptId = null): bool
    {
        return static::where('employee_id', $employeeId)
            ->where('attendance_date', $date)
            ->whereIn('request_status', [self::PENDING, self::APPROVED])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /** Whether this employee already has a pending/confirmed leave covering this date. */
    public static function hasLeaveConflict($employeeId, $date): bool
    {
        return LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('leave_status', [LeaveRequest::PENDING, LeaveRequest::CONFIRMED])
            ->where('from_date', '<=', $date)
            ->where('to_date', '>=', $date)
            ->exists();
    }

    /** Saturday or Sunday. */
    public static function isWeekend(string $date): bool
    {
        $dow = (int) date('N', strtotime($date)); // 6 = Saturday, 7 = Sunday
        return $dow >= 6;
    }

    public static function isHoliday(string $date): bool
    {
        return Holiday::where('day', (int) date('j', strtotime($date)))
            ->where('month', (int) date('n', strtotime($date)))
            ->where('year', (int) date('Y', strtotime($date)))
            ->where('status', 1)
            ->exists();
    }

    public static function isWorkingDay(string $date): bool
    {
        return !static::isWeekend($date) && !static::isHoliday($date);
    }

    /**
     * Working days in the last MISSED_LOOKBACK_DAYS (never before the employee's
     * joining date) that this employee has not marked attendance for and is not
     * on leave for. Most recent first.
     */
    public static function missedDates($employeeId): array
    {
        $employee = DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first();
        if (!$employee) {
            return [];
        }

        $end = now()->subDay()->startOfDay();
        $start = now()->subDays(self::MISSED_LOOKBACK_DAYS)->startOfDay();
        if (!empty($employee->joining_date)) {
            $joining = Carbon::parse($employee->joining_date)->startOfDay();
            if ($joining->gt($start)) {
                $start = $joining;
            }
        }
        if ($start->gt($end)) {
            return [];
        }

        $markedDates = static::where('employee_id', $employeeId)
            ->whereIn('request_status', [self::PENDING, self::APPROVED])
            ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('attendance_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->all();

        $leaveRanges = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('leave_status', [LeaveRequest::PENDING, LeaveRequest::CONFIRMED])
            ->where('to_date', '>=', $start->format('Y-m-d'))
            ->where('from_date', '<=', $end->format('Y-m-d'))
            ->get(['from_date', 'to_date']);

        $holidaySet = [];
        foreach (Holiday::whereIn('year', array_unique([$start->year, $end->year]))->where('status', 1)->get(['day', 'month', 'year']) as $h) {
            if (checkdate((int) $h->month, (int) $h->day, (int) $h->year)) {
                $holidaySet[sprintf('%04d-%02d-%02d', $h->year, $h->month, $h->day)] = true;
            }
        }

        $missed = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');

            if ($d->isSaturday() || $d->isSunday() || isset($holidaySet[$dateStr]) || in_array($dateStr, $markedDates, true)) {
                continue;
            }

            $onLeave = false;
            foreach ($leaveRanges as $lr) {
                if ($dateStr >= $lr->from_date->format('Y-m-d') && $dateStr <= $lr->to_date->format('Y-m-d')) {
                    $onLeave = true;
                    break;
                }
            }
            if ($onLeave) {
                continue;
            }

            $missed[] = $dateStr;
        }

        return array_reverse($missed);
    }

    /* ------------------------------------------------------------------
     |  Permissions on a single request
     * ------------------------------------------------------------------ */

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

    public function canApprove(): bool
    {
        return $this->request_status == self::PENDING
            && ((int) $this->approver_user_id === (int) Auth::id() || isAdmin());
    }

    /* ------------------------------------------------------------------
     |  Listing
     * ------------------------------------------------------------------ */

    public function list()
    {
        $request = request();

        $query = static::query()
            ->leftJoin('masters_employee as e', 'e.id', '=', 'attendance_request.employee_id')
            ->select('attendance_request.*', 'e.emp_name', 'e.designation');

        if (!isAdmin()) {
            $uid = Auth::id();
            $me = static::employeeForUser();
            $query->where(function ($q) use ($uid, $me) {
                $q->where('attendance_request.created_by', $uid)
                    ->orWhere('attendance_request.approver_user_id', $uid);
                if ($me) {
                    $q->orWhere('attendance_request.employee_id', $me->id);
                }
            });
        }

        $total = (clone $query)->count();

        $search = $request->search['value'] ?? '';
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('attendance_request.request_no', 'LIKE', "%{$search}%")
                    ->orWhere('attendance_request.emp_id', 'LIKE', "%{$search}%")
                    ->orWhere('e.emp_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('request_status')) {
            $query->where('attendance_request.request_status', (int) $request->request_status);
        }

        $filtered = (clone $query)->count();

        $query->orderBy('attendance_request.id', 'DESC');
        if ($request->length != -1 && $request->length !== null) {
            $query->offset($request->start)->limit($request->length);
        }

        return [
            'data' => $query->get(),
            'total_records' => $total,
            'filter_records' => $filtered,
        ];
    }

    /** Pending attendance requests waiting on the logged-in manager's approval. */
    public static function pendingForApprover()
    {
        $query = static::query()
            ->leftJoin('masters_employee as e', 'e.id', '=', 'attendance_request.employee_id')
            ->select('attendance_request.*', 'e.emp_name', 'e.designation')
            ->where('attendance_request.request_status', self::PENDING);

        if (!isAdmin()) {
            $query->where('attendance_request.approver_user_id', Auth::id());
        }

        return $query->orderBy('attendance_request.attendance_date')->get();
    }

    /* ------------------------------------------------------------------
     |  Workflow
     * ------------------------------------------------------------------ */

    /**
     * Mark attendance for one or more employees in a single go.
     * $employeeIds may include the marking user's own employee id (self-mark,
     * routed to their reporting manager unless they are a Manager/Admin) and,
     * when the marker is a Manager/Admin, any of their direct reports
     * (always marked directly, no approval step).
     *
     * Returns ['created' => AttendanceRequest[], 'skipped' => [['emp_name'=>, 'reason'=>], ...]].
     */
    public static function markBulk(array $employeeIds, string $date, int $type, ?string $reason, $selfEmployeeId): array
    {
        $created = [];
        $skipped = [];

        DB::transaction(function () use ($employeeIds, $date, $type, $reason, $selfEmployeeId, &$created, &$skipped) {
            foreach (array_unique($employeeIds) as $employeeId) {
                $employee = DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first();
                if (!$employee) {
                    continue;
                }

                if (static::hasMarkedFor($employeeId, $date)) {
                    $skipped[] = ['emp_name' => $employee->emp_name, 'reason' => 'Attendance already marked for this date'];
                    continue;
                }
                if (static::hasLeaveConflict($employeeId, $date)) {
                    $skipped[] = ['emp_name' => $employee->emp_name, 'reason' => 'Leave already applied for this date'];
                    continue;
                }

                $isSelf = (int) $employeeId === (int) $selfEmployeeId;
                $direct = $isSelf ? static::isManager() : true; // a manager marking a report is always direct

                $created[] = static::createEntry($employee, $date, $type, $reason, $direct, $isSelf);
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Back-fill attendance for working days this employee missed: dates they
     * neither marked attendance for nor are on leave for, excluding weekends
     * and holidays (see missedDates()). Each date is routed the same way a
     * normal self-mark would be: direct for a Manager/Admin, otherwise to
     * their reporting manager.
     */
    public static function markMissedDates($employeeId, array $dates, int $type, ?string $reason): array
    {
        $created = [];
        $skipped = [];

        $employee = DB::table('masters_employee')->where('id', $employeeId)->where('trash', 'NO')->first();
        if (!$employee) {
            return ['created' => $created, 'skipped' => $skipped];
        }

        DB::transaction(function () use ($employee, $dates, $type, $reason, &$created, &$skipped) {
            foreach (array_unique($dates) as $date) {
                if (!static::isWorkingDay($date)) {
                    $skipped[] = ['date' => $date, 'reason' => 'Weekend or holiday'];
                    continue;
                }
                if (static::hasMarkedFor($employee->id, $date)) {
                    $skipped[] = ['date' => $date, 'reason' => 'Attendance already marked for this date'];
                    continue;
                }
                if (static::hasLeaveConflict($employee->id, $date)) {
                    $skipped[] = ['date' => $date, 'reason' => 'Leave already applied for this date'];
                    continue;
                }

                $direct = static::isManager();
                $created[] = static::createEntry($employee, $date, $type, $reason, $direct, true);
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Creates one attendance entry, applies direct approval, and notifies. Shared by markBulk() and markMissedDates(). */
    private static function createEntry($employee, string $date, int $type, ?string $reason, bool $direct, bool $isSelf): self
    {
        $request = static::create([
            'employee_id' => $employee->id,
            'emp_id' => $employee->emp_id,
            'attendance_date' => $date,
            'attendance_type' => $type,
            'reason' => $reason,
            'is_direct' => $direct,
            'approver_user_id' => $direct ? Auth::id() : static::reportingManagerUserId($employee),
            'created_by' => Auth::id(),
        ]);

        AttendanceRequestLog::record($request->id, 'created', $reason);

        if ($direct) {
            $request->applyDecision(self::APPROVED, $isSelf ? 'Direct approval by manager' : 'Marked by reporting manager', 'direct_approved');
            if (!$isSelf) {
                $employeeUser = static::userIdForEmployee($employee);
                if ($employeeUser && $employeeUser !== (int) Auth::id()) {
                    $request->notify($employeeUser, 'Attendance Marked',
                        'Your attendance for ' . $date . ' was marked by your reporting manager (' . $request->request_no . ')');
                }
            }
        } else {
            $request->notify($request->approver_user_id, 'Attendance Approval Needed',
                $employee->emp_name . ' marked attendance for ' . $date . ', awaiting your approval (' . $request->request_no . ')');
        }

        return $request;
    }

    /** Approve or reject by the reporting manager; the creator is notified. */
    public function decide(int $decision, ?string $remarks): void
    {
        DB::transaction(function () use ($decision, $remarks) {
            $this->applyDecision($decision, $remarks, $decision === self::APPROVED ? 'approved' : 'rejected');

            $word = $decision === self::APPROVED ? 'approved' : 'rejected';
            $message = 'Your attendance for ' . $this->attendance_date->format('d-m-Y') . ' (' . $this->request_no . ') was ' . $word . ' by ' . Auth::user()->name;

            $this->notify($this->created_by, 'Attendance ' . ucfirst($word), $message);
        });
    }

    private function applyDecision(int $decision, ?string $remarks, string $logAction): void
    {
        $this->update([
            'request_status' => $decision,
            'action_by' => Auth::id(),
            'action_at' => now(),
            'action_remarks' => $remarks,
            'updated_by' => Auth::id(),
        ]);

        AttendanceRequestLog::record($this->id, $logAction, $remarks);
    }

    private function notify($userId, string $title, string $message, string $target = 'view'): void
    {
        if (!$userId) {
            return;
        }

        $link = admin_url('attendance-management/daily-attendance/' . $target . '/' . encryptId($this->id));

        notificationSave([
            'notification_type' => self::NOTIFICATION_TYPE,
            'module_type' => 'attendance_request',
            'notification_message' => $title,
            'mobile_notification' => json_encode([
                'title' => $title,
                'message' => $message,
                'icon' => asset('assets/icons/occupational-therapy.png'),
                'id' => $this->id,
                'module' => 'attendance_request',
            ]),
            'web_link' => $link,
            'assigned_user' => (string) $userId,
            'viewed_user' => '',
            'created_by' => Auth::id(),
        ]);
    }
}
