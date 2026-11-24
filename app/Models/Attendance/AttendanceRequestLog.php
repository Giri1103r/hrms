<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * History of every action taken on a daily attendance request.
 */
class AttendanceRequestLog extends Model
{
    protected $table = 'attendance_request_log';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'remarks',
        'action_by',
        'created_at',
    ];

    public const LABELS = [
        'created' => 'Marked',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'direct_approved' => 'Direct Approval (Manager)',
    ];

    public static function record(int $requestId, string $action, ?string $remarks = null): self
    {
        return static::create([
            'request_id' => $requestId,
            'action' => $action,
            'remarks' => $remarks,
            'action_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
