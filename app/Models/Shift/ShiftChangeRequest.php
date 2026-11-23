<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Scopes\TrashScope;

/**
 * Shift Management → Shift Change Request.
 *
 * Workflow
 *  - An employee raises a request for themselves. It goes to their reporting
 *    manager (masters_employee.reporting_manager) and that manager is notified.
 *  - The reporting manager approves or rejects; the creator is notified.
 *    Approve → the employee's shift is changed. Reject → nothing changes.
 *  - A Manager (or Admin) raising a request is approved directly, no approval step.
 *  - Every action is written to shift_change_request_log.
 */
class ShiftChangeRequest extends Model
{
    protected $table = 'shift_change_request';

    public const PENDING = 0;
    public const APPROVED = 1;
    public const REJECTED = 2;

    /** template_notification.notification_type for this module. */
    public const NOTIFICATION_TYPE = 50;

    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
    ];

    protected $fillable = [
        'request_no',
        'employee_id',
        'emp_id',
        'current_shift_id',
        'requested_shift_id',
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
    ];

    protected $casts = [
        'is_direct' => 'boolean',
        'action_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TrashScope('shift_change_request'));

        static::created(function ($model) {
            $model->updateQuietly(['request_no' => 'SCR-' . str_pad($model->id, 5, '0', STR_PAD_LEFT)]);
        });
    }

    public function logs()
    {
        return $this->hasMany(ShiftChangeRequestLog::class, 'request_id')->orderBy('id');
    }

    /* ------------------------------------------------------------------
     |  Who is who
     * ------------------------------------------------------------------ */

    /** Managers (and Admins) change shifts directly, without approval. */
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

    /** Employee with company, location and shift names for the form / view. */
    public static function employeeDetails($employeeId)
    {
        $employee = DB::table('masters_employee as e')
            ->leftJoin('company_management as c', 'c.id', '=', 'e.company')
            ->leftJoin('masters_location as l', 'l.id', '=', 'e.location')
            ->leftJoin('masters_shift as s', 's.id', '=', 'e.shift')
            ->select(
                'e.id',
                'e.emp_id',
                'e.emp_name',
                'e.designation',
                'e.shift',
                'e.reporting_manager',
                'e.login_id',
                'c.company_name',
                'l.location_name',
                's.shift_name',
                's.start_time',
                's.end_time'
            )
            ->where('e.id', $employeeId)
            ->where('e.trash', 'NO')
            ->first();

        // emp_id and reporting_manager use different collations, so the
        // manager's name is looked up separately instead of a self-join.
        if ($employee) {
            $employee->reporting_manager_name = $employee->reporting_manager
                ? DB::table('masters_employee')->where('emp_id', $employee->reporting_manager)->where('trash', 'NO')->value('emp_name')
                : null;
        }

        return $employee;
    }

    /**
     * Employees the logged-in manager may raise a request for:
     * Admin → everyone, Manager → their direct reports and themselves.
     */
    public static function selectableEmployees()
    {
        $query = DB::table('masters_employee')
            ->select('id', 'emp_id', 'emp_name')
            ->where('trash', 'NO')
            ->where('status', 1)
            ->orderBy('emp_name');

        if (!isAdmin()) {
            $me = static::employeeForUser();
            $query->where(function ($q) use ($me) {
                $q->where('reporting_manager', $me->emp_id ?? '__none__')
                    ->orWhere('id', $me->id ?? 0);
            });
        }

        return $query->get();
    }

    public static function canRaiseFor($employeeId): bool
    {
        if (isAdmin()) {
            return true;
        }
        if (static::isManager()) {
            return static::selectableEmployees()->contains('id', (int) $employeeId);
        }
        $me = static::employeeForUser();

        return $me && (int) $me->id === (int) $employeeId;
    }

    public static function shiftName($shiftId): string
    {
        static $names = null;
        $names ??= DB::table('masters_shift')->pluck('shift_name', 'id');

        return (string) ($names[$shiftId] ?? '-');
    }

    public static function hasPendingFor($employeeId, $exceptId = null): bool
    {
        return static::where('employee_id', $employeeId)
            ->where('request_status', self::PENDING)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    /* ------------------------------------------------------------------
     |  Permissions on a single request
     * ------------------------------------------------------------------ */

    public function canEdit(): bool
    {
        return $this->request_status == self::PENDING
            && ((int) $this->created_by === (int) Auth::id() || isAdmin());
    }

    public function canApprove(): bool
    {
        return $this->request_status == self::PENDING
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
     |  Listing
     * ------------------------------------------------------------------ */

    public function list()
    {
        $request = request();

        $query = static::query()
            ->leftJoin('masters_employee as e', 'e.id', '=', 'shift_change_request.employee_id')
            ->leftJoin('masters_shift as cs', 'cs.id', '=', 'shift_change_request.current_shift_id')
            ->leftJoin('masters_shift as rs', 'rs.id', '=', 'shift_change_request.requested_shift_id')
            ->select(
                'shift_change_request.*',
                'e.emp_name',
                'e.designation',
                'cs.shift_name as current_shift_name',
                'rs.shift_name as requested_shift_name'
            );

        // Admin sees all; everyone else sees what they raised, what is about
        // them, and what waits on their approval.
        if (!isAdmin()) {
            $uid = Auth::id();
            $me = static::employeeForUser();
            $query->where(function ($q) use ($uid, $me) {
                $q->where('shift_change_request.created_by', $uid)
                    ->orWhere('shift_change_request.approver_user_id', $uid);
                if ($me) {
                    $q->orWhere('shift_change_request.employee_id', $me->id);
                }
            });
        }

        $total = (clone $query)->count();

        $search = $request->search['value'] ?? '';
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('shift_change_request.request_no', 'LIKE', "%{$search}%")
                    ->orWhere('shift_change_request.emp_id', 'LIKE', "%{$search}%")
                    ->orWhere('e.emp_name', 'LIKE', "%{$search}%")
                    ->orWhere('cs.shift_name', 'LIKE', "%{$search}%")
                    ->orWhere('rs.shift_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('request_status')) {
            $query->where('shift_change_request.request_status', (int) $request->request_status);
        }
        if ($request->filled('requested_shift_id')) {
            $query->where('shift_change_request.requested_shift_id', decryptId($request->requested_shift_id));
        }
        if ($request->input('mine') === 'approval') {
            $query->where('shift_change_request.approver_user_id', Auth::id())
                ->where('shift_change_request.request_status', self::PENDING);
        }

        $filtered = (clone $query)->count();

        $query->orderBy('shift_change_request.id', 'DESC');
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
     * Raise a request. Managers get it approved and applied at once.
     */
    public static function raise($employee, int $requestedShiftId, ?string $reason): self
    {
        return DB::transaction(function () use ($employee, $requestedShiftId, $reason) {
            $direct = static::isManager();

            $request = static::create([
                'employee_id' => $employee->id,
                'emp_id' => $employee->emp_id,
                'current_shift_id' => $employee->shift,
                'requested_shift_id' => $requestedShiftId,
                'reason' => $reason,
                'is_direct' => $direct,
                'approver_user_id' => $direct ? Auth::id() : static::reportingManagerUserId($employee),
                'created_by' => Auth::id(),
            ]);

            ShiftChangeRequestLog::record($request->id, 'created', $employee->shift, $requestedShiftId, $reason);

            if ($direct) {
                $request->applyDecision(self::APPROVED, 'Direct approval by manager', 'direct_approved');
                // Let the employee know their shift was changed for them.
                $employeeUser = static::userIdForEmployee($employee);
                if ($employeeUser && $employeeUser !== (int) Auth::id()) {
                    $request->notify($employeeUser, 'Shift Changed',
                        'Your shift has been changed to ' . static::shiftName($requestedShiftId) . ' (' . $request->request_no . ')');
                }
            } else {
                $request->notify($request->approver_user_id, 'Shift Change Request',
                    $employee->emp_name . ' requested a shift change to ' . static::shiftName($requestedShiftId) . ' (' . $request->request_no . ')',
                    'approval');
            }

            return $request;
        });
    }

    /** Edit a pending request. */
    public function revise(int $requestedShiftId, ?string $reason): void
    {
        DB::transaction(function () use ($requestedShiftId, $reason) {
            $this->update([
                'requested_shift_id' => $requestedShiftId,
                'reason' => $reason,
                'updated_by' => Auth::id(),
            ]);

            ShiftChangeRequestLog::record($this->id, 'updated', $this->current_shift_id, $requestedShiftId, $reason);

            $this->notify($this->approver_user_id, 'Shift Change Request Updated',
                $this->request_no . ' was updated: requested shift is now ' . static::shiftName($requestedShiftId),
                'approval');
        });
    }

    /** Approve or reject by the reporting manager; the creator is notified. */
    public function decide(int $decision, ?string $remarks): void
    {
        DB::transaction(function () use ($decision, $remarks) {
            $this->applyDecision($decision, $remarks, $decision === self::APPROVED ? 'approved' : 'rejected');

            $word = $decision === self::APPROVED ? 'approved' : 'rejected';
            $message = 'Your shift change request ' . $this->request_no . ' was ' . $word . ' by ' . Auth::user()->name;
            if ($decision === self::REJECTED) {
                $message .= '. Your current shift continues.';
            }

            $this->notify($this->created_by, 'Shift Change Request ' . ucfirst($word), $message);

            // If someone raised it on the employee's behalf, tell the employee too.
            $employeeUser = static::userIdForEmployee(DB::table('masters_employee')->where('id', $this->employee_id)->first());
            if ($employeeUser && $employeeUser !== (int) $this->created_by && $employeeUser !== (int) Auth::id()) {
                $this->notify($employeeUser, 'Shift Change Request ' . ucfirst($word), $message);
            }
        });
    }

    /**
     * Record the decision. Only an approval touches the employee's shift;
     * a rejection leaves the existing shift as it is.
     */
    private function applyDecision(int $decision, ?string $remarks, string $logAction): void
    {
        $previousShift = DB::table('masters_employee')->where('id', $this->employee_id)->value('shift');

        $this->update([
            'request_status' => $decision,
            'action_by' => Auth::id(),
            'action_at' => now(),
            'action_remarks' => $remarks,
            'updated_by' => Auth::id(),
        ]);

        if ($decision === self::APPROVED) {
            DB::table('masters_employee')->where('id', $this->employee_id)->update([
                'shift' => $this->requested_shift_id,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);
        }

        ShiftChangeRequestLog::record(
            $this->id,
            $logAction,
            $previousShift,
            $decision === self::APPROVED ? $this->requested_shift_id : $previousShift,
            $remarks
        );
    }

    private function notify($userId, string $title, string $message, string $target = 'view'): void
    {
        if (!$userId) {
            return;
        }

        $link = admin_url('shift-management/shift-change-request/' . $target . '/' . encryptId($this->id));

        notificationSave([
            'notification_type' => self::NOTIFICATION_TYPE,
            'module_type' => 'shift_change_request',
            'notification_message' => $title,
            'mobile_notification' => json_encode([
                'title' => $title,
                'message' => $message,
                'icon' => asset('assets/icons/occupational-therapy.png'),
                'id' => $this->id,
                'module' => 'shift_change_request',
            ]),
            'web_link' => $link,
            'assigned_user' => (string) $userId,
            'viewed_user' => '',
            'created_by' => Auth::id(),
        ]);
    }
}
