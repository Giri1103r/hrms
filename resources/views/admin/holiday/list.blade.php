@extends('admin.layouts.admin')
@section('title', 'Holiday')
@section('pageurl', admin_url('holiday-management/holiday/list'))


@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <h4 class="card-title"></h4>
                    <div class="d-flex flex-wrap justify-content-end gap-2 p-2">
                        <x-button-filter dataId="" class="search me-1" href=""></x-button-filter>
                        <x-button-import href="{{ admin_url('holiday-management/holiday/import') }}"></x-button-import>
                    </div>
                    <div id="search" class="collapse">
                        <form action="" id="formsearch">
                            <div class="card-body">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-2 mb-3 form-input">
                                            <label for="day" class="form-label">Day</label>
                                            <select name="day" id="day" style="width: 100%"
                                                class="form-control single-select">
                                                <option value="">Select Day</option>
                                                @for ($d = 1; $d <= 31; $d++)
                                                    <option value="{{ $d }}">{{ $d }}</option>
                                                @endfor
                                            </select>
                                        </div>

                                        <div class="col-md-2 mb-3 form-input">
                                            <label for="month" class="form-label">Month</label>
                                            <select name="month" id="month" style="width: 100%"
                                                class="form-control single-select">
                                                <option value="">Select Month</option>
                                                @foreach ($months as $number => $name)
                                                    <option value="{{ $number }}">{{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-2 mb-3 form-input">
                                            <label for="year" class="form-label">Year</label>
                                            <select name="year" id="year" style="width: 100%"
                                                class="form-control single-select">
                                                <option value="">Select Year</option>
                                                @foreach ($years as $year)
                                                    <option value="{{ $year }}">{{ $year }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-2 mb-3 form-input">
                                            <label for="status" class="form-label">{{ __('common.status') }}</label>
                                            <select name="status" id="status" style="width: 100%"
                                                class="form-control single-select">
                                                <option value="">Select Status</option>
                                                <option value="{{ encryptId(1) }}">Active</option>
                                                <option value="{{ encryptId(0) }}">In-Active</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mt-3">
                                            <x-button-search></x-button-search>
                                            <x-button-reset></x-button-reset>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <hr>
                    </div>


                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="datatable-list"
                                class="table primary-table-bordered table-bordered table-striped display responsive nowrap w-100 mt-2 datatable-list">
                                <thead class="thead-primary">
                                    <tr>
                                        <th>{{ __('common.sno') }}</th>
                                        <th>Day</th>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Weekday</th>
                                        <th>{{ __('common.status') }}</th>
                                        <th>{{ __('common.created_by') }}</th>
                                        <th>{{ __('common.created_date') }}</th>
                                        <th>Updated By</th>
                                        <th>Updated Date</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
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
            var table = $('.datatable-list').DataTable({
                autoWidth: false,
                responsive: true,
                processing: false,
                serverSide: true,
                searching: true,
                ordering: false,
                layout: {
                    top2Start: 'buttons',
                    top2End: {
                        search: {
                            placeholder: ''
                        }
                    },
                    topStart: '',
                    topEnd: '',
                    bottomStart: '',
                    bottomEnd: '',
                    bottom2Start: 'info',
                    bottom2End: 'paging'
                },

                ajax: {
                    url: "{{ admin_url('holiday-management/holiday/list') }}",
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: function(d) {
                        d.day = $('#day').val();
                        d.month = $('#month').val();
                        d.year = $('#year').val();
                        d.status = $('#status').val();
                    },
                    error: function(xhr) {
                        if (xhr.status === 419) {
                            alert('Session has expired. You will be redirected to the login page.');
                            window.location.href = "{{ url('') }}";
                        }
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', searchable: false },
                    { data: 'day', name: 'day' },
                    { data: 'month', name: 'month' },
                    { data: 'year', name: 'year' },
                    { data: 'weekday', name: 'weekday' },
                    { data: 'status', name: 'status' },
                    { data: 'created_by', name: 'created_by' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'updated_by', name: 'updated_by' },
                    { data: 'updated_at', name: 'updated_at' },
                ],
                language: {
                    paginate: {
                        first: '<i title="{{ __('common.first') }}" class="fa fa-angle-double-left" aria-hidden="true"></i>',
                        last: '<i title="{{ __('common.last') }}" class="fa fa-angle-double-right" aria-hidden="true"></i>',
                        next: '<i title="{{ __('common.next') }}" class="fa fa-angle-right" aria-hidden="true"></i>',
                        previous: '<i title="{{ __('common.previous') }}" class="fa fa-angle-left" aria-hidden="true"></i>',
                    },
                    "info": "{{ __('common.dt_info') }}",
                    "infoEmpty": "{{ __('common.dt_infoEmpty') }}",
                    "infoFiltered": "{{ __('common.dt_infoFiltered') }}",
                },
                aLengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                buttons: [{
                    "extend": 'pageLength',
                    "text": '{{ __('common.show') }} 10 {{ __('common.records') }}'
                }],
            });

            table.on('length.dt', function(e, settings, len) {
                $('.buttons-page-length').find('span')
                    .text('{{ __('common.show') }} ' + len + ' {{ __('common.records') }}');
            });

            $(document).on('click', '#searchform', function() {
                table.draw();
            });

            $(document).on('click', '#resetform', function() {
                $('#formsearch .single-select').val('').trigger('change');
                setTimeout(function() {
                    table.draw();
                }, 150);
            });
        });
    </script>
@endpush
