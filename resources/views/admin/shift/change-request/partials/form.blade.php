{{--
    Shift change request form shared by Add and Edit.
    $scr is null on Add. On Add a Manager/Admin picks the employee; an employee
    raising for themselves gets their own details pre-filled (no dropdown).
--}}
@php
    $isEdit = isset($scr) && $scr;
    $isManager = $isManager ?? false;
    $selectedShift = old('requested_shift_id', $isEdit ? encryptId($scr->requested_shift_id) : '');
    $currentShiftId = $isEdit ? $scr->current_shift_id : ($employee->shift ?? null);
@endphp

<form method="POST" id="{{ $isEdit ? 'shiftrequestedit' : 'shiftrequestadd' }}"
    action="{{ admin_url($isEdit ? 'shift-management/shift-change-request/edit/submit' : 'shift-management/shift-change-request/add/submit') }}">
    @csrf
    @if ($isEdit)
        <input type="hidden" name="id" value="{{ encryptId($scr->id) }}">
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Employee Details</h4>
    </div>

    @if (!$isEdit && $isManager)
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="form-group form-input">
                    <label class="form-label require" for="employee_id">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-control single-select" style="width: 100%;">
                        <option value="">Select Employee</option>
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
                    As a manager, your change is approved directly and applied to the employee immediately.
                </div>
            </div>
        </div>
    @else
        @error('employee_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        @error('employee_ref') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @endif

    @include('admin.shift.change-request.partials.employee-details', [
        'employee' => $employee,
        'currentShiftLabel' => 'Current Shift',
        'currentShiftText' => $isEdit ? \App\Models\Shift\ShiftChangeRequest::shiftName($scr->current_shift_id) : null,
    ])

    @if (!$isManager && !$isEdit)
        <div class="alert alert-info py-2">
            <i class="fa-solid fa-circle-info me-1"></i>
            This request will be sent to your reporting manager
            <strong>{{ ($employee->reporting_manager_name ?? null) ?: '(not set)' }}</strong> for approval.
        </div>
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Shift Request</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="requested_shift_id">Shift Needed</label>
                <select name="requested_shift_id" id="requested_shift_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Shift</option>
                    @foreach ($shiftList as $shift)
                        @php $enc = encryptId($shift->id); @endphp
                        <option value="{{ $enc }}" data-raw="{{ $shift->id }}"
                            @selected($selectedShift && decryptId($selectedShift) == $shift->id)
                            @disabled($currentShiftId && $currentShiftId == $shift->id)>
                            {{ $shift->shift_name }}
                            ({{ date('h:i A', strtotime($shift->start_time)) }} - {{ date('h:i A', strtotime($shift->end_time)) }})
                        </option>
                    @endforeach
                </select>
                @error('requested_shift_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-8 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="reason">Reason</label>
                <textarea name="reason" id="reason" rows="2" maxlength="500"
                    class="form-control @error('reason') is-invalid @enderror"
                    placeholder="Reason for the shift change">{{ old('reason', $isEdit ? $scr->reason : '') }}</textarea>
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
                var fields = ['emp_id', 'emp_name', 'designation', 'company_name', 'location_name', 'shift_name', 'reporting_manager'];

                function resetDetails() {
                    fields.forEach(function(f) { $('#detail_' + f).text('-'); });
                    $('#requested_shift_id option').prop('disabled', false);
                    $('#employee-pending-warning').remove();
                }

                function loadEmployee(id) {
                    resetDetails();
                    if (!id) {
                        return;
                    }
                    $.ajax({
                        url: "{{ admin_url('shift-management/shift-change-request/employee-details') }}",
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: { employee_id: id },
                        success: function(res) {
                            var d = res.data;
                            fields.forEach(function(f) { $('#detail_' + f).text(d[f]); });
                            // The current shift cannot be requested again.
                            $('#requested_shift_id option').each(function() {
                                $(this).prop('disabled', d.shift_id !== '' && $(this).val() === d.shift_id);
                            });
                            if ($('#requested_shift_id option:selected').prop('disabled')) {
                                $('#requested_shift_id').val('');
                            }
                            $('#requested_shift_id').trigger('change.select2');
                            if (d.has_pending) {
                                $('#employee_id').closest('.form-group').append(
                                    '<span id="employee-pending-warning" class="invalid-feedback d-block">A request is already pending for this employee</span>');
                            }
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
