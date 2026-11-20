@extends('admin.layouts.admin')
@section('title', 'Employee Import')
@section('pageurl', admin_url('employee-management/employee/list'))

@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-end p-2 gap-2">
                                <x-button-download href="{{ admin_url('employee-management/employee/sampledownload') }}"></x-button-download>
                                <x-button-back href="{{ admin_url('employee-management/employee/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="basic-form">
                                <form method="POST" id="employeeimport" enctype="multipart/form-data"
                                    action="{{ admin_url('employee-management/employee/import/submit') }}">
                                    @csrf
                                    <div class="row">
                                        <div class="mb-3 col-md-6 form-input">
                                            <label class="form-label required" for="employee_upload">{{ __('common.file') }}</label>
                                            <input type="file" name="employee_upload" id="employee_upload" class="form-control" accept=".xlsx">
                                            @error('employee_upload') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="card-header-inner px-3 pb-2">
                                        <h4 class="text-white">How to fill the file</h4>
                                    </div>
                                    <ul class="mb-0">
                                        <li>Download the sample file and keep its first row (the column headings) unchanged.</li>
                                        <li><strong>Required:</strong> Employee ID, Employee Name, Gender (Male / Female / Other), Email,
                                            Mobile No (10 to 15 digits), Joining Date (DD-MM-YYYY, a past date), Company Name, Location Name, Shift Name,
                                            Designation, Employee Type, Employee Status.</li>
                                        <li>Company, Location, Shift, Department, Designation, Employee Type, Employee Status and Blood
                                            Group must match the names in their masters exactly.</li>
                                        <li>ID Type, if given, must be Aadhar, PAN, Passport, Driving Licence or Voter ID, with an ID Number.</li>
                                        <li>Reporting Manager ID must be an existing Employee ID (or one earlier in the same file).</li>
                                        <li>User Roles: role names separated by commas. Leave empty for the "User" role.</li>
                                        <li>Each employee gets a login: username is the Employee ID, first password is
                                            <strong>User@</strong> followed by the Employee ID.</li>
                                        <li>Rows with problems are skipped and listed with their line number in the Upload Log;
                                            the other rows are still imported.</li>
                                    </ul>

                                    <hr>
                                    <div class="submit-button" style="text-align: right;">
                                        <x-button-submit></x-button-submit>
                                        <x-button-reset class="submit"></x-button-reset>
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
        $(document).ready(function() {
            $('#resetform').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });

            $('#employeeimport').validate({
                rules: {
                    employee_upload: {
                        required: true,
                        extension: "xlsx",
                        filesize: 5242880,
                    },
                },
                messages: {
                    employee_upload: {
                        required: "Please upload a file",
                        extension: "Please upload an Excel file (.xlsx)",
                    },
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
