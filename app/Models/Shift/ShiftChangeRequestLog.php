<?php

namespace App\Models\Shift;

use Illuminate\Database\Eloquent\Model;

/**
 * History of every action taken on a shift change request.
 */
class ShiftChangeRequestLog extends Model
{
    protected $table = 'shift_change_request_log';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'from_shift_id',
        'to_shift_id',
        'remarks',
        'action_by',
        'created_at',
    ];

    public const LABELS = [
        'created' => 'Request Raised',
        'updated' => 'Request Updated',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'direct_approved' => 'Direct Approval (Manager)',
    ];

    public static function record(int $requestId, string $action, $fromShift, $toShift, ?string $remarks = null): self
    {
        return static::create([
            'request_id' => $requestId,
            'action' => $action,
            'from_shift_id' => $fromShift,
            'to_shift_id' => $toShift,
            'remarks' => $remarks,
            'action_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }
}
