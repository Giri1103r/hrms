<?php

namespace App\Models\Holiday;

use App\Scopes\TrashScope;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holiday';
    protected $primaryKey = 'id';
    protected $fillable = [
        'day',
        'month',
        'year',
        'status',
        'trash',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $attributes = [
        'status' => 1,
        'trash' => 'NO',
    ];

    /**
     * Server-side DataTables listing with the day / month / year / status filters.
     */
    public function list()
    {
        $request = request();
        $query = $this->select('holiday.*');

        $org_total_counts = (clone $query)->count();

        if (isset($request->search['value']) && $request->search['value'] != '') {
            $search = $request->search['value'];

            // Let the search box match month names too ("jan", "March").
            $months = array_keys(array_filter(
                self::monthNames(),
                fn ($name) => stripos($name, $search) !== false
            ));

            $query->where(function ($query) use ($search, $months) {
                $query->orWhere('holiday.year', 'LIKE', '%' . $search . '%');
                if (ctype_digit($search) && (int) $search >= 1 && (int) $search <= 31) {
                    $query->orWhere('holiday.day', (int) $search);
                }
                if ($months) {
                    $query->orWhereIn('holiday.month', $months);
                }
            });
        }

        if ($request->filled('day')) {
            $query->where('holiday.day', (int) $request->day);
        }

        if ($request->filled('month')) {
            $query->where('holiday.month', (int) $request->month);
        }

        if ($request->filled('year')) {
            $query->where('holiday.year', (int) $request->year);
        }

        if ($request->filled('status')) {
            $query->where('holiday.status', decryptId($request->status));
        }

        $total_records = (clone $query)->count();

        $query->orderBy('holiday.year', 'DESC')
            ->orderBy('holiday.month', 'DESC')
            ->orderBy('holiday.day', 'DESC')
            ->orderBy('holiday.id', 'DESC');

        if ($request->length != -1) {
            $query->offset($request->start)->limit($request->length);
        }

        return [
            'data' => $query->get(),
            'total_records' => $org_total_counts,
            'filter_records' => $total_records,
        ];
    }

    /** Distinct years present in the table, newest first (for the filter). */
    public function yearList()
    {
        return $this->select('year')->distinct()->orderBy('year', 'DESC')->pluck('year');
    }

    public static function monthNames(): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = date('F', mktime(0, 0, 0, $m, 1));
        }
        return $months;
    }

    public static function monthName($month): string
    {
        return self::monthNames()[(int) $month] ?? '';
    }

    /** "Monday" etc. for a valid date, '' when the day is missing or the date is invalid. */
    public static function weekdayName($day, $month, $year): string
    {
        if (!$day || !checkdate((int) $month, (int) $day, (int) $year)) {
            return '';
        }
        return date('l', mktime(0, 0, 0, (int) $month, (int) $day, (int) $year));
    }

    protected static function booted()
    {
        static::addGlobalScope(new TrashScope('holiday'));
    }
}
