<?php

namespace App\Models\Leave;

use Illuminate\Database\Eloquent\Model;

/**
 * History of every action taken on a leave request.
 */
class LeaveRequestLog extends Model
{
    protected $table = 'leave_request_log';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'remarks',
        'action_by',
        'created_at',
    ];

    public const LABELS = [
        'applied' => 'Leave Applied',
        'updated' => 'Leave Updated',
        'confirmed' => 'Confirmed',
        'cancelled' => 'Cancelled',
        'direct_confirmed' => 'Confirmed Directly (Approver)',
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
