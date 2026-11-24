<?php

namespace App\Models\Leave;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Leave balance rules.
 *
 *  - Credit: each leave type's monthly_credit (masters_leave_type) is added at
 *    the START of every month. On 1 Jan an employee has 1 month's credit, on
 *    1 Feb 2 months', and so on. An employee who joins mid-year is credited
 *    from their joining month.
 *  - Usage: Pending and Confirmed leave both hold the balance (paid_days).
 *    Cancelled leave gives it back, because it is simply not counted.
 *  - Loss of Pay: days beyond the available balance are saved as lop_days
 *    and never reduce the balance.
 *  - Reset: credit and usage are worked out per calendar year, so unused
 *    leave is usable until 31 Dec and the balance starts again on 1 Jan.
 *    Nothing has to run at year end.
 *  - Day count: Sundays and active holidays (holiday table) are not counted.
 */
class LeaveBalance
{
    /**
     * Balance of one leave type for one employee in a year.
     *
     * $asOf decides how many months are credited. It defaults to today; for an
     * application it is the later of today and the leave's start date, so a
     * leave planned for a later month can use that month's credit.
     */
    public static function for($employee, $leaveType, int $year, ?Carbon $asOf = null, $exceptRequestId = null): array
    {
        $asOf = $asOf ?: Carbon::today();
        $monthlyCredit = (float) ($leaveType->monthly_credit ?? 0);

        $months = static::creditedMonths($employee, $year, $asOf);
        $credited = round($months * $monthlyCredit, 1);

        $usage = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('leave_year', $year)
            ->whereIn('leave_status', [LeaveRequest::PENDING, LeaveRequest::CONFIRMED])
            ->when($exceptRequestId, fn ($q) => $q->where('id', '!=', $exceptRequestId))
            ->selectRaw('
                COALESCE(SUM(CASE WHEN leave_status = ? THEN paid_days ELSE 0 END), 0) AS confirmed,
                COALESCE(SUM(CASE WHEN leave_status = ? THEN paid_days ELSE 0 END), 0) AS pending,
                COALESCE(SUM(lop_days), 0) AS lop
            ', [LeaveRequest::CONFIRMED, LeaveRequest::PENDING])
            ->first();

        $confirmed = (float) $usage->confirmed;
        $pending = (float) $usage->pending;

        return [
            'leave_type_id' => $leaveType->id,
            'leave_type_name' => $leaveType->leave_type_name,
            'monthly_credit' => $monthlyCredit,
            'year' => $year,
            'months_credited' => $months,
            'credited' => $credited,
            'used' => $confirmed,
            'pending' => $pending,
            'lop' => (float) $usage->lop,
            'available' => max(0, round($credited - $confirmed - $pending, 1)),
        ];
    }

    /** Balance of every active leave type for an employee, as of today. */
    public static function summary($employee, ?int $year = null): array
    {
        $year = $year ?: (int) date('Y');

        return DB::table('masters_leave_type')
            ->where('status', 1)
            ->where('trash', 'NO')
            ->orderBy('leave_type_name')
            ->get()
            ->map(fn ($type) => static::for($employee, $type, $year))
            ->all();
    }

    /**
     * Split a leave into paid and Loss of Pay days against the balance
     * that is free for it.
     */
    public static function split($employee, $leaveType, Carbon $from, float $totalDays, $exceptRequestId = null): array
    {
        $year = (int) $from->year;
        $asOf = $from->greaterThan(Carbon::today()) ? $from->copy() : Carbon::today();
        if ($asOf->year > $year) {
            $asOf = Carbon::create($year, 12, 31);
        }

        $balance = static::for($employee, $leaveType, $year, $asOf, $exceptRequestId);
        $paid = min($totalDays, $balance['available']);

        return [
            'balance' => $balance,
            'paid_days' => round($paid, 1),
            'lop_days' => round($totalDays - $paid, 1),
        ];
    }

    /** Months credited in $year up to and including the month of $asOf. */
    public static function creditedMonths($employee, int $year, Carbon $asOf): int
    {
        if ($asOf->year < $year) {
            return 0;
        }
        $lastMonth = $asOf->year > $year ? 12 : (int) $asOf->month;

        $firstMonth = 1;
        if (!empty($employee->joining_date)) {
            $joined = Carbon::parse($employee->joining_date);
            if ($joined->year > $year) {
                return 0;
            }
            if ($joined->year === $year) {
                $firstMonth = (int) $joined->month;
            }
        }

        return max(0, $lastMonth - $firstMonth + 1);
    }

    /* ------------------------------------------------------------------
     |  Working days
     * ------------------------------------------------------------------ */

    /** Number of leave days between two dates, skipping Sundays and holidays. */
    public static function workingDays(Carbon $from, Carbon $to): int
    {
        $holidays = static::holidaysBetween($from, $to);
        $days = 0;

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if (!$date->isSunday() && !isset($holidays[$date->toDateString()])) {
                $days++;
            }
        }

        return $days;
    }

    /** Why a single date can't be taken as leave, or null when it can. */
    public static function nonWorkingReason(Carbon $date): ?string
    {
        if ($date->isSunday()) {
            return $date->format('d-m-Y') . ' is a Sunday';
        }
        if (isset(static::holidaysBetween($date, $date)[$date->toDateString()])) {
            return $date->format('d-m-Y') . ' is a holiday';
        }

        return null;
    }

    /** Active holidays between two dates, keyed by Y-m-d. */
    public static function holidaysBetween(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('holiday')
            ->where('status', 1)
            ->where('trash', 'NO')
            ->whereNotNull('day')
            ->whereBetween('year', [$from->year, $to->year])
            ->get(['day', 'month', 'year']);

        $dates = [];
        foreach ($rows as $row) {
            if (!checkdate((int) $row->month, (int) $row->day, (int) $row->year)) {
                continue;
            }
            $date = Carbon::create((int) $row->year, (int) $row->month, (int) $row->day)->toDateString();
            if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                $dates[$date] = true;
            }
        }

        return $dates;
    }
}
