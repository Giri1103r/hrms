@extends('admin.layouts.admin')
@section('title', __('leftmenu.menu_29'))
@section('pageurl', admin_url('attendance-management/daily-attendance/list'))

@php
    use App\Models\Attendance\AttendanceRequest;
@endphp

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ $ar->request_no }} {!! $statusBadge !!}</h4>
                            <div class="align-back-btc d-flex justify-content-end align-items-center gap-2">
                                @if ($ar->canApprove())
                                    <a href="{{ admin_url('attendance-management/attendance-approval/list') }}"
                                        class="btn btn-success">{{ __('attendance.go_to_attendance_approval') }}</a>
                                @endif
                                <x-button-back href="{{ admin_url('attendance-management/daily-attendance/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">{{ __('attendance.employee_details') }}</h4>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.employee_id') }}</label>
                                    <div class="view_data">{{ $employee->emp_id ?? '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.employee_name') }}</label>
                                    <div class="view_data">{{ $employee->emp_name ?? '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.designation') }}</label>
                                    <div class="view_data">{{ ($employee->designation ?? null) ?: '-' }}</div>
                                </div>
                            </div>

                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">{{ __('attendance.request_details') }}</h4>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.attendance_date') }}</label>
                                    <div class="view_data">{{ Displaydateformat($ar->attendance_date) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('common.type') }}</label>
                                    <div class="view_data fw-semibold">{{ AttendanceRequest::TYPE_LABELS[$ar->attendance_type] ?? '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('common.status') }}</label>
                                    <div class="view_data">{!! $statusBadge !!}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.marked_by') }}</label>
                                    <div class="view_data">{{ getUsername($ar->created_by) }} {{ __('attendance.on_date', ['date' => Displaydatetimeformat($ar->created_at)]) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.approver') }}</label>
                                    <div class="view_data">{{ $ar->approver_user_id ? getUsername($ar->approver_user_id) : '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-8 form-input">
                                    <label class="form-label view_label">{{ __('common.remarks') }}</label>
                                    <div class="view_data">{{ $ar->reason ?: '-' }}</div>
                                </div>
                                @if ($ar->request_status != AttendanceRequest::PENDING)
                                    <div class="mb-3 col-md-4 form-input">
                                        <label class="form-label view_label">{{ __('attendance.decided_by', ['status' => AttendanceRequest::STATUS_LABELS[$ar->request_status]]) }}</label>
                                        <div class="view_data">
                                            {{ $ar->action_by ? getUsername($ar->action_by) : '-' }}
                                            {{ $ar->action_at ? __('attendance.on_date', ['date' => Displaydatetimeformat($ar->action_at)]) : '' }}
                                        </div>
                                    </div>
                                    <div class="mb-3 col-md-8 form-input">
                                        <label class="form-label view_label">{{ __('attendance.action_remarks') }}</label>
                                        <div class="view_data">{{ $ar->action_remarks ?: '-' }}</div>
                                    </div>
                                @endif
                            </div>

                            <div class="card-header-inner px-3 pb-2 mt-3">
                                <h4 class="text-white">{{ __('attendance.request_log') }}</h4>
                            </div>
                            <div class="table-responsive">
                                <table class="table primary-table-bordered table-bordered table-striped w-100">
                                    <thead class="thead-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>{{ __('common.action') }}</th>
                                            <th>{{ __('common.remarks') }}</th>
                                            <th>{{ __('attendance.by') }}</th>
                                            <th>{{ __('common.date') }}</th>
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
                                            <tr><td colspan="5" class="text-center">{{ __('attendance.no_log_entries') }}</td></tr>
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
