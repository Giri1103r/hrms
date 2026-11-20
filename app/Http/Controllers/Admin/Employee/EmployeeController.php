<?php

namespace App\Http\Controllers\Admin\Employee;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Illuminate\Support\Facades\File;

use Str;
use PDF;
use Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Exception;
use DataTables;
use Response;
use App\Jobs\ImportdepartmentJob;
use App\Jobs\ImportEmployeeJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Rules\IdNumber;

use App\Mail\RestEmployeePasswordEmail;
use App\Models\Master\Bloodgroup;
use App\Models\Master\Company;
use App\Models\Employee\Employee;
use App\Models\Master\Location;
use App\Models\Master\Shift;
use App\Models\Master\Department;
use App\Models\Master\Designation;
use App\Models\Employee\Master\EmployeeType;
use App\Models\Employee\Master\EmployeeStatus;
use App\Models\Employee\Master\Document;
use App\Models\Employee\EmployeeDocument;
use App\Models\User;
use App\Models\Administration\UploadLog;
use App\Models\Administration\UserRole;
use Illuminate\Contracts\Session\Session as SessionSession;
use Illuminate\Support\Facades\Session as FacadesSession;

class EmployeeController extends Controller
{

    private $company;
    private $user;
    private $department;
    private $location;
    private $shift;
    private $uploadlog;
    private $employee;
    private $userrole;
    private $bloodgroup;


    public function __construct()
    {

        $this->company = new Company();
        $this->user = new User();
        $this->shift = new Shift();
        $this->location = new Location();
        $this->department = new Department();
        $this->uploadlog = new UploadLog();
        $this->employee = new Employee();
        $this->userrole = new UserRole();
        $this->bloodgroup = new Bloodgroup();

    }


