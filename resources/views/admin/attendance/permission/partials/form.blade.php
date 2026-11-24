{{--
    Permission request form shared by Add and Edit.
    $pr is null on Add. On Add a Manager/Admin picks the employee; an employee
    raising for themselves gets their own details pre-filled (no dropdown).
--}}
@php
    $isEdit = isset($pr) && $pr;
    $isManager = $isManager ?? false;
@endphp

<form method="POST" id="{{ $isEdit ? 'permissionrequestedit' : 'permissionrequestadd' }}"
    action="{{ admin_url($isEdit ? 'attendance-management/permission/edit/submit' : 'attendance-management/permission/add/submit') }}">
    @csrf
    @if ($isEdit)
        <input type="hidden" name="id" value="{{ encryptId($pr->id) }}">
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">{{ __('attendance.employee_details') }}</h4>
    </div>

    @if (!$isEdit && $isManager)
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="form-group form-input">
                    <label class="form-label require" for="employee_id">{{ __('attendance.employee') }}</label>
                    <select name="employee_id" id="employee_id" class="form-control single-select" style="width: 100%;">
                        <option value="">{{ __('attendance.select_employee') }}</option>
                        @foreach ($employeeList as $emp)
                            <option value="{{ encryptId($emp->id) }}" @selected(old('employee_id') && decryptId(old('employee_id')) == $emp->id)>
                                {{ $emp->emp_id }} - {{ $emp->emp_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                    @error('employee_ref') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="col-md-8 mb-3 d-flex align-items-end">
                <div class="alert alert-info py-2 mb-0 w-100">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    {{ __('attendance.direct_note', ['item' => __('leftmenu.menu_30')]) }}
                </div>
            </div>
        </div>
    @else
        @error('employee_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        @error('employee_ref') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @endif

    @include('admin.attendance.permission.partials.employee-details', ['employee' => $employee])

    @if (!$isManager && !$isEdit)
        <div class="alert alert-info py-2">
            <i class="fa-solid fa-circle-info me-1"></i>
            {{ __('attendance.routed_note', ['name' => ($employee->reporting_manager_name ?? null) ?: __('attendance.not_set')]) }}
        </div>
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">{{ __('attendance.permission_details') }}</h4>
    </div>
    <div class="alert alert-warning py-2">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        An employee may raise at most {{ \App\Models\Attendance\PermissionRequest::MONTHLY_REQUEST_LIMIT }} permission requests
        totalling {{ \App\Models\Attendance\PermissionRequest::MONTHLY_HOURS_LIMIT }} hours per calendar month.
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="permission_date">{{ __('attendance.permission_date') }}</label>
                <div class="input-group">
                    <input type="text" name="permission_date" id="permission_date" autocomplete="off"
                        class="form-control @error('permission_date') is-invalid @enderror" placeholder="dd-mm-yyyy"
                        value="{{ old('permission_date', $isEdit ? $pr->permission_date->format('Y-m-d') : '') }}">
                    <span class="input-group-text"><i class="fa-regular fa-calendar"></i></span>
                </div>
                @error('permission_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="time_from">{{ __('attendance.time_from') }}</label>
                <div class="input-group">
                    <input type="text" name="time_from" id="time_from" autocomplete="off"
                        class="form-control @error('time_from') is-invalid @enderror" placeholder="HH:MM"
                        value="{{ old('time_from', $isEdit ? substr($pr->time_from, 0, 5) : '') }}">
                    <span class="input-group-text"><i class="fa-regular fa-clock"></i></span>
                </div>
                @error('time_from') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="time_to">{{ __('attendance.time_to') }}</label>
                <div class="input-group">
                    <input type="text" name="time_to" id="time_to" autocomplete="off"
                        class="form-control @error('time_to') is-invalid @enderror" placeholder="HH:MM"
                        value="{{ old('time_to', $isEdit ? substr($pr->time_to, 0, 5) : '') }}">
                    <span class="input-group-text"><i class="fa-regular fa-clock"></i></span>
                </div>
                @error('time_to') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-12 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="reason">{{ __('attendance.reason') }}</label>
                <textarea name="reason" id="reason" rows="2" maxlength="500"
                    class="form-control @error('reason') is-invalid @enderror"
                    placeholder="{{ __('attendance.reason') }}">{{ old('reason', $isEdit ? $pr->reason : '') }}</textarea>
                @error('reason') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-2">
        <x-button-submit></x-button-submit>
    </div>
</form>

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(function() {
            flatpickr('#permission_date', {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd-m-Y',
                allowInput: true,
            });
            flatpickr('#time_from', {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                allowInput: true,
            });
            flatpickr('#time_to', {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                allowInput: true,
            });
        });
    </script>
@endpush

@if (!$isEdit && $isManager)
    @push('script')
        <script type="text/javascript" nonce="projectcab">
            $(function() {
                var fields = ['emp_id', 'emp_name', 'designation', 'company_name', 'location_name', 'reporting_manager'];

                function resetDetails() {
                    fields.forEach(function(f) { $('#detail_' + f).text('-'); });
                }

                function loadEmployee(id) {
                    resetDetails();
                    if (!id) {
                        return;
                    }
                    $.ajax({
                        url: "{{ admin_url('attendance-management/permission/employee-details') }}",
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { employee_id: id },
                        success: function(res) {
                            var d = res.data;
                            fields.forEach(function(f) { $('#detail_' + f).text(d[f]); });
                        }
                    });
                }

                $('#employee_id').on('change', function() { loadEmployee($(this).val()); });
                if ($('#employee_id').val()) {
                    loadEmployee($('#employee_id').val());
                }
            });
        </script>
    @endpush
@endif
