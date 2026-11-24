@extends('admin.layouts.admin')
@section('title', __('leftmenu.menu_30'))
@section('pageurl', admin_url('attendance-management/permission/list'))

@php
    use App\Models\Attendance\PermissionRequest;
@endphp

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">{{ $pr->request_no }} {!! $statusBadge !!}</h4>
                            <div class="align-back-btc d-flex justify-content-end align-items-center gap-2">
                                @if (!$approvalMode && $pr->canApprove())
                                    <a href="{{ admin_url('attendance-management/permission/approval/' . encryptId($pr->id)) }}"
                                        class="btn btn-success">{{ __('common.approve_reject') }}</a>
                                @endif
                                @if ($pr->canEdit())
                                    <a href="{{ admin_url('attendance-management/permission/edit/' . encryptId($pr->id)) }}"
                                        class="btn btn-primary">{{ __('common.edit') }}</a>
                                @endif
                                <x-button-back href="{{ admin_url('attendance-management/permission/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">{{ __('attendance.employee_details') }}</h4>
                            </div>
                            @include('admin.attendance.permission.partials.employee-details', ['employee' => $employee])

                            <div class="card-header-inner px-3 pb-2">
                                <h4 class="text-white">{{ __('attendance.request_details') }}</h4>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.permission_date') }}</label>
                                    <div class="view_data">{{ Displaydateformat($pr->permission_date) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.time') }}</label>
                                    <div class="view_data fw-semibold">
                                        {{ date('h:i A', strtotime($pr->time_from)) }} - {{ date('h:i A', strtotime($pr->time_to)) }}
                                    </div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.duration') }}</label>
                                    <div class="view_data">{{ $pr->duration_hours }} hrs</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('common.status') }}</label>
                                    <div class="view_data">{!! $statusBadge !!}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.raised_by') }}</label>
                                    <div class="view_data">{{ getUsername($pr->created_by) }} {{ __('attendance.on_date', ['date' => Displaydatetimeformat($pr->created_at)]) }}</div>
                                </div>
                                <div class="mb-3 col-md-4 form-input">
                                    <label class="form-label view_label">{{ __('attendance.approver') }}</label>
                                    <div class="view_data">{{ $pr->approver_user_id ? getUsername($pr->approver_user_id) : '-' }}</div>
                                </div>
                                <div class="mb-3 col-md-8 form-input">
                                    <label class="form-label view_label">{{ __('attendance.reason') }}</label>
                                    <div class="view_data">{{ $pr->reason ?: '-' }}</div>
                                </div>
                                @if ($pr->request_status != PermissionRequest::PENDING)
                                    <div class="mb-3 col-md-4 form-input">
                                        <label class="form-label view_label">{{ __('attendance.decided_by', ['status' => PermissionRequest::STATUS_LABELS[$pr->request_status]]) }}</label>
                                        <div class="view_data">
                                            {{ $pr->action_by ? getUsername($pr->action_by) : '-' }}
                                            {{ $pr->action_at ? __('attendance.on_date', ['date' => Displaydatetimeformat($pr->action_at)]) : '' }}
                                        </div>
                                    </div>
                                    <div class="mb-3 col-md-8 form-input">
                                        <label class="form-label view_label">{{ __('attendance.action_remarks') }}</label>
                                        <div class="view_data">{{ $pr->action_remarks ?: '-' }}</div>
                                    </div>
                                @endif
                            </div>

                            @if ($approvalMode)
                                <div class="card-header-inner px-3 pb-2">
                                    <h4 class="text-white">{{ __('attendance.approval') }}</h4>
                                </div>
                                <form method="POST" action="{{ admin_url('attendance-management/permission/approval/submit') }}" id="permissionrequestapproval">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ encryptId($pr->id) }}">
                                    @error('decision') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label" for="action_remarks">{{ __('common.remarks') }} <small class="text-muted">{{ __('attendance.remarks_reject_hint') }}</small></label>
                                                <textarea name="action_remarks" id="action_remarks" rows="2" maxlength="500"
                                                    class="form-control @error('action_remarks') is-invalid @enderror"
                                                    placeholder="{{ __('common.remarks') }}">{{ old('action_remarks') }}</textarea>
                                                @error('action_remarks') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end gap-2">
                                            <x-button-approve></x-button-approve>
                                            <x-button-reject></x-button-reject>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0">
                                        {{ __('attendance.approve_note') }}
                                    </p>
                                </form>
                            @endif

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

@if ($approvalMode)
    @push('script')
        <script type="text/javascript" nonce="projectcab">
            $(function() {
                var clicked = null;
                $('#permissionrequestapproval button[type=submit]').on('click', function() { clicked = this.name; });
                $('#permissionrequestapproval').on('submit', function(e) {
                    if (clicked === 'rejected' && !$.trim($('#action_remarks').val())) {
                        e.preventDefault();
                        $('#action_remarks').addClass('is-invalid').focus();
                        return false;
                    }
                    return confirm(clicked === 'approved' ? '{{ __('attendance.confirm_approve_request') }}' : '{{ __('attendance.confirm_reject_request') }}');
                });
            });
        </script>
    @endpush
@endif
