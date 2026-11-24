<?php

namespace App\Http\Controllers\Admin\Leave\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Yajra\DataTables\Facades\DataTables;

use App\Models\Leave\Master\LeaveType;
use App\Models\User;
use App\Models\Administration\UploadLog;


class LeaveTypeController extends Controller
{


    private $user;
    private $uploadlog;

    private $leavetype;


    public function __construct()
    {


        $this->user = new User();
        $this->leavetype = new LeaveType();

        $this->uploadlog = new UploadLog();
    }


    public function index(Request $request)
    {
        if (Auth::check()) {
            if ($request->ajax()) {

                try {

                    $data =  $this->leavetype->list();

                    $datatables = Datatables::of($data['data'])
                        ->addIndexColumn()
                        ->addColumn('status', function ($row) {
                            $text = "<span style='color:red'>In-Active<span>";
                            if ($row->status == 1) {
                                $text = "<span style='color:green;cursor:pointer' class= 'statusChange' data-id='" . encryptId($row->id) . "' data-type = '1' >Active<span>";
                            } else if ($row->status == 0) {
                                $text = "<span style='color:red;cursor:pointer' class= 'statusChange' data-id='" . encryptId($row->id) . "' data-type = '0' >In-Active<span>";
                            }
                            return $text;
                        })
                        ->addColumn('monthly_credit', function ($row) {
                            return \App\Models\Leave\LeaveRequest::formatDays($row->monthly_credit) . ' / month';
                        })
                        ->addColumn('created_at', function ($row) {
                            return Displaydatetimeformat($row->created_at);
                        })
                        ->addColumn('created_by', function ($row) {
                            return getUsername($row->created_by);
                        })
                        ->addColumn('action', function ($row) {
                            $btn = '';
                            if (CheckUserPermission('view')) {
                                $btn = '<a href="' . admin_url('leave-management/master/leave-type/view/' . encryptId($row->id)) . '"   class="" title="View"><i class="fa-solid fa-eye"></i></a> ';
                            }
                            if (CheckUserPermission('edit')) {
                                $btn .= '<a href="' . admin_url('leave-management/master/leave-type/edit/' . encryptId($row->id)) . '" class=" " title="Edit"><i class="fa-solid fa-pen-to-square"></i> ';
                            }
                            return $btn;
                        })
                        ->rawColumns(['action', 'created_date', 'created_by', 'status'])
                        ->setFilteredRecords($data['filter_records'])
                        ->setTotalRecords($data['total_records'])
                        ->skipPaging()
                        ->make(true);
                    return $datatables;
                } catch (Exception $ex) {
                    report($ex);
                    return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
                }
            }
        }
        $data = array();
        return view('admin.leave.master.leave_type.list', $data);
    }

