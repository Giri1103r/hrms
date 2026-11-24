{{--
    Apply Leave form shared by Add and Edit.
    $leave is null on Add. On Add a Manager/Admin picks the employee; an employee
    applying for themselves gets their own details pre-filled (no dropdown).
    The "Leave Summary" box is filled by the calculate endpoint as fields change.
--}}
@php
    use App\Models\Leave\LeaveRequest;

    $isEdit = isset($leave) && $leave;
    $forOthers = $forOthers ?? false;
    $selectedType = old('leave_type_id', $isEdit ? encryptId($leave->leave_type_id) : '');
    $duration = (int) old('duration_type', $isEdit ? $leave->duration_type : LeaveRequest::ONE_DAY);
    $session = (int) old('half_day_session', $isEdit ? $leave->half_day_session : 1);
    $fromDate = old('from_date', $isEdit ? $leave->from_date->format('d-m-Y') : '');
    $toDate = old('to_date', $isEdit && $leave->duration_type == LeaveRequest::MULTI_DAY ? $leave->to_date->format('d-m-Y') : '');
@endphp

<form method="POST" id="{{ $isEdit ? 'leaveedit' : 'leaveadd' }}"
    action="{{ admin_url($isEdit ? 'leave-management/leave/edit/submit' : 'leave-management/leave/add/submit') }}">
    @csrf
    @if ($isEdit)
        <input type="hidden" name="id" value="{{ encryptId($leave->id) }}">
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Employee Details</h4>
    </div>

    @if (!$isEdit && $forOthers)
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
                <div class="alert alert-info py-2 mb-0 w-100" id="approval-note">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Leave you apply for an employee who reports to you is confirmed directly.
                    Otherwise it goes to that employee's reporting manager.
                </div>
            </div>
        </div>
    @else
        @error('employee_id') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
        @error('employee_ref') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
    @endif

    <div class="row">
        @foreach ([
            'emp_id' => ['Employee ID', $employee->emp_id ?? '-'],
            'emp_name' => ['Employee Name', $employee->emp_name ?? '-'],
            'designation' => ['Designation', ($employee->designation ?? null) ?: '-'],
            'company_name' => ['Company', ($employee->company_name ?? null) ?: '-'],
            'location_name' => ['Location', ($employee->location_name ?? null) ?: '-'],
            'reporting_manager' => ['Reporting To', ($employee->reporting_manager_name ?? null) ?: '-'],
        ] as $key => [$label, $text])
            <div class="mb-3 col-md-4 form-input">
                <label class="form-label view_label">{{ $label }}</label>
                <div class="view_data" id="detail_{{ $key }}">{{ $text }}</div>
            </div>
        @endforeach
    </div>

    @if (!$forOthers && !$isEdit)
        <div class="alert alert-info py-2">
            <i class="fa-solid fa-circle-info me-1"></i>
            This leave will be sent to your reporting manager
            <strong>{{ ($employee->reporting_manager_name ?? null) ?: '(not set)' }}</strong> for approval.
        </div>
    @endif

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Leave Details</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="leave_type_id">Leave Type</label>
                <select name="leave_type_id" id="leave_type_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Leave Type</option>
                    @foreach ($leaveTypeList as $type)
                        <option value="{{ encryptId($type->id) }}" @selected($selectedType && decryptId($selectedType) == $type->id)>
                            {{ $type->leave_type_name }}
                        </option>
                    @endforeach
                </select>
                @error('leave_type_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="duration_type">Leave Duration</label>
                <select name="duration_type" id="duration_type" class="form-control single-select" style="width: 100%;">
                    @foreach (LeaveRequest::DURATION_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($duration === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('duration_type') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3" id="session-col">
            <div class="form-group form-input">
                <label class="form-label require" for="half_day_session">Session</label>
                <select name="half_day_session" id="half_day_session" class="form-control single-select" style="width: 100%;">
                    @foreach (LeaveRequest::SESSION_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($session === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('half_day_session') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="from_date" id="from_date_label">Leave Date</label>
                <input type="text" name="from_date" id="from_date" class="form-control @error('from_date') is-invalid @enderror"
                    placeholder="DD-MM-YYYY" value="{{ $fromDate }}" autocomplete="off">
                @error('from_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3" id="to-date-col">
            <div class="form-group form-input">
                <label class="form-label require" for="to_date">To Date</label>
                <input type="text" name="to_date" id="to_date" class="form-control @error('to_date') is-invalid @enderror"
                    placeholder="DD-MM-YYYY" value="{{ $toDate }}" autocomplete="off">
                @error('to_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-8 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="reason">Reason</label>
                <textarea name="reason" id="reason" rows="2" maxlength="500"
                    class="form-control @error('reason') is-invalid @enderror"
                    placeholder="Reason for leave">{{ old('reason', $isEdit ? $leave->reason : '') }}</textarea>
                @error('reason') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Leave Summary</h4>
    </div>
    <div id="leave-summary" class="mb-3">
        <p class="text-muted mb-0" id="summary-placeholder">Select the leave type and dates to see the balance and day count.</p>
        <div class="row d-none" id="summary-body">
            @foreach ([
                'credited' => 'Credited This Year',
                'used' => 'Used (Confirmed)',
                'pending' => 'Waiting for Approval',
                'available' => 'Available Now',
            ] as $key => $label)
                <div class="mb-3 col-md-3 form-input">
                    <label class="form-label view_label">{{ $label }}</label>
                    <div class="view_data fw-semibold" id="bal_{{ $key }}">-</div>
                </div>
            @endforeach
            <div class="col-12 small text-muted mb-2" id="bal_rule"></div>
            <div class="col-12 d-none" id="summary-leave">
                <div class="alert py-2 mb-0" id="summary-leave-box"></div>
            </div>
        </div>
        <div class="alert alert-danger py-2 mt-2 mb-0 d-none" id="summary-error"></div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-2">
        <x-button-submit></x-button-submit>
    </div>
</form>

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(function() {
            var MULTI = {{ LeaveRequest::MULTI_DAY }}, HALF = {{ LeaveRequest::HALF_DAY }};
            var detailFields = ['emp_id', 'emp_name', 'designation', 'company_name', 'location_name', 'reporting_manager'];
            var leaveId = @json($isEdit ? encryptId($leave->id) : null);

            var fromPicker = flatpickr('#from_date', { dateFormat: 'd-m-Y', allowInput: true, onChange: refreshSoon });
            var toPicker = flatpickr('#to_date', { dateFormat: 'd-m-Y', allowInput: true, onChange: refreshSoon });

            function toggleDuration() {
                var d = parseInt($('#duration_type').val(), 10);
                $('#session-col').toggleClass('d-none', d !== HALF);
                $('#to-date-col').toggleClass('d-none', d !== MULTI);
                $('#from_date_label').text(d === MULTI ? 'From Date' : 'Leave Date');
                if (d !== MULTI) {
                    toPicker.clear();
                }
            }

            var timer = null;
            function refreshSoon() {
                clearTimeout(timer);
                timer = setTimeout(refresh, 200);
            }

            function refresh() {
                var data = {
                    leave_type_id: $('#leave_type_id').val(),
                    duration_type: $('#duration_type').val(),
                    from_date: $('#from_date').val(),
                    to_date: $('#to_date').val(),
                };
                if (leaveId) {
                    data.leave_id = leaveId;
                } else if ($('#employee_id').length) {
                    data.employee_id = $('#employee_id').val();
                    if (!data.employee_id) {
                        detailFields.forEach(function(f) { $('#detail_' + f).text('-'); });
                        showSummary(null);
                        return;
                    }
                }

                $.ajax({
                    url: "{{ admin_url('leave-management/leave/calculate') }}",
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: data,
                    success: function(res) {
                        var d = res.data;
                        detailFields.forEach(function(f) { $('#detail_' + f).text(d.employee[f]); });
                        showSummary(d);
                    },
                    error: function() { showSummary(null); }
                });
            }

            function showSummary(d) {
                var hasBalance = d && d.balance;
                $('#summary-placeholder').toggleClass('d-none', !!hasBalance);
                $('#summary-body').toggleClass('d-none', !hasBalance);
                $('#summary-error').toggleClass('d-none', !(d && d.error)).text(d && d.error ? d.error : '');
                if (!hasBalance) {
                    return;
                }
                var b = d.balance;
                ['credited', 'used', 'pending', 'available'].forEach(function(k) { $('#bal_' + k).text(b[k] + ' day(s)'); });
                $('#bal_rule').text(b.leave_type_name + ': ' + b.monthly_credit + ' day(s) credited at the start of each month in ' + b.year
                    + '. Unused leave can be used until 31 Dec and resets on 1 Jan.');

                if (!d.leave) {
                    $('#summary-leave').addClass('d-none');
                    return;
                }
                var l = d.leave, text = 'This leave: <strong>' + l.total_days + ' day(s)</strong> (Sundays and holidays not counted). ';
                if (l.has_lop) {
                    text += '<strong>' + l.paid_days + '</strong> from the balance and <strong class="text-danger">'
                        + l.lop_days + ' day(s) Loss of Pay</strong>.';
                } else {
                    text += 'All ' + l.paid_days + ' day(s) come from the balance.';
                }
                $('#summary-leave-box').removeClass('alert-info alert-warning')
                    .addClass(l.has_lop ? 'alert-warning' : 'alert-info').html(text);
                $('#summary-leave').removeClass('d-none');
            }

            $('#duration_type').on('change', function() { toggleDuration(); refreshSoon(); });
            $('#leave_type_id, #employee_id').on('change', refreshSoon);
            $('#from_date, #to_date').on('blur', refreshSoon);

            toggleDuration();
            refresh();
        });
    </script>
@endpush
