@extends('admin.layouts.admin')
@section('title', $approvalMode ? 'Shift Change Request Approval' : 'Shift Change Request View')
@section('pageurl', admin_url('shift-management/shift-change-request/list'))

@php
    use App\Models\Shift\ShiftChangeRequest;
    $fmtShift = fn ($s) => $s
        ? $s->shift_name . ' (' . date('h:i A', strtotime($s->start_time)) . ' - ' . date('h:i A', strtotime($s->end_time)) . ')'
        : '-';
@endphp

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ $scr->request_no }} {!! $statusBadge !!}</h4>
                            <div class="align-back-btc d-flex justify-content-end align-items-center gap-2">
                                @if (!$approvalMode && $scr->canApprove())
                                    <a href="{{ admin_url('shift-management/shift-change-request/approval/' . encryptId($scr->id)) }}"
                                        class="btn btn-success">Approve / Reject</a>
                                @endif
                                @if ($scr->canEdit())
                                    <a href="{{ admin_url('shift-management/shift-change-request/edit/' . encryptId($scr->id)) }}"
                                        class="btn btn-primary">Edit</a>
                                @endif
                                <x-button-back href="{{ admin_url('shift-management/shift-change-request/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">Employee Details</h4>
                            </div>
                            @include('admin.shift.change-request.partials.employee-details', [
                                'employee' => $employee,
                                'currentShiftLabel' => 'Employee\'s Shift Now',
                            ])

                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">Request Details</h4>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Shift at Request Time</label>
                                    <div class="view_data">{{ $fmtShift($currentShift) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Shift Needed</label>
                                    <div class="view_data fw-semibold">{{ $fmtShift($requestedShift) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Status</label>
                                    <div class="view_data">{!! $statusBadge !!}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Raised By</label>
                                    <div class="view_data">{{ getUsername($scr->created_by) }} on {{ Displaydatetimeformat($scr->created_at) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Approver</label>
                                    <div class="view_data">{{ $scr->approver_user_id ? getUsername($scr->approver_user_id) : '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">Reason</label>
                                    <div class="view_data">{{ $scr->reason ?: '-' }}</div>
                                </div>
                                @if ($scr->request_status != ShiftChangeRequest::PENDING)
                                    <div class="mb-3 col-md-4 form-input">
                                        <label class="form-label view_label">{{ ShiftChangeRequest::STATUS_LABELS[$scr->request_status] }} By</label>
                                        <div class="view_data">
                                            {{ $scr->action_by ? getUsername($scr->action_by) : '-' }}
                                            {{ $scr->action_at ? 'on ' . Displaydatetimeformat($scr->action_at) : '' }}
                                        </div>
                                    </div>
                                    <div class="mb-3 col-md-8 form-input">
                                        <label class="form-label view_label">Remarks</label>
                                        <div class="view_data">{{ $scr->action_remarks ?: '-' }}</div>
                                    </div>
                                @endif
                            </div>

                            @if ($approvalMode)
                                <div class="card-header-inner px-3 pb-2">
                                    <h4 class="text-white">Approval</h4>
                                </div>
                                <form method="POST" action="{{ admin_url('shift-management/shift-change-request/approval/submit') }}" id="shiftrequestapproval">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ encryptId($scr->id) }}">
                                    @error('decision') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label" for="action_remarks">Remarks <small class="text-muted">(required when rejecting)</small></label>
                                                <textarea name="action_remarks" id="action_remarks" rows="2" maxlength="500"
                                                    class="form-control @error('action_remarks') is-invalid @enderror"
                                                    placeholder="Remarks">{{ old('action_remarks') }}</textarea>
                                                @error('action_remarks') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end gap-2">
                                            <x-button-approve></x-button-approve>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        Approve changes the employee's shift to <strong>{{ $requestedShift->shift_name ?? '-' }}</strong>.
                                        Reject keeps the current shift unchanged. The person who raised the request is notified either way.
                                    </p>
                                </form>
                            @endif

                            <div class="card-header-inner px-3 pb-2 mt-3">
                                <h4 class="text-white">Request Log</h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table primary-table-bordered table-bordered table-striped w-100">
                                    <thead class="thead-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>Action</th>
                                            <th>From Shift</th>
                                            <th>To Shift</th>
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
                                                <td>{{ ShiftChangeRequest::shiftName($log->from_shift_id) }}</td>
                                                <td>{{ ShiftChangeRequest::shiftName($log->to_shift_id) }}</td>
                                                <td>{{ $log->remarks ?: '-' }}</td>
                                                <td>{{ $log->action_by ? getUsername($log->action_by) : '-' }}</td>
                                                <td>{{ Displaydatetimeformat($log->created_at) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center">No log entries</td></tr>
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
                $('#shiftrequestapproval button[type=submit]').on('click', function() { clicked = this.name; });
                $('#shiftrequestapproval').on('submit', function(e) {
                    if (clicked === 'rejected' && !$.trim($('#action_remarks').val())) {
                        e.preventDefault();
                        $('#action_remarks').addClass('is-invalid').focus();
                        return false;
                    }
                    return confirm(clicked === 'approved'
                        ? 'Approve this request? The employee\'s shift will be changed.'
                        : 'Reject this request? The current shift will stay as it is.');
                });
            });
        </script>
    @endpush
@endif