    public function Add(Request $request)
    {

        try {

            $data = array();
            return view('admin.leave.master.leave_type.add', $data);
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function Store(Request $request)
    {
        try {
            DB::beginTransaction();

            $rules = [
                'leave_type_name' => 'required|unique:masters_leave_type,leave_type_name',
                'monthly_credit' => 'required|numeric|min:0|max:31|multiple_of:0.5',
            ];
            $messages = [
                'leave_type_name.required' => 'Please Enter Leave Type Name',
                'leave_type_name.unique' => 'Leave Type Name should be unique',
                'monthly_credit.required' => 'Please Enter Monthly Credit',
                'monthly_credit.numeric' => 'Monthly Credit must be a number',
                'monthly_credit.min' => 'Monthly Credit cannot be negative',
                'monthly_credit.max' => 'Monthly Credit cannot be more than 31 days',
                'monthly_credit.multiple_of' => 'Monthly Credit must be in steps of 0.5 days',
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }


            $this->leavetype->store();
            DB::commit();

            Session::flash('success', 'Your data has been created successfully!');
            return redirect(admin_url('leave-management/master/leave-type/list'));
        } catch (Exception $ex) {

            report($ex);
            DB::rollBack();
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function View(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $leavetype = $this->leavetype->selectOne($id);

            if (!$leavetype) {
                Session::flash('error', __('common.error_msg'));
                return redirect(admin_url('leave-management/master/leave-type/list'));
            }

            $data = array(
                'leavetype' => $leavetype,
            );

            return view('admin.leave.master.leave_type.view', $data);
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function Edit(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $leavetype = $this->leavetype->find($id);

            if (!$leavetype) {
                Session::flash('error', __('common.error_msg'));
                return redirect(admin_url('leave-management/master/leave-type/list'));
            }

            $data = array(
                'leavetype' => $leavetype,
            );

            return view('admin.leave.master.leave_type.edit', $data);
        } catch (Exception $error) {
            report($error);

            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function Update(Request $request)
    {
        try {
            DB::beginTransaction();

            $id = decryptId($request->id);
            $rules = [
                'leave_type_name' => 'required|unique:masters_leave_type,leave_type_name,' . $id . ',id',
                'monthly_credit' => 'required|numeric|min:0|max:31|multiple_of:0.5',
            ];
            $messages = [
                'leave_type_name.required' => 'Please Enter Leave Type Name',
                'leave_type_name.unique' => 'Leave Type Name should be unique',
                'monthly_credit.required' => 'Please Enter Monthly Credit',
                'monthly_credit.numeric' => 'Monthly Credit must be a number',
                'monthly_credit.min' => 'Monthly Credit cannot be negative',
                'monthly_credit.max' => 'Monthly Credit cannot be more than 31 days',
                'monthly_credit.multiple_of' => 'Monthly Credit must be in steps of 0.5 days',
            ];
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $this->leavetype->updates($id);
            DB::commit();

            Session::flash('success', __('common.updated_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        } catch (Exception $ex) {
            report($ex);
            DB::rollBack();
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function StatusChange(Request $request)
    {

        try {
            $id = decryptId($request->id);

            $this->leavetype->statuschange($id);

            return response()->json(['status' => 'success', 'msg' => 'Leave Type status changed'], 200);
        } catch (Exception $ex) {
            report($ex);
            return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
        }
    }

    public function Delete(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $this->leavetype->deleterecord($id);

            return response()->json(['status' => 'success', 'msg' => 'Leave Type deleted successfully'], 200);
        } catch (Exception $ex) {
            report($ex);
            return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
        }
    }


    public function ExportExcel(Request $request)
    {

        try {

            $allData = $this->leavetype->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Leave Type ID',
                'Leave Type Name',
                __("common.status"),
                __("common.created_by"),
                __("common.created_date"),
            ];

            $exportData = [];
            $i = 1;
            foreach ($allData as $data) {

                $export = [];
                $export[] =  $i;
                $export[] =  $data->lt_code;
                $export[] =  $data->leave_type_name;
                $export[] =  $data->status == 1 ? 'Active' : 'In-Active';
                $export[] =  getusername($data->created_by);
                $export[] =  Displaydateformat($data->created_at);

                $exportData[] = $export;

                $i++;
            }

            $writer = SimpleExcelWriter::streamDownload('Leave Type Master.xlsx')
                ->addHeader($header)
                ->addRows(
                    $exportData
                );
        } catch (Exception $ex) {

            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function ExportPdf(Request $request)
    {

        try {

            $allData = $this->leavetype->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Leave Type ID',
                'Leave Type Name',
                __("common.status"),
                __("common.created_by"),
                __("common.created_date"),
            ];

            $data = array(
                'header' => $header,
                'content' => $allData,
                'pagetitle' => "Leave Type Details",
            );

            $property = [
                'tempDir' => public_path('pdf/temp'),
                'mode' => 'c',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,

            ];

            $mpdf = new \Mpdf\Mpdf($property);
            $mpdf->setAutoTopMargin = 'stretch';

            $view = view('admin.leave.master.leave_type.pdf', $data);
            $html = $view->render();

            $mpdf->WriteHTML($html);

            $filename = "Leave Type Master.pdf";

            
            return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $ex) {

            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('leave-management/master/leave-type/list'));
        }
    }

    public function Uniquecheck(Request $request)
    {
        if ($request->ajax()) {

            $leave_type_name = $request->leave_type_name;
            $id = $request->id;

            if ($id == '') {

                $record = $this->leavetype->uniqueCheck($leave_type_name);
            } else {

                $id = decryptId($id);
                $record = $this->leavetype->ExistuniqueCheck($leave_type_name, $id);
            }
            if ($record->count()) {
                return Response::json(false);
            }
            return Response::json(true);
        }
    }

    public function list(Request $request)
    {
        $id = decryptId($request->id);
        $leavetypes = $this->leavetype->ajaxList($id);

        return response()->json($leavetypes);
    }
}
