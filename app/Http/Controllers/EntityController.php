<?php
namespace App\Http\Controllers;
use App\Models\Address;
use App\Models\Group;
use App\Models\User;
use App\Models\EntityApplication;
use App\Models\Company;
use App\Mail\SendMailable;
use GuzzleHttp\Client;
use Http;
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
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;
use Helper;
use Exception;
use App\Models\Scan;
use Carbon\Carbon;
class EntityController extends Controller
{
    /**
     * Create a new controller instance.
     */
     protected $user;
     protected $group;
     protected $entityApplication;
     public function __construct()
     {
         $this->user = new User();
         $this->group = new Group();
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
    public function createNewApplication()
    {
        # Get country list
        $lastSerialNumber = EntityApplication::where("is_deleted", "No")->latest()
            ->limit(1)
            ->pluck("serial_no")
            ->first();
        $lastSerialNumber = $lastSerialNumber + 1;
        $lastSerialNumber = str_pad((int)$lastSerialNumber, 5, "0", STR_PAD_LEFT);
        return view("entity.create-new-application", compact("lastSerialNumber"))->with([]);
    }
    public function checkEntityApplicationEmailUnique(Request $request)
    {
        $email_address = $request->email;
        $entityApplicationDetail = DB::table("entity_applications");
        $entityApplicationDetail->where("email", $email_address)->where("is_verified", "Yes")
            ->where("user_id", $request->userId)
            ->whereBetween("status", [200, 210]);
        $entityApplicationDetail = $entityApplicationDetail->first();
        if (!empty($entityApplicationDetail))
        {
            return response()->json(["valid" => false]);
        }
        else
        {
            return response()
                ->json(["valid" => true]);
        }
    }
    public function checkCompanyId(Request $request)
    {
        $request_id = $request->request_id;
        $companyData = Company::where("application_no", $request_id)->first();
        if (!empty($companyData))
        {
            $checkEntityExists = User::where("request_number", $companyData["application_no"])->first();
            // dd($checkEntityExists);
            if (!empty($checkEntityExists))
            {
                return response()->json(["valid" => false, "message" => "This Company is already registered!", ]);
            }
            else
            {
                return response()
                    ->json(["valid" => true]);
            }
        }
        else
        {
            return response()
                ->json(["valid" => false]);
        }
    }
    public function saveEntityApplicationDetail(Request $request)
    {
        try
        {
            $input = $request->all();
            foreach ($input as $key => $value)
            {
                // Check if the key is not 'image' or 'image_hidden'
                if ($key !== "image_hidden" && $key !== "previous_image_hidden" && $key !== "signature_hidden" && $key !== "previous_signature_hidden" && $key !== "type" && $key !== "gender" && $key !== "draft_status")
                {
                    // Convert the value to uppercase
                    $input[$key] = strtoupper($value);
                }
            }
            $latestSerialNumber = $this
                ->entityApplication
                ->where("type", $input["type"])->orderByRaw("CAST(serial_no AS UNSIGNED) DESC")
                ->first();
            // dd($latestSerialNumber);
            $currentSerialNumber = $this
                ->entityApplication
                ->where("id", $input["id"])->first();
            if ($input["type"] == "Permanent")
            {
                if (!empty($input["id"]))
                {
                    $input["serial_no"] = $currentSerialNumber->serial_no;
                    $input["issue_date"] = date("Y-m-d");
                    $input["expire_date"] = date("Y-m-d", strtotime("+3 years"));
                }
                else
                {
                    // $input['serial_no']=(!empty($latestSerialNumber->serial_no) ? $latestSerialNumber->serial_no+1 : 5271);
                    $input["expire_date"] = date("Y-m-d", strtotime("+3 years"));
                    $input["issue_date"] = date("Y-m-d");
                }
            }
            else
            {
                if (!empty($input["id"]))
                {
                    $input["serial_no"] = $currentSerialNumber->serial_no;
                    $input["expire_date"] = date("Y-m-d", strtotime("+6 months"));
                    $input["issue_date"] = date("Y-m-d");
                }
                else
                {
                    // $input['serial_no']=(!empty($latestSerialNumber->serial_no) ? $latestSerialNumber->serial_no+1 : 8971);
                    $input["expire_date"] = date("Y-m-d", strtotime("+6 months"));
                    $input["issue_date"] = date("Y-m-d");
                }
            }
            $input["date_of_birth"] = date("Y-m-d", strtotime($input["date_of_birth"]));
            $file = $input["image_hidden"];
            $fileDir = config("constant.ENTITY_APPLICATION_IMAGE_PATH");
            $filePath = public_path("upload") . DIRECTORY_SEPARATOR . $fileDir;
            $base64Data = substr($file, strpos($file, ",") + 1);
            $binaryData = base64_decode($base64Data);
            $signaturefile = $input["signature_hidden"];
            $signaturefileDir = config("constant.ENTITY_APPLICATION_IMAGE_PATH");
            $signaturefilePath = public_path("upload") . DIRECTORY_SEPARATOR . $signaturefileDir;
            $signaturebase64Data = substr($signaturefile, strpos($signaturefile, ",") + 1);
            $signaturebinaryData = base64_decode($signaturebase64Data);
            File::makeDirectory($filePath, $mode = 0777, true, true);
            $entityApplicationDetail = $this
                ->entityApplication
                ->find($request->id);
            if ($request->id > 0)
            {
                // $entityApplicationEmailCheck = $this->commonCheckEntityApplicationDataExists($request);
                // if (!empty($entityApplicationEmailCheck)) {
                //     $error_message[] = 'Application is already registered with given Information.';
                //     $response = ['result' => false, 'message' => implode("<br>", $error_message)];
                //     return $response;
                // }
                $entityApplicationDetail = $this
                    ->entityApplication
                    ->find($request->id);
                $fileName = $entityApplicationDetail->id . "-uploaded-image-" . date("His") . ".png";
                $signaturefileName = $entityApplicationDetail->id . "-uploaded-signature-" . date("His") . ".png";
                if (empty($entityApplicationDetail))
                {
                    return response()->json(["result" => false, "message" => "There is some problem. Please try again!", "isPageRefresh" => true, ]);
                }
                else
                {
                    Log::info("Come to update");
                    // file_put_contents($filePath . '/' . $fileName, $binaryData);
                    // $input['image']     = $fileName;
                    // unset($input['previous_image_hidden']);
                    if (!empty($input["draft_status"]) && $input["draft_status"] == "Draft")
                    {
                        if (!empty($input["previous_image_hidden"]) && !empty($input["image_hidden"]))
                        {
                            if (strpos($input["image_hidden"], "data:image/") !== false)
                            {
                                // The string contains "data:image/"
                                // dd(1);
                                // Add your logic here
                                file_put_contents($filePath . "/" . $fileName, $binaryData);
                                $input["image"] = $fileName;
                                unset($input["draft_status"]);
                                $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                                if (!empty($input["previous_image_hidden"]))
                                {
                                    if (file_exists($filePath))
                                    {
                                        unlink($filePath);
                                    }
                                }
                                unset($input["previous_image_hidden"]);
                                unset($input["image_hidden"]);
                            }
                            else
                            {
                                // dd(2);
                                $input["image"] = $input["image_hidden"];
                                unset($input["draft_status"]);
                                unset($input["previous_image_hidden"]);
                                unset($input["image_hidden"]);
                            }
                        }
                        elseif (!empty($input["previous_signature_hidden"]) && !empty($input["signature_hidden"]))
                        {
                            if (strpos($input["signature_hidden"], "data:image/") !== false)
                            {
                                // The string contains "data:image/"
                                // dd(1);
                                // Add your logic here
                                file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                                $input["signature"] = $signaturefileName;
                                unset($input["draft_status"]);
                                $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                                if (!empty($input["previous_signature_hidden"]))
                                {
                                    if (file_exists($signaturefilePath))
                                    {
                                        unlink($signaturefilePath);
                                    }
                                }
                                unset($input["previous_signature_hidden"]);
                                unset($input["draft_status"]);
                                unset($input["signature_hidden"]);
                            }
                            else
                            {
                                // dd(2);
                                $input["signature"] = $input["signature_hidden"];
                                unset($input["draft_status"]);
                                unset($input["previous_signature_hidden"]);
                                unset($input["draft_status"]);
                                unset($input["signature_hidden"]);
                            }
                        }
                        elseif (empty($input["previous_image_hidden"]) && !empty($input["image_hidden"]))
                        {
                            file_put_contents($filePath . "/" . $fileName, $binaryData);
                            $input["image"] = $fileName;
                            $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                            if (!empty($entityApplicationDetail->image))
                            {
                                if (file_exists($filePath))
                                {
                                    unlink($filePath);
                                }
                            }
                            unset($input["previous_image_hidden"]);
                            unset($input["draft_status"]);
                            unset($input["image"]);
                        }
                        elseif (empty($input["previous_signature_hidden"]) && !empty($input["signature_hidden"]))
                        {
                            file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                            $input["signature"] = $signaturefileName;
                            $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                            if (!empty($entityApplicationDetail->signature))
                            {
                                if (file_exists($signaturefilePath))
                                {
                                    unlink($signaturefilePath);
                                }
                            }
                            unset($input["previous_signature_hidden"]);
                            unset($input["draft_status"]);
                            unset($input["signature_hidden"]);
                        }
                        else
                        {
                            file_put_contents($filePath . "/" . $fileName, $binaryData);
                            $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                            if (!empty($input["previous_image_hidden"]))
                            {
                                if (file_exists($filePath))
                                {
                                    unlink($filePath);
                                }
                            }
                            $input["image"] = $fileName;
                            unset($input["previous_image_hidden"]);
                            unset($input["image_hidden"]);
                            file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                            $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                            if (!empty($input["previous_signature_hidden"]))
                            {
                                if (file_exists($signaturefilePath))
                                {
                                    unlink($signaturefilePath);
                                }
                            }
                            $input["signature"] = $signaturefileName;
                            unset($input["draft_status"]);
                            unset($input["previous_signature_hidden"]);
                            unset($input["signature_hidden"]);
                        }
                    }
                    else
                    {
                        if (!empty($input["previous_image_hidden"]) && !empty($input["image_hidden"]))
                        {
                            if (strpos($input["image_hidden"], "data:image/") !== false)
                            {
                                // The string contains "data:image/"
                                // dd(1);
                                // Add your logic here
                                file_put_contents($filePath . "/" . $fileName, $binaryData);
                                $input["image"] = $fileName;
                                unset($input["draft_status"]);
                                $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                                if (!empty($input["previous_image_hidden"]))
                                {
                                    if (file_exists($filePath))
                                    {
                                        unlink($filePath);
                                    }
                                }
                                unset($input["previous_image_hidden"]);
                            }
                            else
                            {
                                // dd(2);
                                $input["image"] = $input["image_hidden"];
                                unset($input["previous_image_hidden"]);
                                unset($input["draft_status"]);
                            }
                        }
                        elseif (empty($input["previous_image_hidden"]) && !empty($input["image_hidden"]))
                        {
                            file_put_contents($filePath . "/" . $fileName, $binaryData);
                            $input["image"] = $fileName;
                            $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                            if (!empty($entityApplicationDetail->image))
                            {
                                if (file_exists($filePath))
                                {
                                    unlink($filePath);
                                }
                            }
                            unset($input["previous_image_hidden"]);
                            unset($input["draft_status"]);
                        }
                        else
                        {
                            file_put_contents($filePath . "/" . $fileName, $binaryData);
                            $input["image"] = $fileName;
                            $filePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                            if (!empty($input["previous_image_hidden"]))
                            {
                                if (file_exists($filePath))
                                {
                                    unlink($filePath);
                                }
                            }
                            unset($input["previous_image_hidden"]);
                            unset($input["draft_status"]);
                        }
                        if (!empty($input["previous_signature_hidden"]) && !empty($input["signature_hidden"]))
                        {
                            if (strpos($input["signature_hidden"], "data:image/") !== false)
                            {
                                // The string contains "data:image/"
                                // dd(1);
                                // Add your logic here
                                file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                                $input["signature"] = $signaturefileName;
                                unset($input["draft_status"]);
                                $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                                if (!empty($input["previous_signature_hidden"]))
                                {
                                    if (file_exists($signaturefilePath))
                                    {
                                        unlink($signaturefilePath);
                                    }
                                }
                                unset($input["previous_signature_hidden"]);
                            }
                            else
                            {
                                // dd(2);
                                $input["signature"] = $input["signature_hidden"];
                                unset($input["previous_signature_hidden"]);
                                unset($input["draft_status"]);
                            }
                        }
                        elseif (empty($input["previous_signature_hidden"]) && !empty($input["signature_hidden"]))
                        {
                            file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                            $input["signature"] = $signaturefileName;
                            $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                            if (!empty($entityApplicationDetail->signature))
                            {
                                if (file_exists($signaturefilePath))
                                {
                                    unlink($signaturefilePath);
                                }
                            }
                            unset($input["previous_signature_hidden"]);
                            unset($input["draft_status"]);
                        }
                        else
                        {
                            file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                            $input["signature"] = $signaturefileName;
                            $signaturefilePath = public_path("upload/entity-data/entity-application/" . $entityApplicationDetail->signature);
                            if (!empty($input["previous_signature_hidden"]))
                            {
                                if (file_exists($signaturefilePath))
                                {
                                    unlink($signaturefilePath);
                                }
                            }
                            unset($input["previous_signature_hidden"]);
                            unset($input["draft_status"]);
                        }
                    }
                    $updateValue = [];
                    unset($input["_token"]);
                    unset($input["id"]);
                    unset($input["is_entity_application_detail_valid"]);
                    unset($input["image_hidden"]);
                    unset($input["signature_hidden"]);
                    unset($input["previous_image_hidden"]);
                    unset($input["previous_signature_hidden"]);
                    $input["status"] = $this->entityApplication::ENTITY_APPLICATION_DRAFT;
                    if ($input["application_type"] == 1)
                    {
                        $input["application_type"] = 1;
                        $input["status"] = 202;
                    }
                    // dd($input);
                    foreach ($input as $fieldName => $fieldValue)
                    {
                        $updateValue[$fieldName] = $fieldValue;
                    }
                    $updateData = $this
                        ->entityApplication
                        ->where("id", $request->id);
                    $updateData->update($updateValue);
                    // dd($updateData->first()->qrcode);
                    $previousqrcode = $updateData->first()->qrcode;
                    Log::info("GenerateQRCodeUpdate");
                    $qrcodefile = $this->generateQrcode($updateData->first());
                    Log::info($qrcodefile);
                    $previous_qr_code = public_path("upload/qrcode/" . $updateData->first()
                        ->qrcode);
                    $updateData->update(["qrcode" => $qrcodefile]);
                    if (!empty($previousqrcode))
                    {
                        if (file_exists($previous_qr_code))
                        {
                            unlink($previous_qr_code);
                        }
                    }
                    $entityApplicationDetail = DB::table("entity_applications")->select("entity_applications.*", "user.company_name as entity_name", "user.authorized_person_first_name", "user.authorized_person_last_name", "user.unit_category as entity_type")
                        ->leftjoin("users as user", "user.id", "=", "entity_applications.user_id")
                        ->where("entity_applications.id", $entityApplicationDetail->id)
                        ->first();
                    $entityApplicationDetail->image = asset("/upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                    if ($entityApplicationDetail->type != "Permanent")
                    {
                        $entityApplicationDetail->backgroundimg = asset("/img/temporary_front_card.jpg");
                    }
                    else
                    {
                        $entityApplicationDetail->backgroundimg = asset("/img/front_card_bg.jpg");
                    }
                    $entityApplicationDetail->qrcode = asset("/upload/qrcode/" . $qrcodefile);
                    $entityApplicationDetail->issue_date = date("d-m-Y", strtotime($entityApplicationDetail->issue_date));
                    $entityApplicationDetail->expire_date = date("d-m-Y", strtotime($entityApplicationDetail->expire_date));
                    $entityApplicationDetail->mobile_number = "+" . $entityApplicationDetail->dial_code . " " . $entityApplicationDetail->mobile_number;
                    $entityApplicationDetail->application_type = Helper::getEntityApplicationType($entityApplicationDetail->application_type);
                    return response()
                        ->json(["result" => true, "message" => 'Entity Application\'s basic detail is updated', "entityApplicationData" => $entityApplicationDetail, ]);
                }
            }
            else
            {
                $error_message = [];
                // $entityApplicationEmailCheck = $this->entityApplication->where('email', $request->email);
                // if(isset($request->id) && !empty($request->id)){
                //     $entityApplicationEmailCheck->where('id', '!=', $request->id);
                // }
                // $entityApplicationEmailCheck = $entityApplicationEmailCheck->first();
                // if(!empty($entityApplicationEmailCheck)){
                //     $error_message[] = 'Appllication is already registered with added email address.';
                // }
                $entityApplicationEmailCheck = $this->commonCheckEntityApplicationDataExists($request);
                if (!empty($entityApplicationEmailCheck))
                {
                    $error_message[] = "Application is already registered with given Information.";
                }
                if (empty($error_message))
                {
                    unset($input["id"]);
                    $input["status"] = $this->entityApplication::ENTITY_APPLICATION_DRAFT;
                    $entityApplicationDetail = $this
                        ->entityApplication
                        ->create($input);
                    $fileName = $entityApplicationDetail->id . "-uploaded-image" . date("His") . ".png";
                    $signaturefileName = $entityApplicationDetail->id . "-uploaded-signature" . date("His") . ".png";
                    $input["image"] = $fileName;
                    $input["signature"] = $signaturefileName;
                    file_put_contents($filePath . "/" . $fileName, $binaryData);
                    file_put_contents($signaturefilePath . "/" . $signaturefileName, $signaturebinaryData);
                    Log::info("GenerateQRCode");
                    $qrcodefile = $this->generateQrcode($entityApplicationDetail);
                    Log::info($qrcodefile);
                    $entityApplicationUpdatedDetail = $this
                        ->entityApplication
                        ->where("id", $entityApplicationDetail->id)
                        ->update(["image" => $fileName, "signature" => $signaturefileName, "qrcode" => $qrcodefile, ]);
                    $entityApplicationDetail = DB::table("entity_applications")->select("entity_applications.*", "user.company_name as entity_name", "user.authorized_person_first_name", "user.authorized_person_last_name", "user.unit_category as entity_type")
                        ->leftjoin("users as user", "user.id", "=", "entity_applications.user_id")
                        ->where("entity_applications.id", $entityApplicationDetail->id)
                        ->first();
                    $entityApplicationDetail->image = asset("/upload/entity-data/entity-application/" . $entityApplicationDetail->image);
                    if ($entityApplicationDetail->type != "Permanent")
                    {
                        $entityApplicationDetail->backgroundimg = asset("/img/temporary_front_card.jpg");
                    }
                    else
                    {
                        $entityApplicationDetail->backgroundimg = asset("/img/front_card_bg.jpg");
                    }
                    $entityApplicationDetail->qrcode = asset("/upload/qrcode/" . $qrcodefile);
                    $entityApplicationDetail->issue_date = date("d-m-Y", strtotime($entityApplicationDetail->issue_date));
                    $entityApplicationDetail->expire_date = date("d-m-Y", strtotime($entityApplicationDetail->expire_date));
                    $entityApplicationDetail->mobile_number = "+" . $entityApplicationDetail->dial_code . " " . $entityApplicationDetail->mobile_number;
                    $entityApplicationDetail->application_type = Helper::getEntityApplicationType($entityApplicationDetail->application_type);
                    $entityApplicationDetail->draft_status = "Draft";
                    return response()
                        ->json(["result" => true, "message" => 'Entity Application\'s basic detail is saved', "entityApplicationData" => $entityApplicationDetail, ]);
                }
                else
                {
                    return response()->json(["result" => false, "message" => implode("<br>", $error_message) , ]);
                }
            }
        }
        catch(\Exception $ex)
        {
            dd($ex);
            return response(["result" => false, "message" => $ex->getMessage() , ]);
        }
    }
    public function generateQrcode($entityApplicationDetail)
    {
        // $qrcodeData=array(
        //     'Application No.'=>$entityApplicationDetail->application_number,
        //     'Contact No.'=>$entityApplicationDetail->mobile_number,
        //     'Auth Person Name'=>$entityApplicationDetail->authorized_signatory,
        //     'Date of Issue'=>date('d-m-Y',strtotime($entityApplicationDetail->created_at)),
        //     'Valid upto'=>date('d-m-Y',strtotime($entityApplicationDetail->expire_date))
        // );
        $qrcodeData = url("id-card-application/" . encrypt($entityApplicationDetail->id));
        $qrcodefile = "qrcode_" . uniqid() . ".svg";
        // Generate the QR code with sample data (replace with your actual data)
        // $qrcodeData = json_encode($qrcodeData,true);
        // $qrcodeData = implode(PHP_EOL, array_map(function ($key, $value) {
        //     return $key . (is_array($value) ? arrayToUl($value) : ': ' . $value);
        // }, array_keys($qrcodeData), $qrcodeData));
        Log::info("Data inside QR code");
        Log::info($qrcodeData);
        QrCode::size(300)->generate($qrcodeData, public_path("upload/qrcode/" . $qrcodefile));
        return $qrcodefile;
    }
    public function saveEntityApplicationVerifyAndSubmitDetail(Request $request)
    {
        // dd($request->all());
        try
        {
            $input = $request->all();
            if ($request->id > 0)
            {
                $entityApplicationDetail = $this
                    ->entityApplication
                    ->find($request->id);
                $latestSerialNumber = $this
                    ->entityApplication
                    ->where("type", $entityApplicationDetail->type)
                    ->orderByRaw("CAST(serial_no AS UNSIGNED) DESC")
                    ->first();
                // dd($latestSerialNumber);
                $currentSerialNumber = $this
                    ->entityApplication
                    ->where("id", $input["id"])->first();
                if ($entityApplicationDetail->type == "Permanent")
                {
                    if (!empty($entityApplicationDetail->serial_no))
                    {
                        $input["serial_no"] = $entityApplicationDetail->serial_no;
                    }
                    else
                    {
                        $input["serial_no"] = !empty($latestSerialNumber->serial_no) ? $latestSerialNumber->serial_no + 1 : 5271;
                    }
                    $input["expire_date"] = date("Y-m-d", strtotime("+3 years"));
                    $input["issue_date"] = date("Y-m-d");
                }
                else
                {
                    if (!empty($entityApplicationDetail->serial_no))
                    {
                        $input["serial_no"] = $entityApplicationDetail->serial_no;
                    }
                    else
                    {
                        $input["serial_no"] = !empty($latestSerialNumber->serial_no) ? $latestSerialNumber->serial_no + 1 : 8971;
                    }
                    $input["expire_date"] = date("Y-m-d", strtotime("+6 months"));
                    $input["issue_date"] = date("Y-m-d");
                }
                // dd($input);
                if (empty($entityApplicationDetail))
                {
                    return response()->json(["result" => false, "message" => "There is some problem. Please try again!", "redirectPage" => route("entity.create-new-application") , ]);
                }
                else
                {
                    $error_message = [];
                    // // $entityApplicationEmailCheck = $this->entityApplication->where('email', $entityApplicationDetail->email);
                    // // if(isset($request->id) && !empty($request->id)){
                    // //     $entityApplicationEmailCheck->where('id', '!=', $request->id);
                    // // }
                    // // $entityApplicationEmailCheck = $entityApplicationEmailCheck->first();
                    // if(!empty($entityApplicationEmailCheck)){
                    //     $error_message[] = 'Appllication is already registered with added email address.';
                    // }
                    if (empty($error_message))
                    {
                        $updateValue = [];
                        unset($input["_token"]);
                        unset($input["id"]);
                        unset($input["user_id"]);
                        unset($input["accept_term"]);
                        $input["status"] = $this->entityApplication::ENTITY_APPLICATION_SUBMITTED;
                        if (!empty($entityApplicationDetail->application_number))
                        {
                            $application_number = $entityApplicationDetail->application_number;
                        }
                        else
                        {
                            $application_number = $this->generateApplicationNumber();
                        }
                        $input["application_number"] = $application_number;
                        foreach ($input as $fieldName => $fieldValue)
                        {
                            $updateValue[$fieldName] = $fieldValue;
                        }
                        $entityApplicationDetailUpdate = $this
                            ->entityApplication
                            ->where("id", $entityApplicationDetail->id);
                        $entityApplicationDetailUpdate->update($updateValue);
                        Log::info("saveEntityApplicationVerifyAndSubmitDetail");
                        $qrcodefile = $this->generateQrcode($entityApplicationDetailUpdate->first());
                        Log::info($qrcodefile);
                        $previous_qr_code = public_path("upload/qrcode/" . $entityApplicationDetailUpdate->first()
                            ->qrcode);
                        $entityApplicationDetailUpdate->update(["qrcode" => $qrcodefile, ]);
                        if (!empty($entityApplicationDetailUpdate->first()
                            ->qrcode))
                        {
                            if (file_exists($previous_qr_code))
                            {
                                unlink($previous_qr_code);
                            }
                        }
                        $getEntityData = Helper::getEntityDetail($entityApplicationDetail->user_id);
                        $entityMail = $getEntityData->email;
                        // dd($entityApplicationDetail->application_number);
                        // Todo:Send Mail to entity mail address related to application
                        $entityApplicationDetail = $this
                            ->entityApplication
                            ->findOrFail($entityApplicationDetail->id);
                        $mailData = ["data" => $entityApplicationDetail, "mailType" => "newEntityApplication", ];
                        //Helper::statuChangeEmailCommonFunction($entityApplicationDetail);
                        $applicationType = Helper::getEntityApplicationType($entityApplicationDetail['application_type']);
                        $emailData['email']=$entityMail;
                        $emailData['data']=$entityApplicationDetail;
                        $emailData['viewFile']='emails.entityapplicationstatuschange';
                        $emailData['mailType']='newEntityApplication';
                        $emailData['subject']='Card Management - Entity '.$applicationType.' Application';
                        $emailData['errorLogChannel'] = 'entity-application-status';
                        Helper::sendMail($emailData);
                        //Mail::to($entityMail)->send(new SendMailable($mailData));
                        return response()->json(["result" => true, "message" => 'Entity Application\'s detail submitted', "entityApplicationId" => $entityApplicationDetail["id"], "user_id" => $entityApplicationDetail["user_id"], ]);
                    }
                    else
                    {
                        return response()->json(["result" => false, "message" => implode("<br>", $error_message) , ]);
                    }
                }
            }
            else
            {
                return response()->json(["result" => false, "message" => "Entity details not saved. Please save it first", ]);
            }
        }
        catch(\Exception $ex)
        {
            return response(["result" => false, "message" => $ex->getMessage() , ]);
        }
    }
    function generateApplicationNumber()
    {
        $randomNumber = mt_rand(1000000, 9999999);
        $exists = $this
            ->entityApplication
            ->where("application_number", $randomNumber)->exists();
        if ($exists)
        {
            return $this->generateUniqueRandomNumber();
        }
        // If the number is unique, return it
        return $randomNumber;
    }
    public function entityApplicationSucess(Request $request)
    {
        try
        {
            if ($request->entityApplicationId > 0)
            {
                $entity_application_details = $this
                    ->entityApplication
                    ->where("user_id", $request->user_id)
                    ->find($request->entityApplicationId);
                if (!empty($entity_application_details))
                {
                    return view("entity.success")->with(["entity_application_details" => $entity_application_details, ]);
                }
                else
                {
                    return redirect("entity/createNewApplication")->with("error", Config::get("constant.COMMON_TECHNICAL_MESSAGE"));
                }
            }
            else
            {
                return redirect("entity/createNewApplication")
                    ->with("error", Config::get("constant.COMMON_TECHNICAL_MESSAGE"));
            }
        }
        catch(\Exception $ex)
        {
            return redirect("entity/createNewApplication")->with("error", Config::get("constant.COMMON_TECHNICAL_MESSAGE"));
        }
    }
    public function getVal($filterVar)
    {
        $filterVar = str_replace('$', "", $filterVar);
        $filterVar = str_replace("^", "", $filterVar);
        return $filterVar;
    }
    public function recentApplicationsView()
    {
        $userId = Auth::id();
        $statuses = EntityApplication::select(DB::raw('CASE
                                            WHEN status = 200 THEN "Approved"
                                            WHEN status = 201 THEN "Draft"
                                            WHEN status = 202 THEN "Submited"
                                            WHEN status = 500 THEN "Rejected"
                                            WHEN status = 501 THEN "Expired"
                                            WHEN status = 401 THEN "Surrendered"
                                            WHEN status = 502 THEN "Deactivated"
                                            WHEN status = 203 THEN "Activated"
                                            WHEN status = 204 THEN "Verified"
                                            WHEN status = 206 THEN "Hard copy submitted"
                                            WHEN status = 255 THEN "Terminated"
                                            WHEN status = 205 THEN "Send Back"
											WHEN status = 403 THEN "Blocked"
                                            WHEN status = null THEN ""
                                            ELSE "Undefined"
                                        END AS status'))
        // ->whereNotIn('status', [201,202,205])
        ->where("user_id", $userId)
        // ->orderBy('created_at', 'desc')
        ->distinct("status")
            ->pluck("status");
        $applicationTypes = EntityApplication::select("type")->distinct("type")
            ->pluck("type");
        if (request()
            ->ajax())
        {
            $data = EntityApplication::where("is_deleted", "No")->where("user_id", $userId)->orderBy("serial_no", "DESC")
                ->select("*");
            if (request()
                ->has("columns"))
            {
                $columns = request()->get("columns");
                // Filter by status
                if (isset($columns[7]["search"]["value"]))
                {
                    $statusFilter = $this->getVal($columns[7]["search"]["value"]);
                    $statusFilter = Helper::getApplicationCode($statusFilter);
                    $data->where("entity_applications.status", $statusFilter);
                }
                if (isset($columns[3]["search"]["value"]))
                {
                    $statusFilter = $this->getVal($columns[3]["search"]["value"]);
                    $data->where("entity_applications.type", $statusFilter);
                }
            }
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query)
            {
                if (request()->has("search"))
                {
                    $searchTerm = request()->get("search") ["value"];
                    $query->where(function ($subQuery) use ($searchTerm)
                    {
                        foreach ($subQuery->getModel()
                            ->getFillable() as $column)
                        {
                            $subQuery->orWhere($column, "like", "%{$searchTerm}%");
                        }
                    });
                }
            });
            return $dataTable->addColumn("application_type", function ($row)
            {
                // dd($row->application_type);
                return Helper::getEntityApplicationType($row->application_type);
            })
->addColumn("issue_date", function ($row)
            {
                return date("d-m-Y", strtotime($row->issue_date));
            })
->addColumn("expire_date", function ($row)
            {
                if ($row->type == "Other")
                {
                    return "";
                }
                return date("d-m-Y", strtotime($row->expire_date));
            })->addColumn("type", function ($row)
            {
                return $row->type;
            })
            // ->addColumn('application_number', function($row){
            //     return $row->application_number;
            // })
            ->addColumn("serial_no", function ($row)
            {
                if (empty($row->serial_no))
                {
                    $row->serial_no = $row->final_special_serial_no;
                }
                return $row->serial_no;
            })
->addColumn("name", function ($row)
            {
                return $row->fullname;
            })
->addColumn("status", function ($row)
            {
                return '<span class="label label-lg label-inline ' . Helper::getApplicationStatusBackgroundColor($row->status) . '">' . Helper::getApplicationType($row->status) . "</span>";
            })
->addColumn("action", function ($row)
            {
                // <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Application</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                $btn = '<div class="d-flex align-items-center">';
                $btn .= '<a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="' . $row->id . '">
                        <i class="fas fa-eye fa-1x text-green"></i>
                    </a>';
                if ($row->status == 201 || $row->status == 202)
                {
                    $btn .= '<a  href="' . url("entity/draftApplication/" . $row->id) . '" class="btn btn-icon btn-light btn-hover-primary btn-sm mx-3"  data-id="' . $row->id . '">
                        <i class="fas fa-pen fa-1x text-green"></i>
                    </a>';
                }
                $btn .= "</div>";
                return $btn;
            })->rawColumns(["status", "application_type", "action"])
                ->addIndexColumn()
                ->make(true);
        }
        return view("entity.recent-application-view", compact("statuses", "applicationTypes"));
    }
    public function adminApplicationsView()
    {
        $userId = Auth::id();
        //$filterBuildingData=EntityApplication::filterBuildingData();
        //$filterCompanyData=EntityApplication::filterBaseCompanyData([0]);
        $buildingList=Address::where('status',1)->pluck('address');
        $filterBuildingData=EntityApplication::getBuildingList();
        $filterCompanyData=EntityApplication::getCompanyList('0');
        $yearsList=EntityApplication::yearList();
        $companyList = Company::pluck("name");
        $applicationTypes = EntityApplication::select("type")->whereNotIn("type", ["Other"])
            ->distinct("type")
            ->pluck("type");
        if (Auth::user()
            ->getRoleNames()
            ->first() == "Admin")
        {
            $companyNames = EntityApplication::select("users.company_name")->distinct("entity_applications.company_name")
                ->leftJoin("users", "entity_applications.user_id", "=", "users.id")
            // ->latest()
                ->whereNotIn("status", [201, 202])
                ->pluck("users.company_name");
            $statuses = EntityApplication::select(DB::raw('CASE
                            WHEN status = 200 THEN "Approved"
                            WHEN status = 500 THEN "Rejected"
                            WHEN status = 501 THEN "Expired"
                            WHEN status = 502 THEN "Deactivated"
                            WHEN status = 203 THEN "Activated"
                            WHEN status = 204 THEN "Verified"
                            WHEN status = 206 THEN "Hard copy submitted"
                            WHEN status = 255 THEN "Terminated"
							WHEN status = 403 THEN "Blocked"
                            ELSE "Undefined"
                        END AS status'))->whereNotIn("status", [201, 202, 205, 401])
                ->distinct("status")
                ->pluck("status");
        }
        else
        {
            $companyNames = EntityApplication::select("users.company_name")->distinct("entity_applications.company_name")
                ->leftJoin("users", "entity_applications.user_id", "=", "users.id")
            // ->latest()
                ->pluck("users.company_name");
            // dd($companyNames);
            $statuses = EntityApplication::select(DB::raw('CASE
                                                WHEN status = 200 THEN "Approved"
                                                WHEN status = 202 THEN "Submited"
                                                WHEN status = 500 THEN "Rejected"
                                                WHEN status = 501 THEN "Expired"
                                                WHEN status = 401 THEN "Surrendered"
                                                WHEN status = 502 THEN "Deactivated"
                                                WHEN status = 203 THEN "Activated"
                                                WHEN status = 204 THEN "Verified"
                                                WHEN status = 205 THEN "Send Back"
                                                WHEN status = 206 THEN "Hard copy submitted"
                                                WHEN status = 255 THEN "Terminated"
												WHEN status = 403 THEN "Blocked"
                                                WHEN status = null THEN ""
                                                ELSE "Undefined"
                                            END AS status'))->whereNotIn("status", [201])
                ->distinct("status")
                ->pluck("status");
            // dd($statuses);
        }
        if (request()
            ->ajax())
        {
            if (Auth::user()
                ->getRoleNames()
                ->first() == "Admin")
            {
                $data = EntityApplication::select("entity_applications.*", "users.company_name as company_name")->leftJoin("users", "users.id", "=", "entity_applications.user_id")
                    ->whereNotIn("type", ["Other"])
                    ->whereNotIn("entity_applications.status", [201, 202, 205, 401, ])
                    ->where("entity_applications.is_deleted", "No")
                    ->orderBy("entity_applications.serial_no", "DESC")
                    ->latest();
            }
            else
            {
                $data = EntityApplication::select("entity_applications.*", "users.company_name as company_name")->leftJoin("users", "users.id", "=", "entity_applications.user_id")
                    ->whereNotIn("entity_applications.status", [201])
                    ->whereNotIn("type", ["Other"])
                    ->where("entity_applications.is_deleted", "No")
                    ->orderBy("entity_applications.serial_no", "DESC")
                    ->latest();
            }
            // Add separate filters for status and name
            if (request()
                ->has("columns"))
            {
                $columns = request()->get("columns");
                // Filter by status
                if (isset($columns[8]["search"]["value"]))
                {
                    $statusFilter = $this->getVal($columns[8]["search"]["value"]);
                    // dump( $statusFilter);
                    $statusFilter = Helper::getApplicationCode($statusFilter);
                    // dd($statusFilter);
                    $data->where("entity_applications.status", $statusFilter);
                }
                if (isset($columns[4]["search"]["value"]))
                {
                    $statusFilter = $this->getVal($columns[4]["search"]["value"]);
                    $data->where("entity_applications.type", $statusFilter);
                }
                // Filter by entity
                // if (isset($columns[2]['search']['value'])) {
                //     $nameFilter = $this->getVal($columns[2]['search']['value']);
                //     $userData=User::whereRaw("company_name LIKE ?", ["%{$nameFilter}%"])->first();
                //     if($userData){
                //         $data->where('company_name',$userData->company_name);
                //     }
                //     // $data->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$nameFilter}%"]);
                //     // $data->datatableSearch($nameFilter);
                // }
            }
            // dd($data->get());
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query)
            {
                if (request()->has("search"))
                {
                    $searchTerm = request()->get("search") ["value"];
                    $query->where(function ($subQuery) use ($searchTerm)
                    {
                        foreach ($subQuery->getModel()
                            ->getFillable() as $column)
                        {
                            // if ($column === 'first_name' || $column === 'last_name') {
                            //     $subQuery->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                            // } else {
                            //     $subQuery->orWhere($column, 'like', "%{$searchTerm}%");
                            // }
                            if ($column === "first_name" || $column === "last_name")
                            {
                                $subQuery->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                            }
                            elseif ($column === "user_id")
                            {
                                $subQuery->orWhereRaw("users.company_name LIKE ?", ["%{$searchTerm}%"]);
                            }
                            else
                            {
                                $subQuery->orWhere("entity_applications." . $column, "like", "%{$searchTerm}%");
                            }
                            // $subQuery->orWhere($column, 'like', "%{$searchTerm}%");
                        }
                    });
                }
            });
            return $dataTable->addColumn("application_number", function ($row)
            {
                return $row->application_number;
            })
->addColumn("users.company_name", function ($row)
            {
                return $row->company_name;
            })
->addColumn("application_type", function ($row)
            {
                return Helper::getEntityApplicationType($row->application_type);
            })->addColumn("type", function ($row)
            {
                return $row->type;
            })
->addColumn("issue_date", function ($row)
            {
                return date("d-m-Y", strtotime($row->issue_date));
            })
->addColumn("expire_date", function ($row)
            {
                return date("d-m-Y", strtotime($row->expire_date));
            })
->addColumn("serial_no", function ($row)
            {
                return $row->serial_no;
            })
->addColumn("name", function ($row)
            {
                return $row->fullname;
            })
->addColumn("status", function ($row)
            {
                return '<span class="label label-lg label-inline ' . Helper::getApplicationStatusBackgroundColor($row->status) . '">' . Helper::getApplicationType($row->status) . "</span>";
            })
->addColumn("is_verified", function ($row)
            {
                if ($row->is_verified == 0)
                {
                    return '<a href="javascript:;" class="label label-lg label-inline background-light-orange text-orange" onclick="verifyentityapplication(' . $row->id . ')">Verify</a>';
                }
                elseif ($row->is_verified == 1)
                {
                    return '<span class="label label-lg label-inline background-light-green text-green">Verified</span>';
                }
                else
                {
                    return "";
                }
            })
->addColumn("action", function ($row)
            {
                // <div class="d-flex align-items-center">
                //     <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Application</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                // </div>
                $btn = '<div class="d-flex align-items-center">
                <a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="' . $row->id . '">
                    <i class="fas fa-eye fa-1x text-green"></i>
                </a>
            </div>';
                return $btn;
            })->rawColumns(["status", "is_verified", "action"])
                ->addIndexColumn()
                ->make(true);
        }
        // $recentApplicationsData = $this->entityApplication->where('user_id',session::get('User.id'))->whereBetween('status',[200,210])->get();//paginate(5);
        // $recentApplicationsData = EntityApplication::where('user_id',$userId)->paginate(5);
        // // dd($recentApplicationsData);
        return view("entity.admin-application-view", compact("companyNames", "statuses", "buildingList", "companyList", "filterCompanyData", "applicationTypes",'filterBuildingData','filterCompanyData','yearsList'));
    }
    public function adminEntityView()
    {
        if (request()
            ->ajax())
        {
            $data = User::where("id", ">", 2)->where("email", "!=", "dataentryadmin@gmail.com")
                ->select("*", DB::raw("CONCAT(authorized_person_first_name,' ',authorized_person_last_name) as authorized_person_name"))
                ->latest();
            if (request()
                ->unitAddress)
            {
                $data->where("company_address", request()
                    ->unitAddress);
            }
            if (request()
                ->has("columns"))
            {
                $columns = request()->get("columns");
                // Filter by status
                if (isset($columns[5]["search"]["value"]))
                {
                    $statusFilter = $this->getVal($columns[5]["search"]["value"]);
                    $statusFilter = str_replace("\\", "", $statusFilter);
                    $data->where("company_address", $statusFilter);
                }
                // Filter by entity
                // if (isset($columns[2]['search']['value'])) {
                //     $nameFilter = $this->getVal($columns[2]['search']['value']);
                //     $userData=User::whereRaw("company_name LIKE ?", ["%{$nameFilter}%"])->first();
                //     if($userData){
                //         $data->where('company_name',$userData->company_name);
                //     }
                //     // $data->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$nameFilter}%"]);
                //     // $data->datatableSearch($nameFilter);
                // }
            }
            // dd($data->get());
            $dataTable = datatables()->of($data);
            // Add global search filter
            $dataTable->filter(function ($query)
            {
                if (request()->has("search"))
                {
                    $searchTerm = request()->get("search") ["value"];
                    $query->where(function ($subQuery) use ($searchTerm)
                    {
                        //dd($subQuery->getModel()->getFillable());
                        foreach ($subQuery->getModel()
                            ->getFillable() as $column)
                        {
                            if ($column === "name")
                            {
                                $subQuery->orWhereRaw("CONCAT(authorized_person_first_name, ' ', authorized_person_last_name) LIKE ?", ["%{$searchTerm}%"]);
                            }
                            else
                            {
                                $subQuery->orWhere($column, "like", "%{$searchTerm}%");
                            }
                            // $subQuery->orWhere($column, 'like', "%{$searchTerm}%");
                        }
                    });
                }
            });
            return $dataTable->addColumn("unit_category", function ($row)
            {
                return $row->unit_category;
            })->addColumn("created_at", function ($row)
            {
                return date("d-m-Y", strtotime($row->created_at));
            })->addColumn("company_name", function ($row)
            {
                return $row->company_name;
            })->addColumn("company_address", function ($row)
            {
                return $row->company_address;
            })->addColumn("authorized_person_name", function ($row)
            {
                return $row->authorized_person_name;
            })->addColumn("application_number", function ($row)
            {
                return $row->application_number;
            })->addColumn("email", function ($row)
            {
                return $row->email;
            })->addColumn("authorized_person_mobile_number", function ($row)
            {
                return $row->authorized_person_mobile_number;
            })
->addColumn("action", function ($row)
            {
                // <div class="d-flex align-items-center">
                //     <a id="viewApplication" class="viewApplication btn btn-light btn-lg" data-id="'.$row->id.'">
                //         <span class="mr-5">View Entity</span>
                //         <i class="ki ki-long-arrow-next icon-sm"></i>
                //     </a>
                // </div>
                $btn = '<div class="d-flex align-items-center">
                    <a id="viewApplication" class="viewApplication btn btn-primary btn-sm"  data-id="' . $row->id . '">
                        <i class="fas fa-eye fa-1x text-green"></i>
                    </a>
                </div>';
                return $btn;
            })
->addIndexColumn()
                ->make(true);
        }
        $BuildingAddress = User::distinct("company_address")->pluck("company_address");
        return view("entity.admin-entity-view", compact("BuildingAddress"));
    }
    public function recentApplicationsDataFetch(Request $request)
    {
        $query = $this->entityApplication::query();
        // Apply filters based on search parameters
        if ($request->filled("generalSearch"))
        {
            $query->where("OrderID", "like", "%" . $request->input("generalSearch") . "%")
                ->orWhere("Country", "like", "%" . $request->input("generalSearch") . "%");
        }
        // Add more filters if needed...
        // Get paginated data
        $data = $query->paginate($request->input("pagination") ["perpage"]);
        return response()
            ->json($data);
    }
    public function getApplication($id)
    {
        $entityApplicationData = EntityApplication::find($id);
        return view("entity.view-application", compact("entityApplicationData"));
    }
    public function getEntity($id)
    {
        $entityData = User::find($id);
        return view("entity.view-entity", compact("entityData"));
    }
    public function change_application_status($id, $status)
    {
        //if($id != '12702'){
        $entityApplicationData = EntityApplication::find($id);
        $entityApplicationData->status = $status;
        $entityApplicationData->save();
        $entityApplicationData = EntityApplication::findOrFail($id);
        Helper::statuChangeEmailCommonFunction($entityApplicationData);
        // }
        if ($status == config("constant.ENTITY_APPLICATION_APPROVED"))
        {
            return redirect()->back();
            return redirect("entity/proceedToPrint/" . $id)->with("success", "Application has been approved!");
        }
        elseif ($status == config("constant.ENTITY_APPLICATION_REJECTED"))
        {
            return redirect("entity/recentApplications/")->with("error", "Application " . $entityApplicationData->application_number . " has been rejected!");
        }
        elseif ($status == config("constant.ENTITY_APPLICATION_SENDBACK"))
        {
            return redirect("entity/recentApplications/");
        }
        elseif ($status == config("constant.ENTITY_APPLICATION_READY_TO_COLLECT"))
        {
            $row = EntityApplication::select("entity_applications.*", "user.company_name as entity_name", "user.authorized_person_first_name", "user.authorized_person_last_name", "user.unit_category as entity_type")->leftjoin("users as user", "user.id", "=", "user_id")
                ->where("entity_applications.id", $id)->first();
            $directory = public_path("pdfs");
            if (!is_dir($directory))
            {
                mkdir($directory);
            }
            $mpdf = new Mpdf(["format" => "A4-P"]);
            $cardsPerRow = 1; // Change this to the desired number of cards per row
            $verticalSpace = 10;
            // $cardsPerColumn = count($FetchApplicationDetails) / $cardsPerRow;
            //dd($cardsPerColumn);
            $cardWidth = 60; // Adjust as needed
            $cardHeight = 70; // Adjust as needed
            $marginX = 0; // Adjust as needed
            $marginY = 0;
            $html = "";
            // $html .= $this->generateIdCardHtml($FetchApplicationDetails);
            $html .= '<div style="display:flex;">';
            $html .= view("pdfview.front-view", compact("row"))->render();
            $html .= view("pdfview.back-view", compact("row"))->render();
            $html .= "</div>";
            // echo $html;
            $mpdf->WriteHTML($html);
            $pdfPath = public_path("pdfs/idcard-generated.pdf");
            $mpdf->Output($pdfPath, "F");
            return response()->json(["pdfPath" => $pdfPath, "message" => "PDF generated successfully", ]);
            // return response()->json(['pdfPath' => $pdfPath]);
            //     return redirect('entity/proceedToPrint/'.$id)->with('success', 'Card has been Activated!');
        }
        // dd($entityApplicationData);
    }
    public function proceedToPrint($id)
    {
        $entityApplicationData = EntityApplication::find($id);
        return view("entity.proceed-print", compact("entityApplicationData"));
    }
    public function rejectApplication(Request $request)
    {
        // dd($request->all());
        $entityApplicationData = EntityApplication::find($request->application_id);
        $entityApplicationData->status = $request->status;
        $entityApplicationData->comment = $request->comment;
        $entityApplicationData->save();
        $entityApplicationData = EntityApplication::findOrFail($request->application_id);
        Helper::statuChangeEmailCommonFunction($entityApplicationData);
        // return back()->with('error', 'Application '.$entityApplicationData->application_number.' has been rejected!');
        return back();
        // return redirect('entity/recentApplications')->with('error', 'Application '.$entityApplicationData->application_number.' has been rejected!');
    }
    public function generatePdf(request $request)
    {
        // Your PDF generation logic here
        $selectedIds = request("selectedIds"); // Replace this with your actual selected IDs
        $FetchApplicationDetails = EntityApplication::select("entity_applications.*", "user.company_name as entity_name", "user.authorized_person_first_name", "user.authorized_person_last_name", "user.unit_category as entity_type")->leftjoin("users as user", "user.id", "=", "user_id")
            ->whereIn("entity_applications.id", $selectedIds)->get();
        $directory = public_path("pdfs");
        if (!is_dir($directory))
        {
            mkdir($directory);
        }
        $mpdf = new Mpdf(["format" => "A4-P"]);
        $cardsPerRow = 1; // Change this to the desired number of cards per row
        $verticalSpace = 10;
        $cardsPerColumn = count($FetchApplicationDetails) / $cardsPerRow;
        //dd($cardsPerColumn);
        $cardWidth = 60; // Adjust as needed
        $cardHeight = 70; // Adjust as needed
        $marginX = 0; // Adjust as needed
        $marginY = 0;
        $html = "";
        foreach ($FetchApplicationDetails as $index => $row)
        {
            $rowNumber = floor($index / $cardsPerRow);
            $colNumber = $index % $cardsPerRow;
            $positionX = $colNumber * ($cardWidth + $marginX);
            $positionY = $rowNumber * ($cardHeight + $verticalSpace);
            $mpdf->SetXY($positionX, $positionY);
            $html .= '<div style="display:flex;">';
            $html .= view("pdfview.front-view", compact("row"))->render();
            $html .= view("pdfview.back-view", compact("row"))->render();
            $html .= "</div>";
            $html .= '<div style="margin:0 0 10px 0;"></div>';
        }
        $mpdf->WriteHTML($html);
        // Save the PDF to a file (optional)
        // $filename = 'idcard-generated.pdf'; // Set your desired filename
        // $response = response($mpdf->Output($filename, 'S'))
        //     ->header('Content-Type', 'application/pdf');
        $pdfPath = public_path("pdfs/idcard-generated.pdf");
        $mpdf->Output($pdfPath, "F");
        EntityApplication::whereIn("id", $selectedIds)->update(["status" => 203, ]);
        return response()
            ->json(["pdfPath" => $pdfPath]);
    }
    public function applicationSearchView()
    {
        return view("entity.search")
            ->with([]);
    }
    public function getRenewEntityApplicationDetail($renewApplicationId)
    {
        $entityApplicationDetailData = EntityApplication::where("is_deleted", "No")->where("user_id", Auth::id())
            ->where("id", $renewApplicationId)->first();
        if (!empty($entityApplicationDetailData))
        {
            $entityApplicationDetailData->application_type = 1;
            return view("entity.create-new-application", compact("entityApplicationDetailData"))
                ->with([]);
        }
        else
        {
            return view("entity.search")
                ->with([]);
        }
    }
    public function searchEntityApplication(Request $request)
    {
        $search_application = $request->search_application;
        $entityApplicationData = EntityApplication::when($search_application, function ($query, $search_application)
        {
            return $query->where(function ($query) use ($search_application)
            {
                $query->where("mobile_number", "like", "" . $search_application . "%")->orWhere("serial_no", "like", "" . $search_application . "%");
            });
        })->where("is_deleted", "No")
            ->where("user_id", Auth::id())
            ->latest()
            ->get();
        return view("entity.search-table", compact("entityApplicationData"));
    }
    public function approveapp(Request $request)
    {
        EntityApplication::whereIn("id", $request->ids)
            ->update(["status" => 200, ]);
        foreach ($request->ids as $id)
        {
            $entityApplicationData = EntityApplication::findOrFail($id);
            Helper::statuChangeEmailCommonFunction($entityApplicationData);
        }
        return true;
    }
    public function getSurrenderEntityApplicationDetail($surrenderApplicationId)
    {
        $entityApplicationDetailData = EntityApplication::where("user_id", Auth::id())->where("id", $surrenderApplicationId)->first();
        if (!empty($entityApplicationDetailData))
        {
            $entityApplicationDetailData->application_type = 2;
            return view("entity.surrender-application", compact("entityApplicationDetailData"))
                ->with([]);
        }
        else
        {
            return view("entity.search")
                ->with([]);
        }
    }
    public function saveEntitySurrenderDetail(Request $request)
    {
        $input = $request->except('_token'); // Exclude the token directly
        $file = $input["surrender_signature_hidden"] ?? null;
        $fileDir = config("constant.ENTITY_APPLICATION_SURRENDER_SIGNATURE_PATH");
        $filePath = public_path("upload") . DIRECTORY_SEPARATOR . $fileDir;
        // Create directory if it doesn't exist
        File::makeDirectory($filePath, 0777, true, true);
        // Handle the signature file
        if ($file) {
            $this->handleSignatureFile($file, $input, $filePath);
        } else {
            $input["surrender_signature"] = "";
        }
        // Update the entity application
        $this->entityApplication->where("id", $input["id"])->update($input);
        // Send email notification
        $this->sendEmailNotification($input["user_id"], $input["id"]);
        return response()->json([
            "result" => true,
            "message" => 'Entity Application\'s detail submitted',
            "entityApplicationId" => $input["id"],
            "user_id" => $input["user_id"],
        ]);
    }
    private function handleSignatureFile($file, array &$input, string $filePath)
    {
        $base64Data = substr($file, strpos($file, ",") + 1);
        $binaryData = base64_decode($base64Data);
        $fileName = $input["id"] . "-surrender-signature.png";
        if (strpos($file, "data:image/") !== false) {
            file_put_contents($filePath . "/" . $fileName, $binaryData);
            $input["surrender_signature"] = $fileName;
        } else {
            $input["surrender_signature"] = $file;
        }
        // Clean up previous signature if exists
        if (!empty($input["previous_surrender_signature_hidden"])) {
            $previousFilePath = $filePath . "/" . $input["previous_surrender_signature_hidden"];
            if (file_exists($previousFilePath)) {
                unlink($previousFilePath);
            }
        }
        unset($input["previous_surrender_signature_hidden"], $input["surrender_signature_hidden"]);
    }
    private function sendEmailNotification($userId, $applicationId)
    {
        $entityData = Helper::getEntityDetail($userId);
        $entityApplicationDetail = $this->entityApplication->findOrFail($applicationId);
        $applicationType = Helper::getEntityApplicationType($entityApplicationDetail['application_type']);
        $emailData = [
            'email' => $entityData->email,
            'data' => $entityApplicationDetail,
            'viewFile' => 'emails.entityapplicationstatuschange',
            'mailType' => 'surrenderEntityApplication',
            'subject' => 'Card Management - Entity ' . $applicationType . ' Application',
            'errorLogChannel' => 'entity-application-status',
        ];
        Helper::sendMail($emailData);
    }
    public function getDraftEntityApplicationDetail($draftApplicationId)
    {
        $entityApplicationDetailData = EntityApplication::where("user_id", Auth::id())
            ->where("id", $draftApplicationId)
            ->first();
        if ($entityApplicationDetailData) {
            $entityApplicationDetailData->application_type = 0;
            $view = $entityApplicationDetailData->type === "Other"
                ? "specialentity.create-new-application"
                : "entity.create-new-application";
            return view($view, compact("entityApplicationDetailData"));
        }
        return view("entity.search");
    }
    public function entityProfileImageChange(Request $request)
    {
        // Validate the request
        $request->validate([
            "entityProfileImage" => "required|image|mimes:jpeg,png,jpg,gif|max:1048",
        ]);
        $entityId = Auth::id();
        $uploadedImage = $request->file("entityProfileImage");
        $destinationPath = public_path("upload/entity-data/entity-profile/");
        // Generate a new file name
        $imageName = "{$entityId}_" . time() . ".{$uploadedImage->getClientOriginalExtension()}";
        // Find the user and update the image
        $updateEntity = User::findOrFail($entityId);
        $previousImage = $updateEntity->image;
        // Update the image name in the database
        $updateEntity->image = $imageName;
        $updateEntity->save();
        // Move the uploaded image to the destination path
        $uploadedImage->move($destinationPath, $imageName);
        // Delete the previous image if it exists
        if ($previousImage) {
            $previousFilePath = public_path("upload/entity-data/entity-profile/{$previousImage}");
            if (file_exists($previousFilePath)) {
                unlink($previousFilePath);
            }
        }
        // Return a success response
        return response()->json([
            "message" => "Entity profile updated successfully",
            "filePath" => asset("upload/entity-data/entity-profile/{$imageName}"),
        ]);
    }
    public function entityQrEntityApplicationScanDataView($entityApplicationId)
    {
        try {
            $entityApplicationId = decrypt($entityApplicationId);
            // Use eager loading to reduce the number of queries
            $row = EntityApplication::with(['user' => function($query) {
                $query->select('id', 'company_name', 'authorized_person_first_name', 'authorized_person_last_name', 'unit_category');
            }])
            ->select('entity_applications.*')
            ->where('id', $entityApplicationId)
            ->where('is_deleted', 'No')
            ->first();
            if (!$row) {
                return false;
            }
            // Use firstOrCreate to simplify the scan creation logic
            Scan::firstOrCreate(
                [
                    'application_id' => $row->id,
                    'scanned_at' => Carbon::today()
                ],
                [
                    'serial_no' => $row->serial_no,
                    'scanned_at' => now(),
                ]
            );
            // Ensure the directory exists
            $directory = public_path("pdfs");
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true); // Added permissions and recursive creation
            }
            $mpdf = new Mpdf(["format" => "A4-P"]);
            // Prepare HTML content
            $html = view("pdfview.qr-scan-card-view", compact("row"))->render();
            $html = '<div class="d-flex mx-auto card-scan-preview-outer-div" style="gap:10px;">' . $html . '</div>';
            // Add application status header
            $html = '<div style="gap:10px;align-self: center;">' .
                    '<h1 class="' . Helper::getApplicationStatusBackgroundColor($row->status) . '" style="font-weight:bolder;">' .
                    Helper::getApplicationType($row->status) . "</h1></div>" . $html;
            $mpdf->WriteHTML($html);
            return $html;
        } catch (\Exception $e) {
            // Log the exception instead of dumping
            Log::error($e);
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
    public function verifiedapp(Request $request)
    {
        $ids = $request->input('ids', [$request->input('id')]);
        if (!empty($ids)) {
            EntityApplication::whereIn('id', $ids)->update(['status' => 204]);
            $entityApplications = EntityApplication::whereIn('id', $ids)->get();
            foreach ($entityApplications as $entityApplication) {
                Helper::statuChangeEmailCommonFunction($entityApplication);
            }
            return true;
        }
    }
    public function sendbackApplication(Request $request)
    {
        $entityApplicationData = EntityApplication::findOrFail($request->application_id);
        $entityApplicationData->update([
            'status' => $request->status,
            'comment' => $request->comment,
        ]);
        Helper::statuChangeEmailCommonFunction($entityApplicationData);
        return back();
    }
    public function surrenderverifiedapp(Request $request)
    {
        return $this->updateApplicationStatus($request->id, 206);
    }
    public function terminateverifiedapp(Request $request)
    {
        return $this->updateApplicationStatus($request->id, 255);
    }
    public function blockorunblockapp(Request $request)
    {
        $ids = $request->input('ids', [$request->input('id')]);
        if (!empty($ids)) {
            EntityApplication::whereIn('id', $ids)->update(['status' => $request->status]);
            $entityApplications = EntityApplication::whereIn('id', $ids)->get();
            foreach ($entityApplications as $entityApplication) {
                Helper::statuChangeEmailCommonFunction($entityApplication);
            }
            return true;
        }
    }
    private function updateApplicationStatus($id, $status)
    {
        if ($id) {
            EntityApplication::where('id', $id)->update(['status' => $status]);
            $entityApplicationData = EntityApplication::findOrFail($id);
            Helper::statuChangeEmailCommonFunction($entityApplicationData);
            return true;
        }
    }
    public function commonCheckEntityApplicationDataExists($request)
    {
        $userId = Auth::id();
        $formattedMobileNumber = preg_replace("/\s+/", "", $request->mobile_number);
        $fullNameWSpace = trim($request->first_name . " " . $request->last_name);
        $query = $this->entityApplication->where('is_deleted', 'No')
            ->where('user_id', $userId);
        // Combine name checks into a single where clause
        $query->where(function ($query) use ($request, $fullNameWSpace) {
            $query->where('first_name', $request->first_name)
                ->orWhere('first_name', $request->first_name . $request->last_name)
                ->orWhere('first_name', $fullNameWSpace);
        });
        // Combine last name checks into a single where clause
        $query->where(function ($query) use ($request) {
            $query->where('last_name', $request->last_name)
                ->orWhere('last_name', '')
                ->orWhereNull('last_name');
        });
        // Combine mobile number checks into a single where clause
        $query->where(function ($query) use ($request, $formattedMobileNumber) {
            $query->where('mobile_number', $request->mobile_number)
                ->orWhere('mobile_number', $formattedMobileNumber);
        });
        // Exclude the current entity if an ID is provided
        if (!empty($request->id)) {
            $query->where('id', '!=', $request->id);
        }
        return $query->first();
    }
}
