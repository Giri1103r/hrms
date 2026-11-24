@extends('admin.layouts.admin')
@section('title', __('leftmenu.menu_32'))
@section('pageurl', admin_url('attendance-management/attendance-approval/list'))

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('attendance.pending_attendance_approvals') }}</h4>
                        </div>

                        <div class="card-body">
                            @if ($rows->isEmpty())
                                <p class="text-muted mb-0">{{ __('attendance.no_pending_approval') }}</p>
                            @else
                                <form method="POST" id="attendanceapproval" action="{{ admin_url('attendance-management/attendance-approval/bulk-decide') }}">
                                    @csrf
                                    @error('ids') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
                                    @error('decision') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                                    <div class="table-responsive">
                                        <table class="table primary-table-bordered table-bordered table-striped w-100">
                                            <thead class="thead-primary">
                                                <tr>
                                                    <th style="width: 40px;"><input type="checkbox" id="select-all-rows"></th>
                                                    <th>{{ __('attendance.request_no') }}</th>
                                                    <th>{{ __('attendance.employee_id') }}</th>
                                                    <th>{{ __('attendance.employee_name') }}</th>
                                                    <th>{{ __('attendance.designation') }}</th>
                                                    <th>{{ __('attendance.attendance_date') }}</th>
                                                    <th>{{ __('common.type') }}</th>
                                                    <th>{{ __('common.remarks') }}</th>
                                                    <th>{{ __('attendance.marked_on') }}</th>
                                                    <th>{{ __('common.view') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($rows as $row)
                                                    <tr>
                                                        <td><input type="checkbox" class="row-check" name="ids[]" value="{{ encryptId($row->id) }}"></td>
                                                        <td>{{ $row->request_no }}</td>
                                                        <td>{{ $row->emp_id }}</td>
                                                        <td>{{ $row->emp_name }}</td>
                                                        <td>{{ $row->designation ?: '-' }}</td>
                                                        <td>{{ Displaydateformat($row->attendance_date) }}</td>
                                                        <td>{{ $typeList[$row->attendance_type] ?? '-' }}</td>
                                                        <td>{{ $row->reason ?: '-' }}</td>
                                                        <td>{{ Displaydatetimeformat($row->created_at) }}</td>
                                                        <td>
                                                            <a href="{{ admin_url('attendance-management/daily-attendance/view/' . encryptId($row->id)) }}" title="View">
                                                                <i class="fa-solid fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="row mt-3">
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
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(function() {
            $('#select-all-rows').on('change', function() {
                $('.row-check').prop('checked', $(this).is(':checked'));
            });

            $(document).on('change', '.row-check', function() {
                if (!$(this).is(':checked')) {
                    $('#select-all-rows').prop('checked', false);
                } else if ($('.row-check:checked').length === $('.row-check').length) {
                    $('#select-all-rows').prop('checked', true);
                }
            });

            var clicked = null;
            $('#attendanceapproval button[type=submit]').on('click', function() { clicked = this.name; });
            $('#attendanceapproval').on('submit', function(e) {
                if (!$('.row-check:checked').length) {
                    e.preventDefault();
                    alert('{{ __('attendance.select_one_entry_alert') }}');
                    return false;
                }
                if (clicked === 'rejected' && !$.trim($('#action_remarks').val())) {
                    e.preventDefault();
                    $('#action_remarks').addClass('is-invalid').focus();
                    return false;
                }
                return confirm(clicked === 'approved' ? '{{ __('attendance.confirm_approve') }}' : '{{ __('attendance.confirm_reject') }}');
            });
        });
    </script>
@endpush
