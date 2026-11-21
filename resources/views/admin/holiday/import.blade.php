@extends('admin.layouts.admin')
@section('title', 'Holiday Import')
@section('pageurl', admin_url('holiday-management/holiday/list'))

@section('content')
    <div class="clearfix"></div>

    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-end p-2 gap-2">
                                <x-button-download href="{{ admin_url('holiday-management/holiday/sampledownload') }}"></x-button-download>
                                <x-button-back href="{{ admin_url('holiday-management/holiday/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            @if (session('import_result'))
                                @php $result = session('import_result'); @endphp
                                <div class="alert {{ $result['imported'] ? 'alert-warning' : 'alert-danger' }}">
                                    <strong>{{ $result['imported'] }} record(s) imported,
                                        {{ count($result['errors']) }} line(s) skipped.</strong>
                                    Fix the lines below and upload them again.
                                </div>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 120px">Line No</th>
                                                <th>Problem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($result['errors'] as $error)
                                                <tr>
                                                    <td>{{ $error['line_no'] ?: '-' }}</td>
                                                    <td>{{ $error['error'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div class="basic-form">
                                <form method="POST" id="holidayimport" enctype="multipart/form-data"
                                    action="{{ admin_url('holiday-management/holiday/import/submit') }}">
                                    @csrf
                                    <div class="row">
                                        <div class="mb-3 col-md-6 form-input">
                                            <label class="form-label required" for="holiday_upload">{{ __('common.file') }}</label>
                                            <input type="file" name="holiday_upload" id="holiday_upload" class="form-control" accept=".xlsx">
                                            @error('holiday_upload') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="card-header-inner px-3 pb-2">
                                        <h4 class="text-white">How to fill the file</h4>
                                    </div>
                                    <ul class="mb-0">
                                        <li>Download the sample file and keep its first row (<strong>SNo, Day, Month, Year</strong>) unchanged.</li>
                                        <li><strong>Day:</strong> the day of the month the holiday falls on, 1 to 31 (it must exist in that month, so 30 February is rejected).</li>
                                        <li><strong>Month:</strong> a number from 1 to 12, or the month name (January or Jan).</li>
                                        <li><strong>Year:</strong> four digits, for example 2026.</li>
                                        <li>A date that already exists, or appears twice in the file, is skipped.</li>
                                        <li>Rows with problems are skipped and listed here with their line number;
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
                location.href = "{{ admin_url('holiday-management/holiday/import') }}";
            });

            $('#holidayimport').validate({
                rules: {
                    holiday_upload: {
                        required: true,
                        extension: "xlsx",
                        // accept=".xlsx" on the input only filters the file picker; stop
                        // jQuery Validate treating it as a MIME-type rule (it never matches).
                        accept: false,
                        filesize: 5242880,
                    },
                },
                messages: {
                    holiday_upload: {
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
