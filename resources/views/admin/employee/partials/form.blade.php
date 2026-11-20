{{--
    Employee form shared by the Add and Edit pages, so both always show the same
    fields, layout and validation. $employee is null on Add and the record on Edit;
    $documents holds the employee's saved documents (empty on Add).
--}}
@php
    $isEdit = (bool) $employee;
    $formId = $isEdit ? 'employeeedit' : 'employeeadd';
    $value = fn ($field, $default = null) => old($field, $isEdit ? $employee->{$field} : $default);
    $encrypted = fn ($field) => old($field, $isEdit && $employee->{$field} ? encryptId($employee->{$field}) : '');
    $selectedRoles = old('user_role', $isEdit ? array_filter(explode(',', (string) $employee->user_role)) : [ROLE_USER]);
    $removedDocuments = old('remove_documents', []);
@endphp

<form method="POST" id="{{ $formId }}" enctype="multipart/form-data"
    action="{{ admin_url($isEdit ? 'employee-management/employee/edit/submit' : 'employee-management/employee/add/submit') }}">
    @csrf
    @if ($isEdit)
        <input type="hidden" name="id" id="id" value="{{ encryptId($employee->id) }}">
    @endif

    {{-- Basic details --}}
    <div class="card-header-inner px-3 pb-2">
        <h4 class="text-white">Basic Details</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="emp_id">Employee ID</label>
                @if ($isEdit)
                    <input type="text" id="emp_id" class="form-control" value="{{ $employee->emp_id }}" readonly
                        title="The Employee ID is the login username and cannot be changed">
                @else
                    <input type="text" name="emp_id" id="emp_id" class="form-control @error('emp_id') is-invalid @enderror"
                        placeholder="Employee ID" value="{{ old('emp_id') }}" maxlength="50">
                @endif
                @error('emp_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="emp_name">Employee Name</label>
                <input type="text" name="emp_name" id="emp_name" class="form-control @error('emp_name') is-invalid @enderror"
                    placeholder="Employee Name" value="{{ $value('emp_name') }}" maxlength="100">
                @error('emp_name') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="gender">Gender</label>
                <select name="gender" id="gender" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Gender</option>
                    @foreach (['Male', 'Female', 'Other'] as $gender)
                        <option value="{{ $gender }}" @selected($value('gender') == $gender)>{{ $gender }}</option>
                    @endforeach
                </select>
                @error('gender') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="nationality">Nationality</label>
                <input type="text" name="nationality" id="nationality" class="form-control"
                    placeholder="Nationality" value="{{ $value('nationality', 'Indian') }}" maxlength="50">
                @error('nationality') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="blood_group">Blood Group</label>
                <select name="blood_group" id="blood_group" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Blood Group</option>
                    @foreach ($bloodgroup as $list)
                        <option value="{{ $list->id }}" @selected($value('blood_group') == $list->id)>{{ $list->blood_group_name }}</option>
                    @endforeach
                </select>
                @error('blood_group') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="id_type">ID Type</label>
                <select name="id_type" id="id_type" class="form-control single-select" style="width: 100%;">
                    <option value="">Select ID Type</option>
                    @foreach (array_keys(\App\Rules\IdNumber::TYPES) as $idType)
                        <option value="{{ $idType }}" @selected($value('id_type') == $idType)>{{ $idType }}</option>
                    @endforeach
                </select>
                @error('id_type') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="id_number">ID Number</label>
                <input type="text" name="id_number" id="id_number" class="form-control @error('id_number') is-invalid @enderror"
                    placeholder="Select an ID Type first" value="{{ $value('id_number') }}" maxlength="20" autocomplete="off">
                <small class="text-muted d-block mt-1" id="id_number_hint"></small>
                @error('id_number') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Contact --}}
    <div class="card-header-inner px-3 pb-2 mt-2">
        <h4 class="text-white">Contact</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                    placeholder="Employee Email" value="{{ $value('email') }}" maxlength="50">
                @error('email') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="mobile_no">Mobile No</label>
                <input type="text" name="mobile_no" id="mobile_no" class="form-control @error('mobile_no') is-invalid @enderror"
                    placeholder="Mobile Number" value="{{ $value('mobile_no') }}" maxlength="15" inputmode="numeric">
                @error('mobile_no') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Organisation --}}
    <div class="card-header-inner px-3 pb-2 mt-2">
        <h4 class="text-white">Organisation</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="company_id">Company</label>
                <select name="company" id="company_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Company</option>
                    @foreach ($companyList as $list)
                        <option value="{{ encryptId($list->id) }}" @selected($encrypted('company') == encryptId($list->id))>{{ $list->company_name }}</option>
                    @endforeach
                </select>
                @error('company_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="location_id">Location</label>
                <select name="location" id="location_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Location</option>
                </select>
                @error('location_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="department_id">Department</label>
                <select name="department" id="department_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Department</option>
                </select>
                @error('department_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="designation">Designation</label>
                <select name="designation" id="designation" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Designation</option>
                    @foreach ($designationList as $list)
                        <option value="{{ $list->designation_name }}" @selected($value('designation') == $list->designation_name)>{{ $list->designation_name }}</option>
                    @endforeach
                </select>
                @error('designation') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label" for="reporting_manager">Reporting Manager</label>
                <select name="reporting_manager" id="reporting_manager" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Reporting Manager</option>
                    @foreach ($managerList as $manager)
                        <option value="{{ $manager->emp_id }}" @selected($value('reporting_manager') == $manager->emp_id)>{{ $manager->emp_name }} ({{ $manager->emp_id }})</option>
                    @endforeach
                </select>
                @error('reporting_manager') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Employment --}}
    <div class="card-header-inner px-3 pb-2 mt-2">
        <h4 class="text-white">Employment</h4>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="employee_type">Employee Type</label>
                <select name="employee_type" id="employee_type" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Employee Type</option>
                    @foreach ($employeeTypeList as $list)
                        <option value="{{ $list->employee_type_name }}" @selected($value('employee_type') == $list->employee_type_name)>{{ $list->employee_type_name }}</option>
                    @endforeach
                </select>
                @error('employee_type') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="employee_status">Employee Status</label>
                <select name="employee_status" id="employee_status" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Employee Status</option>
                    @foreach ($employeeStatusList as $list)
                        <option value="{{ $list->employee_status_name }}" @selected($value('employee_status') == $list->employee_status_name)>{{ $list->employee_status_name }}</option>
                    @endforeach
                </select>
                @error('employee_status') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="shift_id">Shift</label>
                <select name="shift" id="shift_id" class="form-control single-select" style="width: 100%;">
                    <option value="">Select Shift</option>
                    @foreach ($shiftList as $shift)
                        <option value="{{ encryptId($shift->id) }}" @selected($encrypted('shift') == encryptId($shift->id))>
                            {{ $shift->shift_name }}@if ($shift->start_time && $shift->end_time) ({{ date('h:i A', strtotime($shift->start_time)) }} - {{ date('h:i A', strtotime($shift->end_time)) }})@endif
                        </option>
                    @endforeach
                </select>
                @error('shift_id') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="joining_date">Joining Date</label>
                <input type="text" name="joining_date" id="joining_date" class="form-control @error('joining_date') is-invalid @enderror"
                    placeholder="DD-MM-YYYY" value="{{ old('joining_date', $isEdit ? Displaydateformat($employee->joining_date) : '') }}" autocomplete="off">
                @error('joining_date') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="form-group form-input">
                <label class="form-label require" for="user_role">User Role</label>
                <select name="user_role[]" id="user_role" multiple class="select2 form-control" style="width: 100%;">
                    @foreach ($userrole as $role)
                        <option value="{{ $role->id }}" @selected(in_array($role->id, $selectedRoles))>{{ $role->role_name }}</option>
                    @endforeach
                </select>
                @error('user_role') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    {{-- Documents --}}
    <div class="card-header-inner px-3 pb-2 mt-2 d-flex justify-content-between align-items-center">
        <h4 class="text-white mb-0">Documents</h4>
        <button type="button" class="btn btn-sm btn-primary" id="addDocumentRow">
            <i class="fa-solid fa-plus me-1"></i> Add more
        </button>
    </div>
    @error('documents') <span class="invalid-feedback d-block mb-2">{{ $message }}</span> @enderror

    @if ($documents->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0" id="savedDocuments">
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>File</th>
                        <th>Uploaded</th>
                        <th class="text-center" style="width: 110px;">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($documents as $document)
                        @php $docKey = encryptId($document->id); @endphp
                        <tr class="{{ in_array($docKey, $removedDocuments) ? 'text-decoration-line-through text-muted' : '' }}">
                            <td>{{ $document->documentType->document_name ?? '-' }}</td>
                            <td>
                                <a href="{{ url($document->file_path) }}" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-file me-1"></i>{{ $document->file_orgname }}
                                </a>
                            </td>
                            <td>{{ Displaydateformat($document->created_at) }}</td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input remove-saved-document" name="remove_documents[]"
                                    value="{{ $docKey }}" @checked(in_array($docKey, $removedDocuments)) title="Remove this file when saving">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div id="documentRows">
        @foreach (old('documents', []) as $index => $row)
            @include('admin.employee.partials.document-row', ['index' => $index, 'selected' => $row['document_type_id'] ?? ''])
        @endforeach
    </div>
    <p class="text-muted small" id="documentHint">
        Optional. Add one row per document type; each type can be used once per save. PDF, JPG, PNG,
        DOC or DOCX, up to 5 MB per file. You can choose several files in one row.
        @if ($isEdit && $documents->isNotEmpty())
            Tick <strong>Remove</strong> on a saved file to delete it when you save.
        @endif
        @if (old('documents'))
            <strong class="text-danger d-block">Please choose the files again; browsers do not keep selected files after a failed save.</strong>
        @endif
    </p>
    <template id="documentRowTemplate">
        @include('admin.employee.partials.document-row', ['index' => '__INDEX__', 'selected' => ''])
    </template>

    @unless ($isEdit)
        <p class="text-muted small mb-0">
            A login is created with the Employee ID as username. The first password is
            <strong>User@</strong> followed by the Employee ID.
        </p>
    @endunless

    <hr>
    <div class="submit-button" style="text-align: right;">
        <x-button-submit class="submit"></x-button-submit>
        <x-button-reset class="submit"></x-button-reset>
        <x-button-cancel href="{{ admin_url('employee-management/employee/list') }}"></x-button-cancel>
    </div>
</form>

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(document).ready(function() {
            var formSelector = '#{{ $formId }}';

            $('#user_role').select2({
                placeholder: "Select Role",
                allowClear: true,
                closeOnSelect: false,
            });

            /* Joining Date must be in the past: yesterday is the latest allowed day. */
            flatpickr("#joining_date", {
                dateFormat: "d-m-Y",
                maxDate: new Date().fp_incr(-1),
                allowInput: true,
            });

            jQuery.validator.addMethod('pastDate', function(value, element) {
                if (this.optional(element)) return true;
                var parts = value.split('-');
                if (parts.length !== 3) return false;
                var date = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                return !isNaN(date.getTime()) && date < today;
            }, 'Joining Date must be a past date (before today)');

            $('#resetform').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });

            /* Company > Location > Department, preselecting the saved (Edit) or old() (failed submit) values. */
            var selectedLocation = @json($encrypted('location') ?: '0');
            var selectedDepartment = @json($encrypted('department') ?: '0');

            function fillSelect($select, placeholder, data, preselected) {
                $select.empty().append($('<option>', { value: '', text: placeholder }));
                $.each(data, function(key, value) {
                    $select.append($('<option>', { value: value.id, text: value.name, selected: value.id == preselected }));
                });
            }

            function load(url, $select, placeholder, preselected, next) {
                $.getJSON(url, function(data) {
                    fillSelect($select, placeholder, data, preselected);
                    if (next) next();
                });
            }

            function loadLocations(preselected, next) {
                var company = $('#company_id').val();
                fillSelect($('#location_id'), 'Select Location', [], '');
                fillSelect($('#department_id'), 'Select Department', [], '');
                if (company) load("{{ admin_url('location/ajax-list/') }}" + company + '/' + preselected, $('#location_id'), 'Select Location', preselected, next);
            }

            function loadDepartments(preselected) {
                var location = $('#location_id').val();
                fillSelect($('#department_id'), 'Select Department', [], '');
                if (location) load("{{ admin_url('employee-management/employee/department-list/') }}" + location + '/' + preselected, $('#department_id'), 'Select Department', preselected);
            }

            $('#company_id').on('change', function() { loadLocations('0'); });
            $('#location_id').on('change', function() { loadDepartments('0'); });

            if ($('#company_id').val()) {
                loadLocations(selectedLocation, function() {
                    loadDepartments(selectedDepartment);
                });
            }

            /* ---------- Documents: add / remove rows, one row per document type ---------- */
            var documentTypeCount = {{ $documentTypeList->count() }};
            var nextDocumentIndex = {{ count(old('documents', [])) }};

            function initDocumentSelect($select) {
                $select.select2({
                    width: '100%',
                    placeholder: 'Select Document Type',
                });
            }

            function syncDocumentTypes() {
                var chosen = $('#documentRows .document-type').map(function() { return $(this).val(); }).get().filter(Boolean);
                $('#documentRows .document-type').each(function() {
                    var own = $(this).val();
                    $(this).find('option').each(function() {
                        var value = $(this).val();
                        $(this).prop('disabled', value !== '' && value !== own && chosen.indexOf(value) !== -1);
                    });
                });
                var rows = $('#documentRows .document-row').length;
                $('#addDocumentRow').prop('disabled', rows >= documentTypeCount)
                    .attr('title', rows >= documentTypeCount ? 'Every document type already has a row' : '');
            }

            $('#addDocumentRow').on('click', function() {
                var $row = $($('#documentRowTemplate').html().replace(/__INDEX__/g, nextDocumentIndex++));
                $('#documentRows').append($row);
                initDocumentSelect($row.find('.document-type'));
                syncDocumentTypes();
            });

            $('#documentRows').on('click', '.remove-document-row', function() {
                var $row = $(this).closest('.document-row');
                $row.find('.document-type').select2('destroy');
                $row.remove();
                syncDocumentTypes();
            });

            $('#documentRows').on('change', '.document-type', syncDocumentTypes);

            $('#savedDocuments').on('change', '.remove-saved-document', function() {
                $(this).closest('tr').toggleClass('text-decoration-line-through text-muted', this.checked);
            });

            $(formSelector).on('submit', function() {
                /* Drop rows left completely empty so they are not validated. */
                $('#documentRows .document-row').each(function() {
                    var hasType = $(this).find('.document-type').val() !== '';
                    var hasFiles = $(this).find('.document-files')[0].files.length > 0;
                    if (!hasType && !hasFiles) {
                        $(this).find('select, input').prop('disabled', true);
                    }
                });
            });

            $('#documentRows .document-type').each(function() {
                initDocumentSelect($(this));
            });
            syncDocumentTypes();

            /* ---------- ID Number: format depends on the selected ID Type ---------- */
            var idFormats = @json(collect(\App\Rules\IdNumber::TYPES)->map(fn ($t) => ['pattern' => trim($t[0], '/'), 'format' => $t[1]]));

            function normalizeIdNumber(value) {
                return (value || '').replace(/[\s\-]+/g, '').toUpperCase();
            }

            function verhoeffValid(num) {
                var d = [[0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],[3,4,0,1,2,8,9,5,6,7],[4,0,1,2,3,9,5,6,7,8],
                         [5,9,8,7,6,0,4,3,2,1],[6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],[8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0]];
                var p = [[0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],[8,9,1,6,0,4,3,5,2,7],
                         [9,4,5,3,1,2,6,8,7,0],[4,2,8,6,5,7,3,9,0,1],[2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8]];
                var c = 0, digits = num.split('').reverse();
                for (var i = 0; i < digits.length; i++) c = d[c][p[i % 8][parseInt(digits[i], 10)]];
                return c === 0;
            }

            function updateIdNumberHint() {
                var type = $('#id_type').val();
                var info = idFormats[type];
                $('#id_number').attr('placeholder', info ? type + ' Number' : 'Select an ID Type first');
                $('#id_number_hint').text(info ? 'Format: ' + info.format : '');
            }

            jQuery.validator.addMethod('idNumberByType', function(value, element) {
                var type = $('#id_type').val();
                var info = idFormats[type];
                if (this.optional(element) || !info) return true;
                var number = normalizeIdNumber(value);
                if (!new RegExp(info.pattern).test(number)) return false;
                return type !== 'Aadhar' || verhoeffValid(number);
            }, function(params, element) {
                var type = $('#id_type').val();
                var info = idFormats[type];
                var number = normalizeIdNumber($(element).val());
                if (type === 'Aadhar' && info && new RegExp(info.pattern).test(number)) {
                    return 'This Aadhar number is not valid (check digit does not match).';
                }
                return info ? type + ' number must be ' + info.format + '.' : 'Please check the ID Number.';
            });

            $('#id_type').on('change', function() {
                updateIdNumberHint();
                if ($('#id_number').val()) $('#id_number').valid();
            });

            $('#id_number').on('input', function() {
                var upper = this.value.toUpperCase();
                if (upper !== this.value) this.value = upper;
            });

            updateIdNumberHint();

            jQuery.validator.addMethod("strictEmail", function(value, element) {
                return this.optional(element) || /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value);
            }, "Please enter a valid email address");

            jQuery.validator.addMethod("pattern", function(value, element, regexpr) {
                return this.optional(element) || regexpr.test(value);
            }, "Please check your input.");

            $(formSelector).validate({
                ignore: [],
                rules: {
                    emp_id: {
                        required: true,
                        maxlength: 50,
                        pattern: /^[A-Za-z0-9_\-\/]+$/,
                    },
                    emp_name: { required: true, maxlength: 100 },
                    gender: { required: true },
                    email: {
                        required: true,
                        strictEmail: true,
                        maxlength: 50,
                        remote: {
                            url: "{{ admin_url('employee-management/employee/unique') }}",
                            type: "post",
                            data: {
                                _token: "{{ csrf_token() }}",
                                email: function() { return $('#email').val(); },
                                id: @json($isEdit ? encryptId($employee->id) : ''),
                            }
                        }
                    },
                    mobile_no: { required: true, pattern: /^[0-9]{10,15}$/ },
                    company: { required: true },
                    location: { required: true },
                    shift: { required: true },
                    designation: { required: true },
                    employee_type: { required: true },
                    employee_status: { required: true },
                    joining_date: { required: true, pastDate: true },
                    'user_role[]': { required: true },
                    id_type: {
                        required: function() { return $('#id_number').val() !== ''; },
                    },
                    id_number: {
                        required: function() { return $('#id_type').val() !== ''; },
                        maxlength: 20,
                        idNumberByType: true,
                    },
                },
                messages: {
                    emp_id: {
                        required: "Please enter the Employee ID",
                        pattern: "Use only letters, numbers, -, _ and /",
                    },
                    emp_name: { required: "Please enter the Employee Name" },
                    gender: { required: "Please select the Gender" },
                    email: {
                        required: "Please enter the Email",
                        remote: "This email is already used by another employee",
                    },
                    mobile_no: {
                        required: "Please enter the Mobile No",
                        pattern: "Mobile No must be 10 to 15 digits",
                    },
                    company: { required: "Please select the Company" },
                    location: { required: "Please select the Location" },
                    shift: { required: "Please select the Shift" },
                    designation: { required: "Please select the Designation" },
                    employee_type: { required: "Please select the Employee Type" },
                    employee_status: { required: "Please select the Employee Status" },
                    joining_date: { required: "Please select the Joining Date" },
                    'user_role[]': { required: "Please select at least one User Role" },
                    id_type: { required: "Please select the ID Type for this ID Number" },
                    id_number: { required: "Please enter the ID Number" },
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-input').append(error);
                },
                highlight: function(element) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element) {
                    $(element).removeClass('is-invalid');
                },
            });
        });
    </script>
@endpush