    public function index(Request $request)
    {
        if (Auth::check()) {
            if ($request->ajax()) {
                try {
                    $data =  $this->employee->list();

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
                        ->addColumn('created_at', function ($row) {
                            return Displaydatetimeformat($row->created_at);
                        })

                        ->addColumn('action', function ($row) {
                            $btn = '';
                            if (CheckUserPermission('view')) {
                                $btn = '<a href="' . admin_url('employee-management/employee/view/' . encryptId($row->id)) . '"   class="" title="View"><i class="fa-solid fa-eye"></i></a> ';
                            }
                            if (CheckUserPermission('edit')) {
                                $btn .= '<a href="' . admin_url('employee-management/employee/edit/' . encryptId($row->id)) . '" class=" " title="Edit"><i class="fa-solid fa-pen-to-square"></i> ';
                            }
                            if (CheckUserRole(ROLE_ADMIN) || CheckUserRole(ROLE_SUPERADMIN)) {
                                $btn .= '<a href="' . admin_url('employee-management/employee/passwordchange/' . encryptId($row->id)) . '" class="key-icon" title="passwordchange"><i class="fas fa-key"></i> ';
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
        $companyList  = $this->company->where('status', '1')->get();
        $data = array(
            'companyList' => $companyList,
            'shiftList' => $this->shift->getShift(),
        );
        return view('admin.employee.list', $data);
    }

    public function View(Request $request)
    {
        try {
            $id = decryptId($request->id);
            if (Auth::check()) {

                $userrole  = $this->userrole->select('id', 'role_name')->where('status', '1')->get();
                $employee = $this->employee->selectOne($id);

                $data = array(
                    'employee' => $employee,
                    'userrole' => $userrole,
                );
            }
            return view('admin.employee.view', $data);
        } catch (Exception $ex) {
            report($ex);
        }
    }

    public function Edit(Request $request)
    {
        try {
            $employee = $this->employee->find(decryptId($request->id));
            if (!$employee) {
                Session::flash('error', 'Employee not found');
                return redirect(admin_url('employee-management/employee/list'));
            }

            return view('admin.employee.edit', $this->formData($employee));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('employee-management/employee/list'));
        }
    }

    public function Update(Request $request)
    {
        $employee = $this->employee->find(decryptId($request->id));
        if (!$employee) {
            Session::flash('error', 'Employee not found');
            return redirect(admin_url('employee-management/employee/list'));
        }

        $this->prepareFormInput($request);
        $validator = Validator::make($request->all(), $this->formRules($request, $employee), $this->formMessages());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $savedFiles = [];

        try {
            DB::beginTransaction();

            $employee = $this->employee->updateEmployee($employee->id, $this->formRecord($request), Auth::id());
            $this->user->userUpdate($employee);

            $removeIds = array_filter(array_map('decryptId', (array) $request->input('remove_documents', [])));
            if ($removeIds) {
                EmployeeDocument::where('employee_id', $employee->id)
                    ->whereIn('id', $removeIds)
                    ->update(['status' => 0, 'trash' => 'YES']);
            }

            $savedFiles = $this->storeDocuments($employee, $request->file('documents', []), $request->input('documents', []));

            DB::commit();

            Session::flash('success', 'Your data has been updated successfully!');
            return redirect(admin_url('employee-management/employee/list'));
        } catch (Exception $ex) {
            DB::rollBack();
            File::delete($savedFiles);
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect()->back()->withInput();
        }
    }

    public function StatusChange(Request $request)
    {

        try {
            $id = decryptId($request->id);

            $this->employee->statuschange($id);

            return response()->json(['status' => 'success', 'msg' => 'Employee status changed'], 200);
        } catch (Exception $ex) {
            report($ex);
            return response()->json(['status' => 'error', 'msg' => 'Please try after some time'], 406);
        }
    }
    public function PasswordUpdate(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $userrole  = $this->userrole->select('id', 'role_name')->where('status', '1')->get();
            $employee = $this->employee->selectOne($id);

            $data = array(
                'employee' => $employee,
                'userrole' => $userrole,
            );

            return view('admin.employee.passwordupdate', $data);
        } catch (Exception $error) {

            report($error);
        }
    }

    public function PasswordUpdateSubmit(Request $request)
    {
        try {
            $id = decryptId($request->id);

            $rules = [
                'password' => 'required|confirmed',
            ];
            $messages = [
                'password.required' => 'Please enter the Password',
                'password_confirmation.required' => 'Please enter the Confirm Password',
                'password.confirmed' => 'The password confirmation does not match.',
            ];
            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {

                return redirect()->back()->withErrors($validator)->withInput();
            }
            $employee = $this->employee->find($id);
            $user = $this->user->passwordUpdate($employee->login_id);
            if ($user) {
                $updatedUser = $this->user->find($employee->login_id);
                $newpass =  $request->password;
                if ($updatedUser && !empty($updatedUser->email)) {
                    Mail::to($updatedUser->email)->queue(new RestEmployeePasswordEmail($updatedUser,$newpass));
                }
                Session::flash('success', 'Employee password updated successfully!');
                return redirect(admin_url('employee-management/employee/list'));
            }
            Session::flash('success', 'Employee password updated successfully!');
            return redirect(admin_url('employee-management/employee/list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', 'Something went wrong, Please try after sometimes!');
            return redirect(admin_url('employee-management/employee/list'));
        }
    }

    public function ExportExcel(Request $request)
    {

        try {

            $allData = $this->employee->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Employee Id',
                'Employee Name',
                'Email',
                'Phone Number',
                'Company Name',
                'Location Name',
                'Shift',
                'Department Name',
                'Employee Status',
                'Reporting Manager',
                __("common.status"),
                __("common.created_date"),
            ];

            $i = 1;
            foreach ($allData as $data) {

                $export = [];
                $export[] =  $i;
                $export[] =  $data->emp_id;
                $export[] =  $data->emp_name;
                $export[] =  $data->email;
                $export[] =  $data->mobile_no;
                $export[] =  $data->company_name;
                $export[] =  $data->location_name;
                $export[] =  $data->shift_name;
                $export[] =  $data->department_name;
                $export[] =  $data->employee_status;
                $export[] =  $data->reporting_manager;
                $export[] =  $data->status == 1 ? 'Active' : 'In-Active';
                $export[] =  Displaydateformat($data->created_at);

                $exportData[] = $export;

                $i++;
            }

            $writer = SimpleExcelWriter::streamDownload('Employee Master.xlsx')
                ->addHeader($header)
                ->addRows(
                    $exportData
                );
        } catch (Exception $ex) {

          report($ex);
            Session::flash('error', 'Something went wrong, Please try after sometimes!');
            return redirect(admin_url('employee-management/employee/list'));
        }
    }

    public function ExportPdf(Request $request)
    {

        try {

            $allData = $this->employee->exportdata();

            if ($allData->isEmpty()) {
                return redirect()->back()->with('error', 'No data found');
            }

            $header = [
                __("common.sno"),
                'Employee Id',
                'Employee Name',
                'Email',
                'Phone Number',
                'Company Name',
                'Location Name',
                'Shift',
                'Department Name',
                'Employee Status',
                'Reporting Manager',
                __("common.status"),
                __("common.created_date"),
            ];

            $data = array(
                'header' => $header,
                'content' => $allData,
                'pagetitle' => "Employee Details",
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

            $view = view('admin.employee.pdf', $data);
            $html = $view->render();



            $mpdf->WriteHTML($html);

            $filename = "Employee Master.pdf";

            /* Hand the PDF back as a response instead of echoing it: mPDF sends
               its own Content-Length and then returns, so letting the action fall
               through made the framework append a second body to the download. */
            return response($mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $ex) {

          report($ex);
            Session::flash('error', 'Something went wrong, Please try after sometimes!');
            return redirect(admin_url('employee-management/employee/list'));
        }
    }


    public function add(Request $request)
    {
        try {
            return view('admin.employee.add', $this->formData());
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('employee-management/employee/list'));
        }
    }

    public function store(Request $request)
    {
        $this->prepareFormInput($request);
        $validator = Validator::make($request->all(), $this->formRules($request), $this->formMessages());
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $savedFiles = [];

        try {
            DB::beginTransaction();

            $employee = $this->employee->storeEmployee(
                ['emp_id' => trim($request->emp_id)] + $this->formRecord($request),
                Auth::id()
            );

            $loginId = $this->user->storeForEmployee($employee, Auth::id());
            $this->employee->updateUserId($employee->emp_id, $loginId);

            $savedFiles = $this->storeDocuments($employee, $request->file('documents', []), $request->input('documents', []));

            DB::commit();

            Session::flash('success', 'Employee created. Login: ' . $employee->emp_id . ' / User@' . $employee->emp_id);
            return redirect(admin_url('employee-management/employee/list'));
        } catch (Exception $ex) {
            DB::rollBack();
            File::delete($savedFiles);
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Everything the shared Add / Edit form needs. $employee is null on Add.
     */
    private function formData(?Employee $employee = null): array
    {
        $managers = $this->employee->select('emp_id', 'emp_name')->where('status', 1)->orderBy('emp_name');
        if ($employee) {
            $managers->where('id', '!=', $employee->id);
        }

        return [
            'employee' => $employee,
            'documents' => $employee
                ? $employee->documents()->with('documentType')->orderBy('document_type_id')->get()
                : collect(),
            'companyList' => $this->company->getcompany(),
            'shiftList' => (new Shift())->where('status', 1)
                ->when($employee?->shift, fn ($q) => $q->orWhere('id', $employee->shift))
                ->orderBy('shift_name')->get(),
            'bloodgroup' => $this->bloodgroup->getBloodgroup(),
            'designationList' => (new Designation())->getDesignation(),
            'employeeTypeList' => (new EmployeeType())->getEmployeeType(),
            'employeeStatusList' => (new EmployeeStatus())->getEmployeeStatus(),
            'documentTypeList' => (new Document())->where('status', 1)->orderBy('document_name')->get(['id', 'document_name']),
            'userrole' => $this->userrole->select('id', 'role_name')->where('status', '1')->get(),
            'managerList' => $managers->get(),
        ];
    }

    /** Decrypts the select values so the rules below can check real ids. */
    private function prepareFormInput(Request $request): void
    {
        // decryptId('') returns false, which "required" would accept, so empty stays null.
        $request->merge([
            'company_id' => $request->filled('company') ? decryptId($request->company) : null,
            'location_id' => $request->filled('location') ? decryptId($request->location) : null,
            'shift_id' => $request->filled('shift') ? decryptId($request->shift) : null,
            'department_id' => $request->filled('department') ? decryptId($request->department) : null,
            'id_number' => $request->filled('id_number') ? IdNumber::normalize($request->id_number) : null,
        ]);
    }

    /** Validation shared by Add and Edit; on Edit the unique checks skip this employee. */
    private function formRules(Request $request, ?Employee $employee = null): array
    {
        $uniqueEmployee = fn ($column) => Rule::unique('masters_employee', $column)->ignore($employee?->id);

        $rules = [
            'emp_name' => 'required|max:100',
            'gender' => 'required|in:Male,Female,Other',
            'email' => ['required', 'email', 'max:50', $uniqueEmployee('email'), Rule::unique('users', 'email')->ignore($employee?->login_id)],
            'mobile_no' => ['required', 'regex:/^[0-9]{10,15}$/'],
            'joining_date' => 'required|date_format:d-m-Y|before:today',
            'nationality' => 'nullable|max:50',
            'id_type' => ['nullable', 'required_with:id_number', Rule::in(array_keys(IdNumber::TYPES))],
            'id_number' => [
                'nullable',
                'required_with:id_type',
                'max:100',
                new IdNumber($request->id_type),
                $uniqueEmployee('id_number')->where('id_type', $request->id_type),
            ],
            'blood_group' => 'nullable|exists:masters_blood_group,id',
            'company_id' => 'required|exists:master_company,id',
            'location_id' => ['required', Rule::exists('masters_location', 'id')->where('company_id', $request->company_id)],
            'shift_id' => ['required', Rule::exists('masters_shift', 'id')->where('trash', 'NO')],
            'department_id' => ['nullable', Rule::exists('masters_department', 'id')->where('location_id', $request->location_id)],
            'designation' => 'required|exists:masters_designation,designation_name',
            'employee_type' => 'required|exists:masters_employee_type,employee_type_name',
            'employee_status' => 'required|exists:masters_employee_status,employee_status_name',
            'reporting_manager' => ['nullable', 'exists:masters_employee,emp_id'],
            'user_role' => 'required|array|min:1',
            'user_role.*' => 'exists:template_user_role,id',
            'documents' => 'nullable|array',
            'documents.*.document_type_id' => ['required', 'distinct', Rule::exists('masters_document_type', 'id')->where('status', 1)],
            'documents.*.files' => 'required|array|min:1',
            'documents.*.files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'remove_documents' => 'nullable|array',
        ];

        if ($employee) {
            $rules['reporting_manager'][] = Rule::notIn([$employee->emp_id]);
        } else {
            $rules['emp_id'] = ['required', 'max:50', 'regex:/^[A-Za-z0-9_\-\/]+$/', Rule::unique('masters_employee', 'emp_id'), Rule::unique('users', 'employee_id')];
        }

        return $rules;
    }

    private function formMessages(): array
    {
        return [
            'emp_id.required' => 'Please enter the Employee ID',
            'emp_id.unique' => 'This Employee ID already exists',
            'emp_id.regex' => 'Employee ID can only contain letters, numbers, -, _ and /',
            'emp_name.required' => 'Please enter the Employee Name',
            'email.unique' => 'This email is already used by another employee or user',
            'mobile_no.regex' => 'Mobile number must be 10 to 15 digits',
            'email.email' => 'Please enter a valid email address',
            'id_number.required_with' => 'Please enter the ID Number for the selected ID Type',
            'id_type.required_with' => 'Please select the ID Type for this ID Number',
            'id_number.unique' => 'This ID Number is already registered to another employee',
            'joining_date.date_format' => 'Joining date must be in DD-MM-YYYY format',
            'joining_date.before' => 'Joining Date must be a past date (before today)',
            'company_id.required' => 'Please select the Company',
            'location_id.required' => 'Please select the Location',
            'location_id.exists' => 'The Location does not belong to the selected Company',
            'shift_id.required' => 'Please select the Shift',
            'shift_id.exists' => 'Please select a Shift from the list',
            'department_id.exists' => 'The Department does not belong to the selected Location',
            'designation.exists' => 'Please select a Designation from the list',
            'employee_type.exists' => 'Please select an Employee Type from the list',
            'employee_status.exists' => 'Please select an Employee Status from the list',
            'reporting_manager.not_in' => 'An employee cannot report to themselves',
            'user_role.required' => 'Please select at least one User Role',
            'documents.*.document_type_id.required' => 'Please select the Document Type',
            'documents.*.document_type_id.distinct' => 'Each Document Type can be added only once',
            'documents.*.files.required' => 'Please choose at least one file for this Document Type',
            'documents.*.files.*.mimes' => 'Documents must be PDF, JPG, PNG, DOC or DOCX files',
            'documents.*.files.*.max' => 'Each document must be 5 MB or smaller',
        ];
    }

    /** The employee columns the Add / Edit form writes (everything but emp_id). */
    private function formRecord(Request $request): array
    {
        return [
            'emp_name' => trim($request->emp_name),
            'gender' => $request->gender,
            'nationality' => $request->nationality,
            'id_type' => $request->id_type,
            'id_number' => $request->id_number,
            'blood_group' => $request->blood_group,
            'email' => strtolower(trim($request->email)),
            'mobile_no' => $request->mobile_no,
            'joining_date' => DBdateformat($request->joining_date),
            'company' => $request->company_id,
            'location' => $request->location_id,
            'shift' => $request->shift_id,
            'department' => $request->department_id,
            'designation' => $request->designation,
            'employee_type' => $request->employee_type,
            'employee_status' => $request->employee_status,
            'reporting_manager' => $request->reporting_manager,
            'user_role' => implode(',', $request->user_role),
        ];
    }

    /**
     * Saves each document row's files under uploads/employee/documents/{employee id}
     * and records them in masters_employee_document. Returns the stored paths so the
     * caller can remove them if the transaction is rolled back.
     */
    private function storeDocuments(Employee $employee, array $uploads, array $rows): array
    {
        $saved = [];
        $relativeDir = 'uploads/employee/documents/' . $employee->id;
        $folderPath = public_path($relativeDir);

        foreach ($rows as $index => $row) {
            $files = $uploads[$index]['files'] ?? [];
            if (!$files) {
                continue;
            }

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true);
            }

            foreach ($files as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileName = time() . Str::random(10) . '.' . $extension;
                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize();

                $file->move($folderPath, $fileName);
                $saved[] = $folderPath . '/' . $fileName;

                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'document_type_id' => $row['document_type_id'],
                    'file_orgname' => $originalName,
                    'file_name' => $fileName,
                    'file_path' => 'public/' . $relativeDir . '/' . $fileName,
                    'file_size' => $fileSize,
                    'file_extension' => $extension,
                    'created_by' => Auth::id(),
                ]);
            }
        }

        return $saved;
    }

    public function import(Request $request)
    {
        return view('admin.employee.import');
    }

    public function importSubmit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_upload' => 'required|file|mimes:xlsx|max:5120',
        ], [
            'employee_upload.required' => 'Please upload a file',
            'employee_upload.mimes' => 'Please upload an Excel file (.xlsx)',
            'employee_upload.max' => 'The file must not be larger than 5 MB',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            $file = $request->file('employee_upload');
            $uploadpath = 'public/uploads/employee';
            $folderPath = public_path('uploads/employee');

            if (!File::exists($folderPath)) {
                File::makeDirectory($folderPath, 0755, true);
            }

            $filenewname = time() . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $fileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $fileExt = $file->getClientOriginalExtension();

            $file->move($uploadpath, $filenewname);

            $path = $uploadpath . '/' . $filenewname;
            $user_id = Auth::id();

            $insert_id = $this->uploadlog->create([
                'upload_type' => exportsamplefile('employee')->id,
                'upload_status' => 0,
                'file_name' => $filenewname,
                'file_orgname' => $fileName,
                'file_path' => $path,
                'file_size' => $fileSize,
                'file_extension' => $fileExt,
                'created_by' => $user_id,
            ])->id;

            dispatch(new ImportEmployeeJob([
                'user_id' => $user_id,
                'log_id' => $insert_id,
                'path' => $path,
            ]));

            Session::flash('success', __('common.file_upload_success_msg'));
            return redirect(admin_url('employee-management/employee/list'));
        } catch (Exception $ex) {
            report($ex);
            Session::flash('error', __('common.error_msg'));
            return redirect(admin_url('employee-management/employee/import'));
        }
    }

    public function DownloadSample(Request $request)
    {
        $filedetails = exportsamplefile('employee');

        return redirect(url($filedetails->sample_file));
    }

    public function Uniquecheck(Request $request)
    {
        if ($request->ajax()) {
            $email = $request->email;
            $id = $request->id;
            if ($id == '') {
                $record = $this->employee->uniqueCheck($email);
            } else {
                $id = decryptId($id);
                $record = $this->employee->ExistuniqueCheck($email, $id);
            }
            if ($record->count()) {
                return Response::json(false);
            }
            return Response::json(true);
        }
    }

    /** Departments of a company, for the filter on the employee list. */
    public function companyajax(Request $request)
    {
        $departments = $this->department
            ->where('company_id', decryptId($request->company_id))
            ->where('status', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($department) => ['id' => encryptId($department->id), 'name' => $department->department_name]);

        return response()->json(['department' => $departments]);
    }

    /** Departments of a location, for the Add / Edit form. $id keeps an inactive current department listed. */
    public function list(Request $request, $location_id, $id = '0')
    {
        $locationId = decryptId($location_id);
        $currentId = $id !== '0' ? decryptId($id) : null;

        $departments = $this->department
            ->where('location_id', $locationId)
            ->where(fn ($q) => $q->where('status', 1)->when($currentId, fn ($q) => $q->orWhere('id', $currentId)))
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($department) => ['id' => encryptId($department->id), 'name' => $department->department_name]);

        return response()->json($departments);
    }
}
