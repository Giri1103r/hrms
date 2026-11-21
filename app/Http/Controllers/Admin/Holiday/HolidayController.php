<?php

namespace App\Http\Controllers\Admin\Holiday;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Exception;
use Yajra\DataTables\Facades\DataTables;

use App\Jobs\ImportHolidayJob;
use App\Rules\ExcelFile;
use App\Models\Holiday\Holiday;
use App\Models\Administration\UploadLog;

/**
 * Holiday Management → Holiday: list and Excel import.
 */
class HolidayController extends Controller
{
    private $holiday;

    public function __construct()
    {
        $this->holiday = new Holiday();
    }

    public function index(Request $request)
    {
        if (Auth::check() && $request->ajax()) {
            try {
                $data = $this->holiday->list();

                return DataTables::of($data['data'])
                    ->addIndexColumn()
                    ->addColumn('day', function ($row) {
                        return $row->day ?: '-';
                    })
                    ->addColumn('month', function ($row) {
                        return e(Holiday::monthName($row->month));
                    })
                    ->addColumn('weekday', function ($row) {
                        return Holiday::weekdayName($row->day, $row->month, $row->year) ?: '-';
                    })
                    ->addColumn('status', function ($row) {
                        return $row->status == 1
                            ? "<span style='color:green'>Active</span>"
                            : "<span style='color:red'>In-Active</span>";
                    })
                    ->addColumn('created_by', function ($row) {
                        return getUsername($row->created_by);
                    })
                    ->addColumn('created_at', function ($row) {
                        return Displaydatetimeformat($row->created_at);
                    })
                    ->addColumn('updated_by', function ($row) {
                        return $row->updated_by ? getUsername($row->updated_by) : '-';
                    })
                    ->addColumn('updated_at', function ($row) {
                        return $row->updated_at ? Displaydatetimeformat($row->updated_at) : '-';
                    })
                    // Holiday::list() already searched/filtered; stop DataTables
                    // from filtering again on raw month numbers.
                    ->filter(function () {
                    })
                    ->rawColumns(['status'])
                    ->setFilteredRecords($data['filter_records'])
                    ->setTotalRecords($data['total_records'])
                    ->skipPaging()
                    ->make(true);
            } catch (Exception $ex) {
                report($ex);
                return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
            }
        }

        $data = [
            'months' => Holiday::monthNames(),
            'years' => $this->holiday->yearList(),
        ];
        return view('admin.holiday.list', $data);
    }

    public function import(Request $request)
    {
        return view('admin.holiday.import');
    }

    /**
     * Imports the uploaded file straight away (holiday files are small) and
     * shows how many rows were imported and which lines were skipped.
     */
    public function importSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'holiday_upload' => ['required', 'file', 'max:5120', new ExcelFile()],
        ], [
            'holiday_upload.required' => 'Please upload a file',
            'holiday_upload.max' => 'The file must not be larger than 5 MB',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $file = $request->file('holiday_upload');
            $uploadpath = 'public/uploads/holiday';
            $folderPath = public_path('uploads/holiday');

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true);
            }

            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileExt = strtolower($file->getClientOriginalExtension());
            $filenewname = time() . Str::random(10) . '.' . $fileExt;

            $file->move($uploadpath, $filenewname);

            $path = $uploadpath . '/' . $filenewname;
            $user_id = Auth::id();

            $insert_id = UploadLog::create([
                'upload_type' => exportsamplefile('holiday')->id,
                'upload_status' => 0,
                'file_name' => $filenewname,
                'file_orgname' => $fileName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'file_extension' => $fileExt,
                'created_by' => $user_id,
            ])->id;

            // Run now (not queued) so the result can be shown on the next page.
            $result = (new ImportHolidayJob([
                'user_id' => $user_id,
                'log_id' => $insert_id,
                'path' => $path,
            ]))->handle();

            if (empty($result['errors'])) {
                Session::flash('success', $result['imported'] . ' holiday record(s) imported successfully');
                return redirect(admin_url('holiday-management/holiday/list'));
            }

            // Some or all rows were skipped: stay on the import page and list them.
            return redirect(admin_url('holiday-management/holiday/import'))
                ->with('import_result', $result);
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('holiday-management/holiday/import'));
        }
    }

    public function DownloadSample(Request $request)
    {
        $filedetails = exportsamplefile('holiday');

        return redirect(url($filedetails->sample_file));
    }
}
