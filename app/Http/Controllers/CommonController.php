<?php
namespace App\Http\Controllers;
use App\Helpers\Helper;
use App\Imports\ImportEntityApplications;
use App\Models\EntityApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Department;
use App\Models\Address;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
class CommonController extends Controller
{
    public function search(Request $request)
    {
        $queryParams = $request->all();
        $route  = $queryParams['redirectOn'];
        $queryString = '';
        foreach($queryParams as $key =>  $queryParam){
            if($key != '_token' && $key != 'redirectOn' && $queryParam != ''){
                if($queryString != ''){
                    $queryString .= '&';
                }
                if($key == 'from_date' || $key == 'to_date'){
                    $queryParam = strtotime($queryParam);
                }
                $queryString .= $key.'='.urlencode($queryParam);
            }
        }
        $url = ($queryString != '') ? $route.'?'.$queryString : $route;
        return redirect($url);
    }
    public function getStateOptionFromSelectedCountry(Request $request)
    {
        $country_id = $request->country_id;
        $state_lists = DB::table('states')->where('country_id', $country_id)->pluck('name', 'id')->all();
        $option_html = '<option value="">-- Select --</option>';
        if(!empty($state_lists))
        {
            foreach($state_lists as $state_id => $state_name)
            {
                $option_html .= '<option value="'.$state_name.'" data-id="'.$state_id.'">'.$state_name.'</option>';
            }
        }
        $response_data = ['html' => $option_html];
        return response()->json($response_data);
    }
    public function getCityOptionFromSelectedState(Request $request)
    {
        $state_id = $request->state_id;
        $city_lists = DB::table('cities')->where('state_id', $state_id)->pluck('city_name', 'id')->all();
        $option_html = '<option value="">-- Select --</option>';
        if(!empty($city_lists))
        {
            foreach($city_lists as $city_id => $city_name)
            {
                $option_html .= '<option value="'.$city_name.'" data-id="'.$city_id.'">'.$city_name.'</option>';
            }
        }
        $response_data = ['html' => $option_html];
        return response()->json($response_data);
    }
    public function getCities($state)
    {
        // Fetch cities based on the selected state (replace this with your logic)
        $cities = []; // You should fetch cities from your database or another source
        return response()->json($cities);
    }
    public function getCompany(Request $request){
        $appNo=$request['query'];
        $companyData=Company::where('application_no',$appNo)->first();
        if($companyData){
            echo $companyData->name;
        }else{
            echo '';
        }
    }
    public function addAddress(Request $request){
        Address::create(['address'=>$request->address]);
        $data=Address::latest()->first();
        echo '<option value="'.$data->address.'">'.$data->address.'</option>';
    }
    public function checkAddress(Request $request)
    {
        $address = $request->address;
        $check = Address::where('address', $address)->get();
        if(count($check)>0){
            return response()->json(['valid' => false]);
        } else {
            return response()->json(['valid' => true]);
        }
    }
    public function expiredApplicationNotifyEmailGenerate()
    {
        Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (1) Function Called");
        $entityApplicationData = EntityApplication::where('is_deleted','No')->where('expire_date','<=',date('Y-m-d'))->whereIn('status', [200, 203])->get();
        if(!empty($entityApplicationData))
        {
            Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1) Get application data");
            if (!$entityApplicationData->isEmpty()) {
                Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1.1) Total Records :".count($entityApplicationData));
                foreach ($entityApplicationData as $entityApplication) {
                    $entityApplication->status = 501;
                    $entityApplication->updated_at = date('Y-m-d H:i:s');
                    $entityApplication->save();
                    Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1.2) Application Id :".$entityApplication->id." status changed to expired : 501");
                }
            }
            Helper::expiredStatusChangeEmailCommonFunction($entityApplicationData);
        }
    }
    public function uploadidcardsezexceldata()
    {
        return view('users.exceldataupload');
    }
    public function uploadidcardsezexceldatapost(request $request)
    {
        Excel::import(new ImportEntityApplications,
                        $request->file('excel_file')->store('files'));
        return redirect()->back();
    }
    public function createKeyValueArray($keys, $collection){
        $dataArray = [];
        foreach ($collection as $row) {
            $data = (object) [];
            foreach ($row as $rowKeys => $rowValues) {
                $checkValue = [
                   'date_of_birth' => '','issue_date' => '','expire_date' => ''
                ];
                $key = $keys[$rowKeys];
                if (array_key_exists($key, $checkValue)) {
                    $rowValues = EntityApplication::excelUploadedDataDateFormate($rowValues);
                }
                $data->{$key} = $rowValues;
            }
            $dataArray[] = $data;
        }
        return $dataArray;
    }
    public function ddset()
    {
        $dd = EntityApplication::where('is_deleted','No')->get();
        if(!empty($dd))
        {
            foreach ($dd as $entityApplication) {
                if(empty($entityApplication->issue_date))
                {
                    $entityApplication->update([
                        'issue_date' => date('Y-m-d',strtotime($entityApplication->created_at))
                    ]);
                }
            }
        }
    }
	public function pendingApplicationsCountNotifyEmailGenerate()
    {
        $entityApplicationData = EntityApplication::where('is_deleted','No')->where('status',202)->count();
        if($entityApplicationData > 0)
        {
            $entityMail = env('SUBADMIN_EMAIL_ADDRESS');
            $mailData = array('count' => $entityApplicationData, 'mailType' => 'submitedAppliationsCountNotify');
            Mail::to($entityMail)->send(new SendMailable($mailData));
        }
    }
	public function addDepartment(Request $request){
        Department::create(['name'=>$request->department]);
        $data=Department::latest()->first();
        echo '<option value="'.$data->name.'">'.$data->name.'</option>';
    }
    public function checkDepartment(Request $request)
    {
        $department = $request->department;
        $check = Department::where('name', $department)->get();
        if(count($check)>0){
            return response()->json(['valid' => false]);
        } else {
            return response()->json(['valid' => true]);
        }
    }
    public function checkcronlog()
    {
        echo 'test';
        Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." call cronjob");
        echo 'sucess'; exit;
    }
    public function validateIdCardImportData($importData)
    {
        $errorMessage = [];

        // Check if data is not empty
        if (!empty($importData)) {
            // Define validation patterns
            $checkPattern = "/^[A-Za-z0-9_,!@#$%^&*'\/(),\[\] .?\":+=;{}|<>\\\-]+$/";
            $checkEmailPattern = "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i";
            $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

            foreach ($importData as $key => $import) {
                // Validate First Name
                if ($import->first_name == '') {
                    $errorMessage['validate'][$key][] = 'First Name is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->first_name) && !preg_match($checkPattern, $import->first_name)) {
                    $errorMessage['validate'][$key][] = 'First name must not contain special characters for serial no - ' . $import->serial_no;
                }

                // Validate Last Name
                if ($import->last_name == '') {
                    $errorMessage['validate'][$key][] = 'Last name is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->last_name) && !preg_match($checkPattern, $import->last_name)) {
                    $errorMessage['validate'][$key][] = 'Last name must not contain special characters for serial no - ' . $import->serial_no;
                }

                // Validate Company Name
                if ($import->company_name == '') {
                    $errorMessage['validate'][$key][] = 'Company name is missing for serial no - ' . $import->serial_no;
                }

                // Validate Email
                if ($import->email == '') {
                    $errorMessage['validate'][$key][] = 'Email is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->email) && !preg_match($checkEmailPattern, $import->email)) {
                    $errorMessage['validate'][$key][] = 'Email should be valid for serial no - ' . $import->serial_no;
                }

                // Validate Designation
                if ($import->designation == '') {
                    $errorMessage['validate'][$key][] = 'Designation is missing for serial no - ' . $import->serial_no;
                }

                // Validate Date of Birth
                if ($import->date_of_birth == '') {
                    $errorMessage['validate'][$key][] = 'Date of birth is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->date_of_birth) && !preg_match($datePattern, $import->date_of_birth)) {
                    $errorMessage['validate'][$key][] = 'Date of birth is not valid for serial no - ' . $import->serial_no;
                }

                // Validate Issue Date
                if ($import->issue_date == '') {
                    $errorMessage['validate'][$key][] = 'Issue date is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->issue_date) && !preg_match($datePattern, $import->issue_date)) {
                    $errorMessage['validate'][$key][] = 'Issue Date is not valid for serial no - ' . $import->serial_no;
                }

                // Validate Expire Date
                if ($import->expire_date == '') {
                    $errorMessage['validate'][$key][] = 'Expire date is missing for serial no - ' . $import->serial_no;
                } elseif (!empty($import->expire_date) && !preg_match($datePattern, $import->expire_date)) {
                    $errorMessage['validate'][$key][] = 'Expire Date is not valid for serial no - ' . $import->serial_no;
                }

                // Validate Gender
                if ($import->gender == '') {
                    $errorMessage['validate'][$key][] = 'Gender is missing for serial no - ' . $import->serial_no;
                }

                // Validate Type
                if ($import->type == '') {
                    $errorMessage['validate'][$key][] = 'Type is missing for serial no - ' . $import->serial_no;
                }

                // Validate Dial Code
                if ($import->dial_code == '') {
                    $errorMessage['validate'][$key][] = 'Dial code is missing for serial no - ' . $import->serial_no;
                }

                // Validate Mobile Number
                if ($import->mobile_number == '') {
                    $errorMessage['validate'][$key][] = 'Mobile number is missing for serial no - ' . $import->serial_no;
                }

                // Validate Country Code
                if ($import->country_code == '') {
                    $errorMessage['validate'][$key][] = 'Country code is missing for serial no - ' . $import->serial_no;
                }

                // Validate Serial Number
                if ($import->serial_no == '') {
                    $errorMessage['validate'][$key][] = 'Serial no is missing for serial no - ' . $import->serial_no;
                }

                // Validate Unit Category
                if ($import->unit_category == '') {
                    $errorMessage['validate'][$key][] = 'Unit category is missing for serial no - ' . $import->serial_no;
                }
            }
        } else {
            $errorMessage['validate']['nodetail'] = 'No record found in your uploaded file';
        }

        return $errorMessage;
    }
    public function expiredApplicationBeforeTenDaysEarlyNotifyEmail()
    {
        Log::channel('entity-application-status')->info("Time Ten Days Early Expire function called Starts: " .date("Y-m-d H:i:s") ." (1) Function Called");
        $currentDate = date('Y-m-d');
        $nextexpireDate = date('Y-m-d',strtotime($currentDate.'+ 10 days'));
        $entityApplicationData = EntityApplication::where('is_deleted','No')->where('expire_date',$nextexpireDate)->get();
        if (!$entityApplicationData->isEmpty()) {
            Log::channel('entity-application-status')->info("Time Ten Days Early Expire Data: " .date("Y-m-d H:i:s") ." (1) Function Called");
            Helper::expiredStatusChangeEmailCommonFunction($entityApplicationData);
        }
        else {
            Log::channel('entity-application-status')->info("Time Ten Days Early Expire Data not found");
            Log::channel('entity-application-status')->info("Time Ten Days Early Expire Function Ends");
        }
    }
    public function bccExpiredApplicationNotifyEmailGenerate()
    {
        Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (1) Function Called");
        $entityApplicationData = EntityApplication::where('is_deleted','No')->where('expire_date','=','2024-07-15')->where('status', 501)->limit(1)->get();
        if(!empty($entityApplicationData))
        {
            Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1) Get application data");
            Helper::bccExpiredStatusChangeEmailCommonFunction($entityApplicationData);
        }
    }
}
