@extends('admin.layouts.admin')
@section('title', __('leftmenu.menu_29'))
@section('pageurl', admin_url('attendance-management/daily-attendance/list'))

@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="align-back-btc d-flex justify-content-end align-items-center">
                                <x-button-back href="{{ admin_url('attendance-management/daily-attendance/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="basic-form">
                                <form method="POST" id="attendanceadd" action="{{ admin_url('attendance-management/daily-attendance/add/submit') }}">
                                    @csrf

                                    <div class="card-header-inner px-3 pb-2">
                                        <h4 class="text-white">{{ __('attendance.attendance_details') }}</h4>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label require" for="attendance_date">{{ __('attendance.attendance_date') }}</label>
                                                <div class="input-group">
                                                    <input type="text" name="attendance_date" id="attendance_date" autocomplete="off"
                                                        class="form-control @error('attendance_date') is-invalid @enderror" placeholder="dd-mm-yyyy"
                                                        value="{{ old('attendance_date') }}">
                                                    <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                                                </div>
                                                @error('attendance_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label require" for="attendance_type">{{ __('attendance.attendance_type') }}</label>
                                                <select name="attendance_type" id="attendance_type" class="form-control single-select" style="width: 100%;">
                                                    @foreach ($typeList as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('attendance_type') == $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error('attendance_type') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label" for="reason">{{ __('common.remarks') }}</label>
                                                <textarea name="reason" id="reason" rows="2" maxlength="500"
                                                    class="form-control @error('reason') is-invalid @enderror"
                                                    placeholder="{{ __('common.remarks') }}">{{ old('reason') }}</textarea>
                                                @error('reason') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    @error('selected_employee_ids') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                                    <div class="card-header-inner px-3 pb-2">
                                        <h4 class="text-white">{{ $isManager ? __('attendance.select_employees') : __('attendance.employee') }}</h4>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table primary-table-bordered table-bordered table-striped w-100">
                                            <thead class="thead-primary">
                                                <tr>
                                                    <th style="width: 40px;">
                                                        @if ($isManager)
                                                            <input type="checkbox" id="select-all-employees">
                                                        @endif
                                                    </th>
                                                    <th>{{ __('attendance.employee_id') }}</th>
                                                    <th>{{ __('attendance.employee_name') }}</th>
                                                    <th>{{ __('attendance.designation') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input type="checkbox" class="employee-check" name="employee_ids[]" value="{{ encryptId($self->id) }}" checked></td>
                                                    <td>{{ $self->emp_id }}</td>
                                                    <td>{{ $self->emp_name }} <span class="badge bg-secondary">{{ __('attendance.myself') }}</span></td>
                                                    <td>{{ $self->designation ?: '-' }}</td>
                                                </tr>
                                                @foreach ($reports as $emp)
                                                    <tr>
                                                        <td><input type="checkbox" class="employee-check" name="employee_ids[]" value="{{ encryptId($emp->id) }}"></td>
                                                        <td>{{ $emp->emp_id }}</td>
                                                        <td>{{ $emp->emp_name }}</td>
                                                        <td>{{ $emp->designation ?: '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($isManager && $reports->isEmpty())
                                        <p class="text-muted small">{{ __('attendance.no_reports') }}</p>
                                    @endif

                                    <div class="d-flex justify-content-end gap-2 mt-2">
                                        <x-button-submit></x-button-submit>
                                    </div>
                                </form>
                            </div>
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
            flatpickr('#attendance_date', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd-m-Y',
                maxDate: 'today',
                allowInput: true,
            });

            $('#select-all-employees').on('change', function() {
                $('.employee-check').prop('checked', $(this).is(':checked'));
            });

            $(document).on('change', '.employee-check', function() {
                if (!$(this).is(':checked')) {
                    $('#select-all-employees').prop('checked', false);
                } else if ($('.employee-check:checked').length === $('.employee-check').length) {
                    $('#select-all-employees').prop('checked', true);
                }
            });
        });
    </script>
@endpush
