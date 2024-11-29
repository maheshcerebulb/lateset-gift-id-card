<?php
namespace App\Http\Controllers;
use App\Exports\ExportBuildingCompanyApp;
use App\Models\EntityApplication;
use App\Models\Group;
use App\Models\User;
use App\Models\TemporaryEntity;
use App\Models\Company;
use App\Models\Address;
use App\Mail\SendMailable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Helper;
use Log;
use App\Models\Country;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     */
    protected $user;
    protected $group;
    protected $temporaryEntity;
    protected $entityApplication;
    public function __construct()
    {
        $this->user = new User();
        $this->group = new Group();
        $this->temporaryEntity = new TemporaryEntity();
        $this->entityApplication = new EntityApplication();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
    }
    /**
     * Ajax for validate user email address by check unique
     */
    public function validateUserEmail(Request $request){
        if($request->ajax()){
            $email = $request->email;
            $userDetail = $this->user->where('email', $email);
            if(isset($request->userId) && !empty($request->userId)){
                $userDetail->where('id', '!=', $request->userId);
            }
            $userDetail = $userDetail->first();
            if(!empty($userDetail)){
                return response()->json(['isExist' => 'Yes']);
            } else {
                return response()->json(['isExist' => 'No']);
            }
        }
    }
    public function register()
    {
        // Fetch the active country list
        $countryLists = Country::where('is_active', 'Y')->pluck('name', 'id');

        // Fetch all company addresses
        $companyAddress = Address::all();

        // Fetch distinct tower names with their IDs
        $companyTowerList = Company::whereNotNull('tower_name')
            ->where('tower_name', '!=', '')
            ->distinct()
            ->get(['id', 'tower_name']);

        // Pass data to the view
        return view('register.index', [
            'country_lists' => $countryLists,
            'company_address' => $companyAddress,
            'companyTowerList' => $companyTowerList,
        ]);
    }
    public function registerSuccess(Request $request)
    {
        try {
            if ($request->user_id > 0) {
                $userDetails = $this->user->find($request->user_id);

                if (!empty($userDetails)) {
                    return view('register.success', ['user_details' => $userDetails]);
                }
            }

            // Redirect to register page with error message for invalid or missing user details
            return redirect('register')->with('error', Config::get('constant.COMMON_TECHNICAL_MESSAGE'));
        } catch (\Exception $ex) {
            return redirect('register')->with('error', Config::get('constant.COMMON_TECHNICAL_MESSAGE'));
        }
    }
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * function for login view
     */
    public function login(){
        return view('users.login');
    }
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     * Function for create login
     */
    public function validateLogin(Request $request)
    {
        if ($request->isMethod('post')) {
            $credentials = [
                'email' => $request->email,
                'password' => $request->password,
                'is_active' => 'Y',
                'is_deleted' => 'N',
            ];

            if (Auth::attempt($credentials)) {
                $userDetail = $this->user->where('email', $request->email)->first();

                // Write user session details
                Session::put('User.id', $userDetail->id);
                Session::put('User.name', $userDetail->name ?? $userDetail->company_name ?? '');
                Session::put('User.email', $userDetail->email);
                Session::put('User.userGroup', $userDetail->group_id);

                $redirectOn = ($userDetail->group_id == 3 && $userDetail->first_time_login == 'Y')
                    ? url('/users/changePassword?redirect=welcome')
                    : url('/welcome');

                return response()->json([
                    'result' => true,
                    'isValid' => 'Yes',
                    'redirectOn' => $redirectOn,
                ]);
            }

            // Check if the user exists
            $userDetail = DB::table('users')->where('email', $request->email)->first();

            if (!empty($userDetail)) {
                $message = $userDetail->is_active != 'Y'
                    ? 'Your account is inactive.'
                    : 'Incorrect password. Please try again.';
            } else {
                $message = 'Incorrect username. Please try again.';
            }

            return response()->json([
                'result' => false,
                'isValid' => 'No',
                'message' => $message,
            ]);
        }
    }

    public function profile(){
        $userDetails = Auth::user();
        $userDetails = $userDetails->toArray();
        return view('users.profile', compact('userDetails'));
    }
    public function changePassword(Request $request)
    {
        try {
            $userDetails = Auth::user();
            $redirectOn = $request->get('redirect', '');

            if ($request->isMethod('post')) {
                // Define validation rules
                $rules = [];
                if (!empty($request->password)) {
                    $rules['old_password'] = 'required|current_password';
                    $rules['password'] = 'required|min:6|confirmed';
                }

                // Validate request data
                $validator = Validator::make($request->all(), $rules);
                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator);
                }

                // Update password
                if (!empty($request->password)) {
                    $userDetails->first_time_login = 'N';
                    $userDetails->base_password = base64_encode($request->password);
                    $userDetails->password = bcrypt($request->password);
                    $userDetails->save();
                }

                // Redirect based on condition
                if (!empty($redirectOn)) {
                    return redirect('/');
                }

                return redirect()->back()->with('success', 'Your password has been updated');
            }

            return view('users.change-password', compact('userDetails', 'redirectOn'));
        } catch (\Exception $e) {
            return redirect('welcome')->with('error', 'An error occurred. Please try again.');
        }
    }
     /**
     * Welcome page
     *
     * @return \Illuminate\Http\Response
     */
    public function welcome()
    {
        try {
            $userId = Auth::id();
            $userDetails = User::findOrFail($userId);
            $role = $userDetails->getRoleNames()->first();

            // Common Data
            $recentEntityApplicationData = $this->entityApplication;
            $filterBuildingData = EntityApplication::getBuildingList();
            $filterCompanyData = EntityApplication::getCompanyList('0');
            $yearsList = EntityApplication::yearList();

            // Role-specific data and redirections
            switch ($role) {
                case 'Admin':
                    $recentEntityApplicationData = $recentEntityApplicationData::getTableDataWithEntity();
                    return view('users.dashboard-admin', compact(
                        'userDetails',
                        'recentEntityApplicationData',
                        'filterBuildingData',
                        'filterCompanyData',
                        'yearsList'
                    ));

                case 'Super Admin':
                    $addressData = Address::where('status', 1)->get();
                    $buildingList = Address::where('status', 1)->pluck('address');
                    $companyList = Company::pluck('name');
                    return view('users.dashboard-su-admin', compact(
                        'userDetails',
                        'addressData',
                        'companyList',
                        'buildingList',
                        'filterBuildingData',
                        'filterCompanyData',
                        'yearsList'
                    ));

                case 'Data Entry':
                    $addressData = Address::where('status', 1)->get();
                    return view('users.dashboard-data-entry-admin', compact('userDetails', 'addressData'));

                case 'Sub Admin':
                    $recentEntityApplicationData = $recentEntityApplicationData::getTableDataWithEntity();
                    $addressData = Address::where('status', 1)->get();
                    return view('users.dashboard-admin', compact(
                        'userDetails',
                        'recentEntityApplicationData',
                        'filterBuildingData',
                        'filterCompanyData',
                        'yearsList'
                    ));

                default: // Entity Role
                    $recentEntityApplicationData = $recentEntityApplicationData::where('user_id', $userId)
                        ->latest()
                        ->take(5)
                        ->get();

                    if ($userDetails->first_time_login == 'Y') {
                        return redirect()->route('users.change-password');
                    }

                    return view('users.dashboard-entity', compact('userDetails', 'recentEntityApplicationData'));
            }
        } catch (\Exception $e) {
            // Handle unexpected exceptions gracefully
            return redirect()->route('login')->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }

    public function dashboard() {
        return view('users.dashboard-admin', compact('userDetails'));
    }
    public function updateEntityProfile(Request $request)
    {
        try {
            $errorMessages = [];
            $userDetailQuery = $this->user->where('email', $request->email);

            if (!empty($request->id)) {
                $userDetailQuery->where('id', '!=', $request->id);
            }

            $userDetail = $userDetailQuery->first();

            if (!empty($userDetail)) {
                $errorMessages[] = 'Entity is already registered with the provided email address.';
            }

            if (empty($errorMessages)) {
                $inputData = $request->except(['_token', 'id', 'created_at', 'updated_at']);

                $this->user->where('id', $request->id)->update($inputData);

                return redirect()->back()->with('success', 'Your profile has been updated.');
            }
            return redirect()->back()
                ->withInput($request->all())
                ->with('error', implode('<br>', $errorMessages));
        } catch (\Exception $ex) {
            return redirect()->back()
                ->withInput($request->all())
                ->with('error', 'An error occurred while updating the profile: ' . $ex->getMessage());
        }
    }

    public function dashboardHtml()
    {
        try {
            return view('users.welcome-html');
        } catch (\Exception $e) {
            return redirect('login');
        }
    }
    /**
     * user logout action.
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request){
        Auth::logout();
        $request->session()->forget('User');
        return redirect('login');
    }
    /**
     * Forgot password action
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function forgotPassword(Request $request)
    {
        $response = ['result' => false, 'message' => 'Invalid request'];
        try {
            if ($request->isMethod('post')) {
                // Check if user exists with the given email
                $userDetail = DB::table('users')->where('email', $request->email)->first();

                if (!empty($userDetail)) {
                    if ($userDetail->is_active == 'Yes') {
                        // Generate a new random password
                        $newPassword = substr(md5(rand() . rand()), 0, 8);

                        // Update the user's password
                        $updateData = ['password' => bcrypt($newPassword)];
                        $updateUserQuery = User::find($userDetail->id);
                        $updateUserQuery->update($updateData);

                        // Prepare mail data
                        $mailData = [
                            'email' => $userDetail->email,
                            'password' => $newPassword,
                            'mailType' => 'forgotPassword'
                        ];

                        // Send email with the new password
                        Mail::to($userDetail->email)->send(new SendMailable($mailData));

                        $response = [
                            'result' => true,
                            'message' => 'Password has been sent to your email address'
                        ];
                    } else {
                        $response = [
                            'result' => false,
                            'message' => 'Your account has been deactivated by admin'
                        ];
                    }
                } else {
                    $response = [
                        'result' => false,
                        'message' => 'Email address does not exist'
                    ];
                }
            }
        } catch (\Exception $ex) {
            // Handle any exceptions
            $response = [
                'result' => false,
                'message' => $ex->getMessage()
            ];
        }
        return response()->json($response);
    }

    public function checkEntityEmailUnique(Request $request)
    {
        $email_address = $request->email;
        $userDetail = User::where('is_deleted', 'N')->where('email', $email_address);
        if(isset($request->userId) && !empty($request->userId)){
            $userDetail->where('id', '!=', $request->userId);
        }
        $userDetails = $userDetail->first();
        if(!empty($userDetails)){
            return response()->json(['valid' => false]);
        } else {
            return response()->json(['valid' => true]);
        }
    }
    public function saveBasicEntityDetail(Request $request)
    {
        try {
            $input = $request->all();

            // If a request_id is provided, fetch the tower_name from Company model and assign it
            if ($request->request_id) {
                $towerName = Company::where('application_no', $request->request_id)->value('tower_name');
                if ($towerName) {
                    $input['company_building'] = strtoupper($towerName);
                }
            }

            // Log the request data for debugging purposes
            Log::channel("entity-registration")->info(
                "Time: " . now() . " Request Data : " . json_encode($request->all()) . "\n\n"
            );

            // If an ID is provided, update the existing record
            if ($request->id > 0) {
                $temporaryEntityDetail = $this->temporaryEntity->find($request->id);

                if (!$temporaryEntityDetail) {
                    return response()->json([
                        'result' => false,
                        'message' => 'There is some problem. Please try again!',
                        'isPageRefresh' => true
                    ]);
                }

                // Prepare the data for update
                $updateData = $this->prepareUpdateData($input);

                // Update the temporary entity record
                $temporaryEntityDetail->update($updateData);

                return response()->json([
                    'result' => true,
                    'message' => 'Entity details have been updated successfully'
                ]);
            }

            // For new entity, create a new record
            $input = $this->prepareCreateData($input);
            $tempRegisterEntityDetail = $this->temporaryEntity->create($input);

            // Log response data after creation
            Log::channel("entity-registration")->info(
                "Time: " . now() . " Request Data : " . json_encode($request->all()) . "/n/n/Response Data:" . json_encode($tempRegisterEntityDetail) . "/n"
            );

            return response()->json([
                'result' => true,
                'message' => 'Entity details have been added successfully',
                'entityId' => $tempRegisterEntityDetail->id
            ]);

        } catch (\Exception $ex) {
            // Log the exception error
            Log::channel("entity-registration")->info(
                "Time: " . now() . " Request Data : " . json_encode($request) . "\n\n Response Data:" . $ex->getMessage() . "\n"
            );

            return response()->json([
                'result' => false,
                'message' => $ex->getMessage()
            ]);
        }
    }

    public function saveEntityAuthorizedPersonDetail(Request $request)
    {
        try {
            $input = $request->all();

            // Validate if the entity ID is provided
            if ($request->id > 0) {
                // Fetch the temporary entity record
                $temporaryEntityDetail = $this->temporaryEntity->find($request->id);

                if (!$temporaryEntityDetail) {
                    return response()->json([
                        'result' => false,
                        'message' => 'There is some problem. Please try again!',
                        'redirectPage' => route('users.register')
                    ]);
                }

                // Prepare the data for update by cleaning unnecessary fields
                $updateData = $this->prepareAuthorizedPersonUpdateData($input);

                // Update the temporary entity record with the new details
                $temporaryEntityDetail->update($updateData);

                return response()->json([
                    'result' => true,
                    'message' => 'Authorized person\'s details have been added successfully'
                ]);
            }

            return response()->json([
                'result' => false,
                'message' => 'Entity details not saved. Please save it first'
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'result' => false,
                'message' => $ex->getMessage()
            ]);
        }
    }

    /**
     * Prepare the data for updating the authorized person's details.
     *
     * @param array $input
     * @return array
     */
    private function prepareAuthorizedPersonUpdateData(array $input)
    {
        // Remove unnecessary fields before updating
        unset($input['_token'], $input['id'], $input['is_entity_authorized_person_detail_valid'],
            $input['entity_authorized_person_support_document_hidden'],
            $input['entity_authorized_person_signature_hidden']);

        return $input;
    }

    public function uploadEntityAuthorizedPersonSupportDocument(Request $request)
    {
        try {
            // Get temp entity id from the request
            $tempEntityId = $request->temp_entity_id;

            // Define the file upload path
            $fileDir = config('constant.ENTITY_DOCUMENT_TEMP_PATH');
            $filePath = public_path('upload') . DIRECTORY_SEPARATOR . $fileDir;

            // Check if the file exists in the request
            if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
                return response()->json(['message' => 'Invalid file or no file uploaded.', 'status' => 'error'], 422);
            }

            // Get the uploaded file
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension();
            $fileName = $tempEntityId . '-support-document.' . $ext;

            // Check if file already exists and delete it
            $existingFilePath = $filePath . $fileName;
            if (file_exists($existingFilePath)) {
                unlink($existingFilePath);
            }

            // Move the file to the target directory
            if ($file->move($filePath, $fileName)) {
                // Update the entity record with the file name
                $this->temporaryEntity->where('id', $tempEntityId)->update(['authorized_person_support_document' => $fileName]);

                return response()->json(['result' => true, 'file_name' => $fileName]);
            }

            return response()->json(['message' => 'There was an issue uploading the file. Please try again!', 'status' => 'error'], 422);
        } catch (\Illuminate\Validation\ValidationException $validationException) {
            // Capture validation errors and return them
            $errors = $validationException->validator->errors();
            $errorMessage = implode('<br>', $errors->all());
            return response()->json(['message' => $errorMessage, 'status' => 'error'], 422);
        } catch (\Exception $exception) {
            // General error handling
            return response()->json(['message' => $exception->getMessage(), 'status' => 'error'], 500);
        }
    }

    public function removeEntityAuthorizedPersonSupportDocument(Request $request)
    {
        try {
            $temp_entity_id = $request->temp_entity_id;
            $added_file_name = $request->file_name;
            if(!empty($added_file_name))
            {
                $fileDir = config('constant.ENTITY_DOCUMENT_TEMP_PATH');
                $filePath = public_path('upload').DIRECTORY_SEPARATOR.$fileDir;
                if(file_exists($filePath.$added_file_name))
                {
                    $this->temporaryEntity->where('id', $temp_entity_id)->update(['authorized_person_support_document' => '']);
                    unlink($filePath.$added_file_name);
                    return response()->json(['result' => true]);
                }
                else
                {
                    return response()->json(['result' => false, 'message' => 'File not found.']);
                }
            }
            else
            {
                return response()->json(['result' => false, 'message' => 'File not found.']);
            }
        }
        catch (\Exception $exception) {
            // Handle other exceptions, if any
            return response()->json(['result' => false, 'message' => 'There is some problem. Please try again!']);
        }
    }
    public function uploadEntityAuthorizedPersonSignature(Request $request)
    {
        try {
            $temp_entity_id = $request->temp_entity_id;
            $fileDir = config('constant.ENTITY_DOCUMENT_TEMP_PATH');
            $filePath = public_path('upload').DIRECTORY_SEPARATOR.$fileDir;
            $tempFile = $_FILES['file']['tmp_name'][0];
            $ext = pathinfo($_FILES['file']['name'][0], PATHINFO_EXTENSION);
            $fileName = $temp_entity_id.'-signature'.'.'.$ext;
            if(file_exists($filePath.$fileName))
            {
                unlink($filePath.$fileName);
            }
            if(move_uploaded_file($tempFile, $filePath.$fileName))
            {
                $this->temporaryEntity->where('id', $temp_entity_id)->update(['authorized_person_signature' => $fileName]);
                return response()->json(['result' => true, 'file_name' => $fileName]);
            }
            else
            {
                return response()->json(['message' => 'There is some issue in file upload. Please try again!', 'status' => 'error'], 422);
            }
        }
        catch (\Illuminate\Validation\ValidationException $validationException) {
            $errors = $validationException->validator->errors();
            $errorMessage = implode('<br>', $errors->all());
            return response()->json(['message' => $errorMessage, 'status' => 'error'], 422);
        }
        catch (\Exception $exception) {
            // Handle other exceptions, if any
            $message = $exception->getMessage();
            //$message = 'There is some problem. Please try again!';
            return response()->json(['message' => $message, 'status' => 'error'], 500);
        }
    }
    public function removeEntityAuthorizedPersonSignature(Request $request)
    {
        try {
            $temp_entity_id = $request->temp_entity_id;
            $added_file_name = $request->file_name;
            if(!empty($added_file_name))
            {
                $fileDir = config('constant.ENTITY_DOCUMENT_TEMP_PATH');
                $filePath = public_path('upload').DIRECTORY_SEPARATOR.$fileDir;
                if(file_exists($filePath.$added_file_name))
                {
                    $this->temporaryEntity->where('id', $temp_entity_id)->update(['authorized_person_signature' => '']);
                    unlink($filePath.$added_file_name);
                    return response()->json(['result' => true]);
                }
                else
                {
                    return response()->json(['result' => false, 'message' => 'File not found.']);
                }
            }
            else
            {
                return response()->json(['result' => false, 'message' => 'File not found.']);
            }
        }
        catch (\Exception $exception) {
            // Handle other exceptions, if any
            return response()->json(['result' => false, 'message' => 'There is some problem. Please try again!']);
        }
    }
    public function getEntityDetailForFinalStepOnRegister(Request $request)
    {
        try {
            $input = $request->all();
            if($request->id > 0){
                $temporaryEntityDetail = $this->temporaryEntity->find($request->id);
                if (empty($temporaryEntityDetail)) {
                    return response()->json(['result' => false, 'message' => 'There is some problem. Please try again!', 'redirectPage' => route('users.register')]);
                } else {
                    $authorized_signatory_name = $temporaryEntityDetail->authorized_person_first_name;
                    if(!empty($temporaryEntityDetail->authorized_person_last_name))
                    {
                        $authorized_signatory_name  .= " ".$temporaryEntityDetail->authorized_person_last_name;
                    }
                    return response()->json([
                        'result' => true,
                        'authorized_signatory_name' => $authorized_signatory_name,
                        'place' => $temporaryEntityDetail->company_city,
                        'authorized_person_designation' => $temporaryEntityDetail->authorized_person_designation,
                    ]);
                }
            } else {
                return response()->json(['result' => false, 'message' => 'Entity details not saved. Please save it first']);
            }
        } catch (\Exception $ex) {
            return response(['result' => false, 'message' => $ex->getMessage()]);
        }
    }
    public function saveEntityVerifyAndSubmitDetail(Request $request)
    {
        try {
            $input = $request->all();
            if($request->id > 0){
                $temporaryEntityDetail = $this->temporaryEntity->find($request->id);
                if (empty($temporaryEntityDetail)) {
                    return response()->json(['result' => false, 'message' => 'There is some problem. Please try again!', 'redirectPage' => route('users.register')]);
                } else {
                    $error_message = array();
                    $companyData=Company::where('application_no',$temporaryEntityDetail->request_id)->first();
                    if ($temporaryEntityDetail->unit_category != 'Other' && empty($companyData)) {
                        $error_message[] = 'The Application No (LOA) is not valid.';
                    }
                    # Perform basic validation
                    $userDetail = $this->user->where('email', $temporaryEntityDetail->email);
                    if(isset($request->userId) && !empty($request->userId)){
                        $userDetail->where('id', '!=', $request->userId);
                    }
                    $userDetail = $userDetail->first();
                    if(!empty($userDetail)){
                       $error_message[] = 'Entity is already registered with added email address.';
                    }
                    if(empty($error_message))
                    {
                        $save_data = array();
                        $not_include_column = array('id', 'created_at', 'updated_at');
                        $temporaryEntityDetail = $temporaryEntityDetail->toArray();
                        foreach($temporaryEntityDetail as $column_name => $column_data)
                        {
                            if($column_name == 'email') {
                                $company_email = $column_data;
                            }
                            if(!in_array($column_name, $not_include_column)) {
                                $save_data[$column_name] = $column_data;
                            }
                        }
                        // $password           = $this->generateRandomPassword(8); // 13-09-2024
                        $password           = $temporaryEntityDetail['authorized_person_first_name'].'!@#$%^';
                        $application_number = $this->generateApplicationNumber();
                        $save_data['password'] = bcrypt($password);
                        $save_data['application_number']    = $application_number;
                        $save_data['first_time_login']      = 'Y';
                        $save_data['company_registration_number']      = $save_data['registration_number'];
						$save_data['request_number']      = $save_data['request_id'];
                        $userDetail = $this->user->create($save_data);
                        $role = Role::where(['name' => 'Entity'])->first();
                        $userDetail->assignRole([$role->id]);
                        # Send login data
                        $userDetail->normal_password = $password;
                        # Send email
                        $emailData['email']=$userDetail->email;
                        $emailData['data']=$userDetail;
                        $emailData['viewFile']='emails.register';
                        $emailData['mailType']='register';
                        $emailData['subject']='Entity Registration Confirmation';
                        $emailData['errorLogChannel'] = 'entity-registeration';
                        Helper::sendMail($emailData);
                        return response()->json(['result' => true, 'message' => 'Entity details have been saved successfully', 'user_id' => $userDetail->id]);
                    }
                    else
                    {
                        return response()->json(['result' => false, 'message' => implode("<br><br>", $error_message)]);
                    }
                }
            } else {
                return response()->json(['result' => false, 'message' => 'Entity details not saved. Please save it first']);
            }
        } catch (\Exception $ex) {
            return response(['result' => false, 'message' => $ex->getMessage()]);
        }
    }
    function generateApplicationNumber() {
        $randomNumber = mt_rand(1000000, 9999999);
        $exists = $this->user->where('application_number', $randomNumber)->exists();
        if ($exists) {
            return $this->generateUniqueRandomNumber();
        }
        // If the number is unique, return it
        return $randomNumber;
    }
    function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    function buildingUnitListing($address)
    {
        $unitAddress = $address;
        // dd($unitsdata);
        $BuildingAddress = User::distinct('company_address')->pluck('company_address');
        return view('entity.admin-entity-view',compact('BuildingAddress','unitAddress'));
    }
    function buildingEmployeeListing($address)
    {
        $unitAddress = $address;
        $companiesdata = User::where('company_address',$address)->get();
        $companyIds = $companiesdata->pluck('id');
        $employeesdata = EntityApplication::whereIn('user_id', $companyIds)->where('entity_applications.is_deleted','No')->pluck('id');
        if ($employeesdata->isEmpty()) {
            // If it's empty, manually add 0 to the collection
            $employeesdata = collect([0]);
        }
        $applicationTypes = EntityApplication::select('type')
        ->distinct('type')
        ->pluck('type');
        return view('users.super-admin-employees-list',compact('employeesdata','applicationTypes'));
    }
    function buildingActiveCardListing($address)
    {
        // dd($address);
        $unitAddress = $address;
        $companiesdata = User::where('company_address',$address)->get();
        $companyIds = $companiesdata->pluck('id');
        $employeesdata = EntityApplication::whereIn('user_id', $companyIds)->whereBetween('status',[200,210])->where('entity_applications.is_deleted','No')->pluck('id');
        if ($employeesdata->isEmpty()) {
            // If it's empty, manually add 0 to the collection
            $employeesdata = collect([0]);
        }
        // dd($employeesdata);
        return view('users.super-admin-active-list',compact('employeesdata'));
    }
    function buildingInActiveCardListing($address)
    {
        $unitAddress = $address;
        $companiesdata = User::where('company_address',$address)->get();
        $companyIds = $companiesdata->pluck('id');
        // dd($companyIds);
        $employeesdata = EntityApplication::WhereIn('user_id', $companyIds)->where('status',[501,502,401])->where('entity_applications.is_deleted','No')->pluck('id');
        if ($employeesdata->isEmpty()) {
            // If it's empty, manually add 0 to the collection
            $employeesdata = collect([0]);
        }
        return view('users.super-admin-inactive-list',compact('employeesdata'));
    }
    function fetchUnitList(request $request)
    {
        if(request()->ajax()) {
            $data=User::where('company_address',$request->building)->distinct()->select('unit_category','company_name',DB::raw("CONCAT(authorized_person_first_name,' ',authorized_person_last_name) AS authorized_person_name"),'application_number','authorized_person_mobile_number','email','created_at');
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query) {
                if (request()->has('search')) {
                    $searchTerm = request()->get('search')['value'];
                    $query->where(function ($subQuery) use ($searchTerm) {
                        foreach ($subQuery->getModel()->getFillable() as $column) {
                            if($column == 'authorized_person_first_name')
                            {
                                $subQuery->orWhereRaw("CONCAT(authorized_person_first_name, ' ', authorized_person_last_name) LIKE ?", ["%{$searchTerm}%"]);
                            }
                            else
                            {
                                $subQuery->orWhere($column, 'like', "%{$searchTerm}%");
                            }
                        }
                    });
                }
            });
            return $dataTable->addColumn('unit_category', function($row){
                return $row->unit_category;
            })
            ->addColumn('created_at', function($row){
                return date('d-m-Y',strtotime($row->created_at));
            })
            ->addColumn('company_name', function($row){
                return $row->company_name;
            })
            ->addColumn('authorized_person_name', function($row){
                return $row->authorized_person_name;
            })
            ->addColumn('application_number', function($row){
                return $row->application_number;
            })
            ->addColumn('email', function($row){
                return $row->email;
            })
            ->addColumn('authorized_person_mobile_number', function($row){
                return $row->authorized_person_mobile_number;
            })
            ->addColumn('action', function($row){
                // <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Application</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                $btn = '<div class="d-flex align-items-center">';
                $btn .=  '<a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="'.$row->id.'">
                        <i class="fas fa-eye fa-1x text-green"></i>
                    </a>';
                $btn.= '</div>';
                return $btn;
            })->rawColumns(['created_at','status','action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function fetchEmployeesList(Request $request)
    {
        if ($request->ajax()) {
            $query = EntityApplication::select(
                'entity_applications.first_name',
                'entity_applications.last_name',
                'entity_applications.serial_no',
                'entity_applications.application_type',
                'entity_applications.issue_date',
                'entity_applications.expire_date',
                'entity_applications.status',
                'users.company_name',
                'entity_applications.email',
                'entity_applications.type',
                'entity_applications.final_special_serial_no'
            )
            ->leftJoin('users', 'users.id', '=', 'entity_applications.user_id')
            ->whereIn('entity_applications.id', $request->emplyeesIds)
            ->where('entity_applications.is_deleted', 'No')
            ->orderByDesc('entity_applications.serial_no');

            // Apply status filter if specified
            if ($request->has('columns')) {
                $columns = $request->get('columns');
                if (isset($columns[5]['search']['value'])) {
                    $statusFilter = $this->getVal($columns[5]['search']['value']);
                    $query->where('entity_applications.type', $statusFilter);
                }
            }

            // Global search filter
            if ($request->has('search') && $searchTerm = $request->get('search')['value']) {
                $query->where(function ($query) use ($searchTerm) {
                    $query->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                        ->orWhere('users.company_name', 'like', "%{$searchTerm}%")
                        ->orWhere('entity_applications.application_type', 'like', "%{$searchTerm}%")
                        ->orWhere('entity_applications.serial_no', 'like', "%{$searchTerm}%")
                        ->orWhere('entity_applications.email', 'like', "%{$searchTerm}%");
                });
            }

            $dataTable = datatables()->eloquent($query)
                ->addColumn('application_type', function($row) {
                    return $this->getApplicationTypeLabel($row->application_type);
                })
                ->addColumn('issue_date', function($row) {
                    return $row->issue_date ? date('d-m-Y', strtotime($row->issue_date)) : '';
                })
                ->addColumn('expire_date', function($row) {
                    return $row->type === 'Other' ? '' : ($row->expire_date ? date('d-m-Y', strtotime($row->expire_date)) : '');
                })
                ->addColumn('serial_no', function($row) {
                    return $row->serial_no ?: $row->final_special_serial_no;
                })
                ->addColumn('name', function($row) {
                    return $row->first_name . ' ' . $row->last_name;
                })
                ->addColumn('status', function($row) {
                    return '<span class="label label-lg label-inline ' . Helper::getApplicationStatusBackgroundColor($row->status) . '">' . Helper::getApplicationType($row->status) . '</span>';
                })
                ->addColumn('action', function($row) {
                    return '<a href="#" class="btn btn-primary btn-sm viewApplication" data-id="' . $row->id . '"><i class="fas fa-eye text-green"></i></a>';
                })
                ->addIndexColumn()
                ->rawColumns(['status', 'action'])
                ->make(true);

            return $dataTable;
        }

        return view('users.super-admin-employees-list');
    }

    private function getApplicationTypeLabel($type)
    {
        switch ($type) {
            case 0:
                return 'New';
            case 1:
                return 'Renew';
            case 2:
                return 'Surrender';
            default:
                return '';
        }
    }
    function fetchActiveList(request $request)
    {
        if(request()->ajax()) {
            $data=EntityApplication::select('entity_applications.first_name','entity_applications.last_name','entity_applications.serial_no','entity_applications.application_type','entity_applications.issue_date','entity_applications.expire_date','entity_applications.status','users.company_name as company_name','users.email','entity_applications.final_special_serial_no','entity_applications.type')
                                    ->leftJoin('users','users.id','=','entity_applications.user_id')
                                    // ->where('user.company_address',$request->building)
                                    ->where('entity_applications.is_deleted','No')
                                    ->whereIn('entity_applications.id',$request->activeIds);
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query) {
                if (request()->has('search')) {
                    $searchTerm = request()->get('search')['value'];
                    $query->where(function ($subQuery) use ($searchTerm) {
                        foreach ($subQuery->getModel()->getFillable() as $column) {
                           if ($column === 'first_name' || $column === 'last_name') {
                                $subQuery->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                            }
                            elseif ($column == 'user_id') {
                                $subQuery->orwhere('users.company_name', 'like', "%{$searchTerm}%");
                            } elseif ($column === 'type') {
                                $subQuery->orWhere(function ($typeQuery) use ($searchTerm) {
                                    if ($searchTerm == 'New') {
                                        $typeQuery->where('entity_applications.type', 0);
                                    } elseif ($searchTerm == 'Renew') {
                                        $typeQuery->where('entity_applications.type', 1);
                                    } elseif ($searchTerm == 'Surrender') {
                                        $typeQuery->where('entity_applications.type', 2);
                                    }
                                });
                            }
                            else {
                                $subQuery->orWhere('entity_applications.'.$column, 'like', "%{$searchTerm}%");
                            }
                        }
                    });
                }
            });
            return $dataTable->addColumn('user.company_name', function($row){
                return $row->company_name;
            })
            ->addColumn('application_type', function($row){
                $this->getApplicationTypeLabel($row->application_type);
            })
            ->addColumn('issue_date', function($row){
                return date('d-m-Y',strtotime($row->issue_date));
            })
            ->addColumn('expire_date', function($row){
                if ($row->type == 'Other') {
                    return '';
                }
                return date('d-m-Y',strtotime($row->expire_date));
            })
            ->addColumn('serial_no', function($row){
                if (empty($row->serial_no)) {
                    $row->serial_no = $row->final_special_serial_no;
                }
                return $row->serial_no;
            })
            ->addColumn('name', function($row){
                return $row->fullname;
            })
            ->addColumn('status', function($row){
                return '<span class="label label-lg label-inline ' . Helper::getApplicationStatusBackgroundColor($row->status) . '">' . Helper::getApplicationType($row->status) . '</span>';
            })
            ->addColumn('action', function($row){
                // <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Application</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                $btn = '<div class="d-flex align-items-center">';
                $btn .=  '<a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="'.$row->id.'">
                        <i class="fas fa-eye fa-1x text-green"></i>
                    </a>';
                $btn.= '</div>';
                return $btn;
            })->rawColumns(['status','action'])
            ->addIndexColumn()
            ->make(true);
        }
    }
    function fetchInActiveList(request $request)
    {
        if(request()->ajax()) {
            $data=EntityApplication::select('entity_applications.first_name','entity_applications.last_name','entity_applications.serial_no','entity_applications.application_type','entity_applications.issue_date','entity_applications.expire_date','entity_applications.status','users.company_name as company_name','users.email','entity_applications.final_special_serial_no','entity_applications.type')
                                    ->leftJoin('users','users.id','=','entity_applications.user_id')
                                    // ->where('user.company_address',$request->building)
                                    ->where('entity_applications.is_deleted','No')
                                    ->whereIn('entity_applications.id',$request->inactiveIds);
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query) {
                if (request()->has('search')) {
                    $searchTerm = request()->get('search')['value'];
                    $query->where(function ($subQuery) use ($searchTerm) {
                        foreach ($subQuery->getModel()->getFillable() as $column) {
                            if ($column === 'first_name' || $column === 'last_name') {
                                $subQuery->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                            }
                            elseif ($column == 'user_id') {
                                $subQuery->orwhere('users.company_name', 'like', "%{$searchTerm}%");
                            }
                            elseif ($column === 'type') {
                                $subQuery->orWhere(function ($typeQuery) use ($searchTerm) {
                                    if ($searchTerm == 'New') {
                                        $typeQuery->where('entity_applications.type', 0);
                                    } elseif ($searchTerm == 'Renew') {
                                        $typeQuery->where('entity_applications.type', 1);
                                    } elseif ($searchTerm == 'Surrender') {
                                        $typeQuery->where('entity_applications.type', 2);
                                    }
                                });
                            }
                            else {
                                $subQuery->orWhere('entity_applications.'.$column, 'like', "%{$searchTerm}%");
                            }
                        }
                    });
                }
            });
            return $dataTable->addColumn('user.company_name', function($row){
                return $row->company_name;
            })
            ->addColumn('application_type', function($row){
                $this->getApplicationTypeLabel($row->application_type);
            })
            ->addColumn('issue_date', function($row){
                return date('d-m-Y',strtotime($row->issue_date));
            })
            ->addColumn('expire_date', function($row){
                if ($row->type == 'Other') {
                    return '';
                }
                return date('d-m-Y',strtotime($row->expire_date));
            })
            ->addColumn('serial_no', function($row){
                if (empty($row->serial_no)) {
                    $row->serial_no = $row->final_special_serial_no;
                }
                return $row->serial_no;
            })
            ->addColumn('name', function($row){
                return $row->fullname;
            })
            ->addColumn('status', function($row){
                return '<span class="label label-lg label-inline ' . Helper::getApplicationStatusBackgroundColor($row->status) . '">' . Helper::getApplicationType($row->status) . '</span>';
            })
            ->addColumn('action', function($row){
                // <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Application</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                $btn = '<div class="d-flex align-items-center">';
                $btn .=  '<a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="'.$row->id.'">
                        <i class="fas fa-eye fa-1x text-green"></i>
                    </a>';
                $btn.= '</div>';
                return $btn;
            })->rawColumns(['status','action'])
            ->addIndexColumn()
            ->make(true);
        }
    }

    public function resendUserIdPasswordEmail($entityId)
    {
        $entityId = (int) $entityId;
       try {
        $userDetail = User::findOrFail($entityId);
        // dd($userDetail);
        $password           = $this->generateRandomPassword(8);
        $mailData = array('email' => $userDetail->email, 'password' => $password, 'mailType' => 'register');
        $userDetail->password = bcrypt($password);
        $userDetail->save();
            // # Send email
            Mail::to($userDetail->email)->send(new SendMailable($mailData));
       } catch (\Throwable $th) {
            echo $th;
       }
    }

    public function getVal($filterVar){
        $filterVar=str_replace('$','',$filterVar);
        $filterVar = str_replace('^', '', $filterVar);
        return $filterVar;
    }

    public function getBaseCompanyList(Request $request){
        $filterBaseCompanyData = EntityApplication::filterBaseCompanyData($request->filter);
            // Return response
            return response()->json([
                'success' => true,
                'companies' => $filterBaseCompanyData
            ]);
    }
    public function buildingCompaniesApplicationsDataExport(request $request)
    {
        $filter_report = ($request->filter_report) ? $request->filter_report : 'entity';
        $filter_building = ($request->filter_building) ? $request->filter_building: '';
        $filter_company = ($request->filter_company) ? $request->filter_company: '';
        $gender = ($request->filter_gender)? $request->filter_gender: '';
        $type = ($request->filter_type) ? $request->filter_type: '';
        $ageFilter = ($request->filter_age_group) ? $request->filter_age_group: '0';
        $year = ($request->filter_year) ? $request->filter_year: '';

        if($filter_report == 'Id-cards'){
            $data = EntityApplication::select(
                'entity_applications.serial_no',
                'user.company_address',
                'user.company_name',
                'user.company_building',
                'entity_applications.first_name',
                'entity_applications.last_name',
                DB::raw('CASE
                    WHEN entity_applications.status = 200 THEN "Approved"
                    WHEN entity_applications.status = 201 THEN "Draft"
                    WHEN entity_applications.status = 202 THEN "Submited"
                    WHEN entity_applications.status = 500 THEN "Rejected"
                    WHEN entity_applications.status = 501 THEN "Expired"
                    WHEN entity_applications.status = 401 THEN "Surrendered"
                    WHEN entity_applications.status = 502 THEN "Deactivated"
                    WHEN entity_applications.status = 203 THEN "Activated"
                    WHEN entity_applications.status = 204 THEN "Verified"
                    WHEN entity_applications.status = 500 THEN "Rejected"
                    WHEN entity_applications.status = 205 THEN "Send Back"
                    WHEN entity_applications.status = 206 THEN "Hard copy submitted"
                    WHEN entity_applications.status = 255 THEN "Terminated"
                    ELSE "Undefined"
                    END AS application_status'),
                    'entity_applications.type',
                    'entity_applications.issue_date',
                    'entity_applications.expire_date',
                    'entity_applications.final_special_serial_no',
                    'entity_applications.gender',
                    'entity_applications.date_of_birth',
                    'user.unit_category'
                )
                ->leftJoin('users as user', 'user.id', '=', 'entity_applications.user_id')
                ->where('entity_applications.is_deleted', 'No');

            $dataCount = EntityApplication::select(
                DB::raw('SUM(CASE WHEN entity_applications.status != 201 THEN 1 ELSE 0 END) AS total_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 203 THEN 1 ELSE 0 END) AS total_activated_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 200 OR entity_applications.status = 203 THEN 1 ELSE 0 END) AS total_activated_apprvoed_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 401 OR entity_applications.status = 206 THEN 1 ELSE 0 END) AS total_surrendered_hardcopy_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 401 THEN 1 ELSE 0 END) AS total_surrendered_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 200 THEN 1 ELSE 0 END) AS total_approved_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 202 THEN 1 ELSE 0 END) AS total_submitted_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 500 THEN 1 ELSE 0 END) AS total_rejected_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 501 THEN 1 ELSE 0 END) AS total_expired_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 502 THEN 1 ELSE 0 END) AS total_deactivated_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 204 THEN 1 ELSE 0 END) AS total_verified_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 205 THEN 1 ELSE 0 END) AS total_send_back_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 255 THEN 1 ELSE 0 END) AS total_terminated_count'),
                DB::raw('SUM(CASE WHEN entity_applications.status = 206 THEN 1 ELSE 0 END) AS total_hard_copy_submitted_count')
            )
            ->leftJoin('users as user', 'user.id', '=', 'entity_applications.user_id')
            ->where('entity_applications.is_deleted', 'No');

            if (isset($filter_building) && !empty($filter_building)) {
                $bulidingFilters = array_filter($filter_building, fn($value) => $value !== 0 && !empty($value));
                if (!empty($bulidingFilters)) {
                    $data->whereIn('user.company_building', $bulidingFilters);
                    $dataCount->whereIn('user.company_building', $bulidingFilters);
                }
            }
            if (isset($filter_company) && !empty($filter_company)) {
                $companyFilters = array_filter($filter_company, fn($value) => $value !== 0 && !empty($value));
                if (!empty($companyFilters)) {
                    $data->whereIn('user.company_name', $companyFilters);
                    $dataCount->whereIn('user.company_name', $companyFilters);
                }
            }
            if (isset($gender) && !empty($gender)) {
                $data->where('entity_applications.gender', $gender);
                $dataCount->where('entity_applications.gender', $gender);
            }
            if (isset($type) && !empty($type)) {
                $data->where('entity_applications.type', $type);
                $dataCount->where('entity_applications.type', $type);
            }
            if ($ageFilter !== '0') {
                $currentDate = Carbon::now();
                if ($ageFilter === '40-inf') {
                    // For 40 and above
                    $maxDateOfBirth = $currentDate->subYears(40)->toDateString();
                    $data->where('entity_applications.date_of_birth', '<=', $maxDateOfBirth);
                    $dataCount->where('entity_applications.date_of_birth', '<=', $maxDateOfBirth);
                } else {
                    // For ranges like 18-20, 20-30, 30-40
                    [$minAge, $maxAge] = explode('-', $ageFilter);
                    $maxDateOfBirth = $currentDate->subYears($minAge)->toDateString(); // Earliest birthdate for max age
                    $minDateOfBirth = $currentDate->subYears($maxAge + 1)->addDay()->toDateString(); // Latest birthdate for min age
                    $data->whereBetween('entity_applications.date_of_birth', [$minDateOfBirth, $maxDateOfBirth]);
                    $dataCount->whereBetween('entity_applications.date_of_birth', [$minDateOfBirth, $maxDateOfBirth]);
                }
            }
            if ($year) {
                $data->whereYear('entity_applications.created_at', $year);
                $dataCount->whereYear('entity_applications.created_at', $year);
            }
            $data = $data->get();
            $dataCount = $dataCount->first();
        } else {
            // entity data
            $data = User::where('is_deleted', 'N');
            if (isset($filter_building) && !empty($filter_building)) {
                $bulidingFilters = array_filter($filter_building, fn($value) => $value !== 0 && !empty($value));
                if (!empty($bulidingFilters)) {
                    $data->whereIn('company_building', $bulidingFilters);
                }
            }
            if (isset($filter_company) && !empty($filter_company)) {
                $companyFilters = array_filter($filter_company, fn($value) => $value !== 0 && !empty($value));
                if (!empty($companyFilters)) {
                    $data->whereIn('company_name', $companyFilters);
                }
            }
            $data = $data->get();
        }

        $responseArray = array();
        if ($data->isEmpty()) {
            return response()->json(['result' => false, 'message' => 'No data found!']);
        }
        else{
            $formattedData = [];
            if($filter_report == 'Id-cards'){
                $statusWords = [
                    'total_count' => 'Total',
                    //'total_activated_count' => 'Activated',
                    'total_activated_apprvoed_count' => 'Activated/ Approved',
                    //'total_surrendered_count' => 'Surrendered',
                    'total_surrendered_hardcopy_count' => 'Surrender/ Hard copy submitted',
                    //'total_approved_count' => 'Approved',
                    'total_submitted_count' => 'Submitted',
                    'total_rejected_count' => 'Rejected',
                    //'total_expired_count' => 'Expired',
                    //'total_deactivated_count' => 'Deactivated',
                    'total_verified_count' => 'Verified',
                    'total_send_back_count' => 'Send Back',
                    'total_terminated_count' => 'Terminated',
                    //'total_hard_copy_submitted_count' => 'Copy Submitted',
                ];

                // Prepare an array with the word-based status counts
                $countDivHtml = '<div class="col-lg-12"><h4>Statistical</h4></div>';
                foreach ($statusWords as $key => $status) {
                    $countDivHtml .= '<div class="col-lg-3 report-statastic-container">
                                        <div class="report-statastic-count">
                                            <h4>'.$dataCount->$key.'</h4>
                                            <label>'.$status.'</label>
                                        </div>
                                    </div>';
                }
                $tableHtml = '';
                if($countDivHtml){
                    $tableHtml .= $countDivHtml;
                }
                $tableHtml .= '<table border="1" style="margin-top:20px;width: 98%; border-collapse: collapse;">';
                $tableHtml .= '
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Serial Number</th>
                            <th>Company Unit</th>
                            <th>Application Type</th>
                            <th>Issue Date</th>
                            <th>Expire Date</th>
                            <th>Company</th>
                        </tr>
                    </thead>
                    <tbody>
                ';
                foreach ($data as $row) {
                    $employeeName = $row->first_name;

                    // Append last name if it's not blank
                    if (!empty($row->last_name)) {
                        $employeeName .= ' ' . $row->last_name;
                    }
                    $formattedData[] = [
                        'employee'          => $employeeName,
                        'dob'               => $row->date_of_birth,
                        'gender'            => $row->gender,   // 'Gender'
                        'serial_number'     => $row->type != "Other" ? (string) $row->serial_no : (string) $row->final_special_serial_no,
                        'company_unit'      => $row->unit_category,  // 'Company Unit'
                        'application_type'  => $row->type,
                        'issue_date'        => date('d-m-Y',strtotime($row->issue_date)),
                        'expire_date'       => $row->type != 'Other' ? date('d-m-Y',strtotime($row->expire_date)) : '',
                        'building'          => $row->company_building,
                        'company'           => $row->company_name,
                    ];

                    $issue_date = ($row->issue_date) ? date("d-m-Y",strtotime($row->issue_date)) : '-';
                    $serial_no = ($row->type != "Other") ? $row->serial_no : $row->final_special_serial_no;
                    $expire_date = ($row->type != "Other") ? date("d-m-Y",strtotime($row->expire_date)) : "Till Posting in SEZ";

                    $tableHtml .= '<tr>';
                    $tableHtml .= '<td>' . $employeeName . '</td>';
                    $tableHtml .= '<td>' . $serial_no. '</td>';
                    $tableHtml .= '<td>' . $row->unit_category . '</td>';
                    $tableHtml .= '<td>' . $row->type. '</td>';
                    $tableHtml .= '<td>' . $issue_date. '</td>';
                    $tableHtml .= '<td>' . $expire_date . '</td>';
                    $tableHtml .= '<td>' . $row->company_name . '</td>';
                    $tableHtml .= '</tr>';
                }
                $tableHtml .= '</tbody></table>';
            } else {
                $tableHtml = '<table border="1" style="width: 98%; border-collapse: collapse;">';
                $tableHtml .= '
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Category</th>
                            <th>Company Building</th>
                            <th>Authorized Person</th>
                            <th>Mobile No.</th>
                        </tr>
                    </thead>
                    <tbody>
                ';
                foreach ($data as $row) {
                    $autoriszedPersonName = $row->authorized_person_first_name;
                    if($row->authorized_person_last_name){
                        $autoriszedPersonName .= ' '.$row->authorized_person_last_name;
                    }

                    $formattedData[] = [
                        'company_name'                  => $row->company_name,
                        'email'                         => $row->email,
                        'unit_category'                 => $row->unit_category,
                        'applcation_number'             => $row->request_number,
                        'company_registration_number'   => $row->company_registration_number,
                        'company_building'              => $row->company_building,
                        'authorized_person'             => $autoriszedPersonName,
                        'authorized_person_mobile'      => $row->authorized_person_mobile_number,
                    ];

                    $tableHtml .= '<tr>';
                    $tableHtml .= '<td>' . $row->company_name . '</td>';
                    $tableHtml .= '<td>' . $row->unit_category . '</td>';
                    //$tableHtml .= '<td>' . $row->request_number. '</td>';
                    $tableHtml .= '<td>' . $row->company_building . '</td>';
                    $tableHtml .= '<td>' . $autoriszedPersonName . '</td>';
                    $tableHtml .= '<td>' . $row->authorized_person_mobile_number . '</td>';
                    $tableHtml .= '</tr>';
                }
                $tableHtml .= '</tbody></table>';
            }
            $excelData = Excel::raw(new ExportBuildingCompanyApp(collect($formattedData), $filter_report), \Maatwebsite\Excel\Excel::XLSX);
            $base64Excel = base64_encode($excelData);
            $responseArray['status'] = false;
            $responseArray['message'] = 'Data found successfully';
            $responseArray['data'] = $base64Excel;
            $responseArray['tableData'] = $tableHtml;
            return response()->json(['result' => true, 'message' => 'Data Found Successfully', 'buildingCompanyApplicationsData' => $responseArray]);
        }
    }
}
