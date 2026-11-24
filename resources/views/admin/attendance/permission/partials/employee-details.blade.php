{{--
    Read-only employee block shared by the Add, Edit and View pages.
    $employee may be null on the manager's Add page until an employee is picked;
    each value has an id so the page script can fill it in.
--}}
<div class="row">
    @foreach ([
        'emp_id' => [__('attendance.employee_id'), $employee->emp_id ?? '-'],
        'emp_name' => [__('attendance.employee_name'), $employee->emp_name ?? '-'],
        'designation' => [__('attendance.designation'), ($employee->designation ?? null) ?: '-'],
        'company_name' => [__('common.company'), ($employee->company_name ?? null) ?: '-'],
        'location_name' => [__('common.location'), ($employee->location_name ?? null) ?: '-'],
        'reporting_manager' => [__('attendance.reporting_to'), ($employee->reporting_manager_name ?? null) ?: '-'],
    ] as $key => [$label, $text])
        <div class="mb-3 col-md-4 form-input">
            <label class="form-label view_label">{{ $label }}</label>
            <div class="view_data" id="detail_{{ $key }}">{{ $text }}</div>
        </div>
    @endforeach
</div>
