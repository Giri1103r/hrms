{{--
    Compensatory off request form shared by Add and Edit.
    $co is null on Add. On Add a Manager/Admin picks the employee; an employee
    raising for themselves gets their own details pre-filled (no dropdown).
--}}
@php
    $isEdit = isset($co) && $co;
    $isManager = $isManager ?? false;
@endphp

<form method="POST" id="{{ $isEdit ? 'compoffrequestedit' : 'compoffrequestadd' }}"
    action="{{ admin_url($isEdit ? 'attendance-management/compo-off/edit/submit' : 'attendance-management/compo-off/add/submit') }}">
    @csrf
    @if ($isEdit)
        <input type="hidden" name="id" value="{{ encryptId($co->id) }}">
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
                    {{ __('attendance.direct_note', ['item' => __('leftmenu.menu_31')]) }}
                </div>
            </div>
        </div>
    @else
        @error('employee_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        @error('employee_ref') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @endif

    @include('admin.attendance.compoff.partials.employee-details', ['employee' => $employee])

    @if (!$isManager && !$isEdit)
        <div class="alert alert-info py-2">
            <i class="fa-solid fa-circle-info me-1"></i>
            {{ __('attendance.routed_note', ['name' => ($employee->reporting_manager_name ?? null) ?: __('attendance.not_set')]) }}
        </div>
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">{{ __('attendance.compoff_details') }}</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="worked_date">{{ __('attendance.worked_date') }}</label>
                <input type="date" name="worked_date" id="worked_date"
                    class="form-control @error('worked_date') is-invalid @enderror"
                    value="{{ old('worked_date', $isEdit ? $co->worked_date->format('Y-m-d') : '') }}">
                @error('worked_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                <small class="text-muted">{{ __('attendance.worked_date_hint') }}</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="compoff_date">{{ __('attendance.compoff_date') }}</label>
                <input type="date" name="compoff_date" id="compoff_date"
                    class="form-control @error('compoff_date') is-invalid @enderror"
                    value="{{ old('compoff_date', $isEdit ? $co->compoff_date->format('Y-m-d') : '') }}">
                @error('compoff_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                <small class="text-muted">{{ __('attendance.compoff_date_hint') }}</small>
            </div>
        </div>
        <div class="col-md-12 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="reason">{{ __('attendance.reason') }}</label>
                <textarea name="reason" id="reason" rows="2" maxlength="500"
                    class="form-control @error('reason') is-invalid @enderror"
                    placeholder="{{ __('attendance.reason') }}">{{ old('reason', $isEdit ? $co->reason : '') }}</textarea>
                @error('reason') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-2">
        <x-button-submit></x-button-submit>
    </div>
</form>

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
                        url: "{{ admin_url('attendance-management/compo-off/employee-details') }}",
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
