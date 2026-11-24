@extends('admin.layouts.admin')
@section('title', 'Leave Type Master Add')
@section('pageurl', admin_url('leave-management/master/leave-type/list'))


@section('content')
    <div class="clearfix"></div>
    <div class="page-titles">
        <div class="d-flex align-items-center">

        </div>

    </div>

    <div class="content-body  default-height">
        <div class="container-fluid main-content">
            <!-- row -->
            <div class="row">

                <div class="col-12">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title"></h4>
                                <div class="align-back-btc d-flex justify-content-end align-items-center">
                                    <x-button-back href="{{ admin_url('leave-management/master/leave-type/list') }}"></x-button-back>
                                </div>
                            </div>

                            <div class="card-body">

                                <div class="basic-form">
                                    <form method="POST" id="leavetypeadd"
                                        action="{{ admin_url('leave-management/master/leave-type/add/submit') }}">
                                        @csrf

                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <div class="form-group form-input">
                                                    <label class="form-label require">Leave Type ID</label>
                                                    <input type="text" name ="lt_code" class="form-control"
                                                        placeholder="Leave Type ID"
                                                        value="{{ getsequence('leavetype') }}" readonly>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-2">
                                                <div class="form-group form-input">
                                                    <label class="form-label require">Leave Type Name</label>
                                                    <input type="text" name="leave_type_name" id="leave_type_name"
                                                        class="form-control" placeholder="Leave Type Name"
                                                        value="{{ old('leave_type_name') }}">
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-2">
                                                <div class="form-group form-input">
                                                    <label class="form-label require" for="monthly_credit">Monthly Credit (days)</label>
                                                    <input type="number" name="monthly_credit" id="monthly_credit"
                                                        class="form-control @error('monthly_credit') is-invalid @enderror"
                                                        placeholder="Monthly Credit" min="0" max="31" step="0.5"
                                                        value="{{ old('monthly_credit', 1) }}">
                                                    <small class="text-muted">Credited at the start of every month. Unused leave is usable until 31 Dec and resets on 1 Jan.</small>
                                                    @error('monthly_credit') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                                </div>
                                            </div>

                                        </div>
                                        <hr>
                                        <div class="submit-button" style="text-align: right;">

                                            <x-button-submit class="submit"></x-button-submit>
                                            <x-button-reset class="submit"></x-button-reset>
                                            <x-button-cancel
                                                href="{{ admin_url('leave-management/master/leave-type/list') }}"></x-button-cancel>
                                        </div>

                                    </form>
                                </div>

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
        });

        $(function() {
            $('#leavetypeadd').validate({
                rules: {
                    monthly_credit: {
                        required: true,
                        number: true,
                        min: 0,
                        max: 31,
                        step: 0.5,
                    },
                    leave_type_name: {
                        required: true,
                        maxlength: 70,
                        remote: {
                            url: '{{ admin_url('leave-management/master/leave-type/unique') }}',
                            type: 'post',
                            data: {
                                leave_type_name: function() {
                                    return $('#leave_type_name').val();
                                },
                            }
                        }
                    },

                },
                messages: {
                    monthly_credit: {
                        required: "Monthly Credit is Required",
                        number: "Monthly Credit must be a number",
                        min: "Monthly Credit cannot be negative",
                        max: "Monthly Credit cannot be more than 31 days",
                        step: "Monthly Credit must be in steps of 0.5 days",
                    },
                    leave_type_name: {
                        required: "{{ __('Leave Type Name is Required') }}",
                        maxlength: "Maximum Characters should not exceed 70",
                        remote: "{{ __('Leave Type Name should be unique') }}",
                    },
                },
                errorElement: 'span',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-input').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid');
                },
                submitHandler: function(form) {
                    form.submit();
                },
                invalidHandler: function(event, validator) {
                    var errors = validator.numberOfInvalids();
                    validator.errorList.forEach(function(error) {
                    });
                }
            });

        });
    </script>
@endpush
