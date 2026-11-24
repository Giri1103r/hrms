@extends('admin.layouts.admin')
@section('title', __('attendance.missed_attendance'))
@section('pageurl', admin_url('attendance-management/daily-attendance/list'))

@section('content')
    <div class="clearfix"></div>
    <div class="content-body default-height">
        <div class="container-fluid main-content">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">{{ __('attendance.missed_attendance') }}</h4>
                            <div class="align-back-btc d-flex justify-content-end align-items-center">
                                <x-button-back href="{{ admin_url('attendance-management/daily-attendance/list') }}"></x-button-back>
                            </div>
                        </div>

                        <div class="card-body">
                            <p class="text-muted">
                                {{ __('attendance.missed_attendance_note', ['days' => $lookbackDays]) }}
                            </p>

                            @if ($dates->isEmpty())
                                <p class="text-muted mb-0">{{ __('attendance.no_missed_attendance') }}</p>
                            @else
                                <form method="POST" id="missedattendance" action="{{ admin_url('attendance-management/daily-attendance/missed/submit') }}">
                                    @csrf
                                    @error('selected_dates') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label require" for="attendance_type">{{ __('attendance.attendance_type') }}</label>
                                                <select name="attendance_type" id="attendance_type" class="form-control single-select" style="width: 100%;">
                                                    @foreach ($typeList as $value => $label)
                                                        <option value="{{ $value }}" @selected(old('attendance_type') == $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @error('attendance_type') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <div class="form-group form-input">
                                                <label class="form-label" for="reason">{{ __('common.remarks') }}</label>
                                                <textarea name="reason" id="reason" rows="1" maxlength="500"
                                                    class="form-control @error('reason') is-invalid @enderror"
                                                    placeholder="{{ __('common.remarks') }}">{{ old('reason') }}</textarea>
                                                @error('reason') <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table primary-table-bordered table-bordered table-striped w-100">
                                            <thead class="thead-primary">
                                                <tr>
                                                    <th style="width: 40px;"><input type="checkbox" id="select-all-dates"></th>
                                                    <th>{{ __('common.date') }}</th>
                                                    <th>{{ __('attendance.day') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dates as $date)
                                                    <tr>
                                                        <td><input type="checkbox" class="date-check" name="dates[]" value="{{ $date }}"></td>
                                                        <td>{{ Displaydateformat($date) }}</td>
                                                        <td>{{ date('l', strtotime($date)) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-end gap-2 mt-2">
                                        <x-button-submit></x-button-submit>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('script')
    <script type="text/javascript" nonce="projectcab">
        $(function() {
            $('#select-all-dates').on('change', function() {
                $('.date-check').prop('checked', $(this).is(':checked'));
            });

            $(document).on('change', '.date-check', function() {
                if (!$(this).is(':checked')) {
                    $('#select-all-dates').prop('checked', false);
                } else if ($('.date-check:checked').length === $('.date-check').length) {
                    $('#select-all-dates').prop('checked', true);
                }
            });

            $('#missedattendance').on('submit', function(e) {
                if (!$('.date-check:checked').length) {
                    e.preventDefault();
                    alert('{{ __('attendance.select_one_date_alert') }}');
                    return false;
                }
            });
        });
    </script>
@endpush
