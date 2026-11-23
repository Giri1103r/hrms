@extends('admin.layouts.admin')
@section('title', 'Leave Status Master Edit')
@section('pageurl', admin_url('leave-management/master/leave-status/list'))


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
                                <div class="align-back-btc d-flex justify-content-end align-items-center">
                                    <x-button-back href="{{ admin_url('leave-management/master/leave-status/list') }}"></x-button-back>
                                </div>
                            </div>

                            <div class="card-body">

                                <div class="basic-form">
                                    <form method="POST" id="leavestatusedit"
                                        action="{{ admin_url('leave-management/master/leave-status/edit/submit') }}">
                                        @csrf
                                        <input type="hidden" name="id" id="id"
                                            value="{{ encryptId($leavestatus->id) }}">

                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <div class="form-group form-input">
                                                    <label class="form-label require">Leave Status ID</label>
                                                    <input type="text" name ="ls_code" class="form-control"
                                                        placeholder="Leave Status ID"
                                                        value="{{ $leavestatus->ls_code }}" readonly>
                                                </div>
                                            </div>

                                            <div class="col-md-4 mb-2">
                                                <div class="form-group form-input">
                                                    <label class="form-label require">Leave Status Name</label>
                                                    <input type="text" name="leave_status_name" id="leave_status_name"
                                                        class="form-control" placeholder="Leave Status Name"
                                                        value="{{ old('leave_status_name', $leavestatus->leave_status_name) }}">
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="submit-button" style="text-align: right;">
                                            <x-button-submit class="submit"></x-button-submit>
                                            <x-button-reset class="submit"></x-button-reset>
                                            <x-button-cancel
                                                href="{{ admin_url('leave-management/master/leave-status/list') }}"></x-button-cancel>
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
            $('#leavestatusedit').validate({
                rules: {
                    leave_status_name: {
                        required: true,
                        maxlength: 70,
                        remote: {
                            url: '{{ admin_url('leave-management/master/leave-status/unique') }}',
                            type: 'post',
                            data: {
                                leave_status_name: function() {
                                    return $('#leave_status_name').val();
                                },
                                id: function() {
                                    return $('#id').val();
                                }
                            }
                        }
                    },

                },
                messages: {
                    leave_status_name: {
                        required: "{{ __('Leave Status Name is Required') }}",
                        maxlength: "Maximum Characters should not exceed 70",
                        remote: "{{ __('Leave Status Name should be unique') }}"
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
