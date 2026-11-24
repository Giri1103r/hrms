<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Exception;

use App\Models\Attendance\AttendanceRequest;

/**
 * Attendance Management → Attendance Approval.
 *
 * Manager-only (menu access is restricted to Admin/Manager via
 * master_role_permission): lists attendance marked by the manager's direct
 * reports that is still pending, with a checkbox against each row, and lets
 * the manager approve or reject the selected rows in one bulk action. Single
 * approve/reject rules live in App\Models\Attendance\AttendanceRequest::decide().
 */
class AttendanceApprovalController extends Controller
{
    private const BASE = 'attendance-management/attendance-approval/';

    public function index()
    {
        if (!AttendanceRequest::isManager()) {
            abort(403);
        }

        return view('admin.attendance.approval.list', [
            'rows' => AttendanceRequest::pendingForApprover(),
            'typeList' => AttendanceRequest::TYPE_LABELS,
        ]);
    }

    public function bulkDecide(Request $request)
    {
        if (!AttendanceRequest::isManager()) {
            abort(403);
        }

        $decision = $request->has('approved') ? AttendanceRequest::APPROVED
            : ($request->has('rejected') ? AttendanceRequest::REJECTED : null);

        $validator = validator($request->all() + ['decision' => $decision], [
            'ids' => ['required', 'array', 'min:1'],
            'decision' => ['required', Rule::in([AttendanceRequest::APPROVED, AttendanceRequest::REJECTED])],
            'action_remarks' => [Rule::requiredIf($decision === AttendanceRequest::REJECTED), 'nullable', 'string', 'max:500'],
        ], [
            'ids.required' => 'Please select at least one attendance entry',
            'decision.required' => 'Please approve or reject the selected entries',
            'action_remarks.required' => 'Please give a reason for rejecting',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        $decided = 0;
        $skipped = 0;

        try {
            foreach ($request->ids as $encryptedId) {
                $ar = AttendanceRequest::find(decryptId($encryptedId));
                if (!$ar || !$ar->canApprove()) {
                    $skipped++;
                    continue;
                }

                $ar->decide($decision, $request->action_remarks);
                $decided++;
            }

            $word = $decision === AttendanceRequest::APPROVED ? 'approved' : 'rejected';
            $message = $decided . ' entr' . ($decided === 1 ? 'y' : 'ies') . ' ' . $word;
            if ($skipped) {
                $message .= '; ' . $skipped . ' skipped (no longer pending)';
            }

            Session::flash($decided ? 'success' : 'error', $decided ? $message : 'Nothing was ' . $word . '; the selected entries are no longer pending');

            return redirect(admin_url(self::BASE . 'list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Please try after some time');
            return redirect(admin_url(self::BASE . 'list'));
        }
    }
}
