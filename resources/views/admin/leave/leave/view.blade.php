@extends('admin.layouts.admin')
@section('title', $approvalMode ? 'Leave Approval' : 'Leave View')
@section('pageurl', admin_url('leave-management/leave/list'))

@php use App\Models\Leave\LeaveRequest; @endphp

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ $leave->request_no }} {!! $statusBadge !!}</h4>
                            <div class="align-back-btc d-flex justify-content-end align-items-center gap-2">
                                @if (!$approvalMode && $leave->canApprove())
                                    <a href="{{ admin_url('leave-management/leave/approval/' . encryptId($leave->id)) }}"
                                        class="btn btn-success">Confirm / Cancel</a>
                                @endif
                                @if ($leave->canEdit())
                                    <a href="{{ admin_url('leave-management/leave/edit/' . encryptId($leave->id)) }}"
                                        class="btn btn-primary">Edit</a>
                                @endif
                                <x-button-back href="{{ admin_url('leave-management/leave/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">Employee Details</h4>
                            </div>
                            <div class="row">
                                @foreach ([
                                    'Employee ID' => $employee->emp_id ?? $leave->emp_id,
                                    'Employee Name' => $employee->emp_name ?? '-',
                                    'Designation' => ($employee->designation ?? null) ?: '-',
                                    'Company' => ($employee->company_name ?? null) ?: '-',
                                    'Location' => ($employee->location_name ?? null) ?: '-',
                                    'Reporting To' => ($employee->reporting_manager_name ?? null) ?: '-',
                                ] as $label => $text)
                                    <div class="mb-3 col-md-4 form-input">
                                        <label class="form-label view_label">{{ $label }}</label>
                                        <div class="view_data">{{ $text }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">Leave Details</h4>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Leave Type</label>
                                    <div class="view_data">{{ $leaveType->leave_type_name ?? '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Duration</label>
                                    <div class="view_data">{{ $leave->durationLabel() }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Date</label>
                                    <div class="view_data fw-semibold">{{ $leave->dateRangeLabel() }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Days</label>
                                    <div class="view_data">
                                        {{ LeaveRequest::formatDays($leave->total_days) }}
                                        <small class="text-muted">(Sundays and holidays not counted)</small>
                                    </div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">From Balance / Loss of Pay</label>
                                    <div class="view_data">
                                        {{ LeaveRequest::formatDays($leave->paid_days) }} paid
                                        @if ($leave->lop_days > 0)
                                            / <span class="text-danger fw-semibold">{{ LeaveRequest::formatDays($leave->lop_days) }} LOP</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Status</label>
                                    <div class="view_data">{!! $statusBadge !!}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Applied By</label>
                                    <div class="view_data">{{ getUsername($leave->created_by) }} on {{ Displaydatetimeformat($leave->created_at) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Approver</label>
                                    <div class="view_data">{{ $leave->approver_user_id ? getUsername($leave->approver_user_id) : '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Reason</label>
                                    <div class="view_data">{{ $leave->reason ?: '-' }}</div>
                                </div>
                                @if ($leave->leave_status != LeaveRequest::PENDING)
                                    <div class="mb-3 col-md-4 form-input">
                                        <label class="form-label view_label">{{ LeaveRequest::STATUS_LABELS[$leave->leave_status] }} By</label>
                                        <div class="view_data">
                                            {{ $leave->action_by ? getUsername($leave->action_by) : '-' }}
                                            {{ $leave->action_at ? 'on ' . Displaydatetimeformat($leave->action_at) : '' }}
                                        </div>
                                    </div>
                                    <div class="mb-3 col-md-8 form-input">
                                        <label class="form-label view_label">Remarks</label>
                                        <div class="view_data">{{ $leave->action_remarks ?: '-' }}</div>
                                    </div>
                                @endif
                            </div>

                            @if ($balance)
                                <div class="card-header-inner px-3 pb-2">
                                    <h4 class="text-white">{{ $balance['leave_type_name'] }} Balance &mdash; {{ $balance['year'] }}</h4>
                                </div>
                                <div class="row">
                                    @foreach ([
                                        'credited' => 'Credited So Far',
                                        'used' => 'Used (Confirmed)',
                                        'pending' => 'Waiting for Approval',
                                        'available' => 'Available Now',
                                    ] as $key => $label)
                                        <div class="mb-3 col-md-3 form-input">
                                            <label class="form-label view_label">{{ $label }}</label>
                                            <div class="view_data fw-semibold">{{ LeaveRequest::formatDays($balance[$key]) }} day(s)</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($approvalMode)
                                <div class="card-header-inner px-3 pb-2">
                                    <h4 class="text-white">Approval</h4>
                                </div>
                                <form method="POST" action="{{ admin_url('leave-management/leave/approval/submit') }}" id="leaveapproval">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ encryptId($leave->id) }}">
                                    @error('decision') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label" for="action_remarks">Remarks <small class="text-muted">(required when cancelling)</small></label>
                                                <textarea name="action_remarks" id="action_remarks" rows="2" maxlength="500"
                                                    class="form-control @error('action_remarks') is-invalid @enderror"
                                                    placeholder="Remarks">{{ old('action_remarks') }}</textarea>
                                                @error('action_remarks') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end gap-2">
                                            <button type="submit" name="confirmed" value="1" class="btn btn-success">Confirm</button>
                                            <button type="submit" name="cancelled" value="1" class="btn btn-danger">Cancel Leave</button>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        Confirm keeps {{ LeaveRequest::formatDays($leave->paid_days) }} day(s) deducted from the balance.
                                        Cancel gives them back. The person who applied is notified either way.
                                    </p>
                                </form>
                            @endif

                            <div class="card-header-inner px-3 pb-2 mt-3">
                                <h4 class="text-white">Leave Log</h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table primary-table-bordered table-bordered table-striped w-100">
                                    <thead class="thead-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>Action</th>
                                            <th>Remarks</th>
                                            <th>By</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($logs as $log)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $logLabels[$log->action] ?? ucfirst($log->action) }}</td>
                                                <td>{{ $log->remarks ?: '-' }}</td>
                                                <td>{{ $log->action_by ? getUsername($log->action_by) : '-' }}</td>
                                                <td>{{ Displaydatetimeformat($log->created_at) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center">No log entries</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@if ($approvalMode)
    @push('script')
        <script type="text/javascript" nonce="projectcab">
            $(function() {
                var clicked = null;
                $('#leaveapproval button[type=submit]').on('click', function() { clicked = this.name; });
                $('#leaveapproval').on('submit', function(e) {
                    if (clicked === 'cancelled' && !$.trim($('#action_remarks').val())) {
                        e.preventDefault();
                        $('#action_remarks').addClass('is-invalid').focus();
                        return false;
                    }
                    return confirm(clicked === 'confirmed'
                        ? 'Confirm this leave?'
                        : 'Cancel this leave? The days go back to the employee\'s balance.');
                });
            });
        </script>
    @endpush
@endif
