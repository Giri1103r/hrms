{{--
    Read-only employee block shared by the Add, Edit and View pages.
    $employee may be null on the manager's Add page until an employee is picked;
    each value has an id so the page script can fill it in.
--}}
@php
    $shiftLabel = function ($emp) {
        if (!$emp || !$emp->shift_name) {
            return '-';
        }
        return $emp->shift_name . ($emp->start_time
            ? ' (' . date('h:i A', strtotime($emp->start_time)) . ' - ' . date('h:i A', strtotime($emp->end_time)) . ')'
            : '');
    };
@endphp
<div class="row">
    @foreach ([
        'emp_id' => ['Employee ID', $employee->emp_id ?? '-'],
        'emp_name' => ['Employee Name', $employee->emp_name ?? '-'],
        'designation' => ['Designation', ($employee->designation ?? null) ?: '-'],
        'company_name' => ['Company', ($employee->company_name ?? null) ?: '-'],
        'location_name' => ['Location', ($employee->location_name ?? null) ?: '-'],
        'shift_name' => [$currentShiftLabel ?? 'Current Shift', $currentShiftText ?? $shiftLabel($employee)],
        'reporting_manager' => ['Reporting To', ($employee->reporting_manager_name ?? null) ?: '-'],
    ] as $key => [$label, $text])
        <div class="mb-3 col-md-4 form-input">
            <label class="form-label view_label">{{ $label }}</label>
            <div class="view_data" id="detail_{{ $key }}">{{ $text }}</div>
        </div>
    @endforeach
</div>
