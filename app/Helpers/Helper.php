<?php

namespace App\Helpers;

use App\Mail\SendMailable;
use App\Models\LiqourApplication;
use App\Models\User;
use App\Models\EntityApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Mail\DefaultMail;
use Illuminate\Support\Facades\Config;

class Helper
{
    public static function getApplicationType($code)
    {
        switch ($code) {
            case 200:
                return "Approved";
            case 201:
                return "Draft";
            case 202:
                return "Submitted";
            case 500:
                return "Rejected";
            case 501:
                return "Expired";
            case 401:
                return "Surrendered";
            case 502:
                return "Deactivated";
            case 203:
                return "Activated";
            case 204:
                return "Verified";
            case 205:
                return "Send Back";
            case 206:
                return "Hard copy submitted";
            case 255:
                return "Terminated";
            case 403:
                return "Blocked";
            default:
                return "Undefined";
        }
    }

    public static function getApplicationCode($appType)
    {
        $types = [
            "Approved" => 200,
            "Draft" => 201,
            "Submitted" => 202,
            "Rejected" => 500,
            "Expired" => 501,
            "Surrendered" => 401,
            "Deactivated" => 502,
            "Activated" => 203,
            "Verified" => 204,
            "Send Back" => 205,
            "Hard copy submitted" => 206,
            "Terminated" => 255,
            "Blocked" => 403
        ];

        return $types[$appType] ?? "Undefined";
    }

    public static function getEntityDetail($userId)
    {
        return User::find($userId);
    }

    public static function getApplicationCount()
    {
        $role = Auth::user()->getRoleNames()->first();
        $dataCount = EntityApplication::select(
            DB::raw($role === 'Entity'
            ? 'COUNT(entity_applications.id) AS total_count'
            : 'SUM(CASE WHEN entity_applications.status != 201 THEN 1 ELSE 0 END) AS total_count'),
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
        ->leftJoin('users as user', 'user.id', '=', 'entity_applications.user_id');

        if ($role === 'Entity') {
            $dataCount->where('entity_applications.user_id', Auth::id());
        }

        $dataCount->where('entity_applications.is_deleted', 'No');

        // Fetch the result
        return $dataCount->first();

    }

    public static function getApplicationStatusBackgroundColor($code)
    {
        $statusClasses = [
            200 => "background-light-cyon text-cyon",
            201 => "background-light-cyon text-cyon",
            202 => "background-light-purple text-purple",
            500 => "background-light-red text-red",
            501 => "background-light-red text-red",
            401 => "background-light-red text-red",
            502 => "background-light-red text-red",
            203 => "background-light-cyon text-cyon",
            204 => "background-light-yellow text-white",
            205 => "background-light-sky-blue text-white",
            206 => "background-dark-red text-white",
            255 => "background-dark-black text-white",
            403 => "background-dark-black text-white"
        ];

        return $statusClasses[$code] ?? "Undefined";
    }

    public static function getSuperAdminData()
    {
        $roleName = 'Entity';

        $totalUnit = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->count();

        $totalEmp = EntityApplication::where('is_deleted', 'No')->count();
        $activeIds = EntityApplication::where('is_deleted', 'No')->whereIn('status', [200, 203])->count();
        $inactiveIds = EntityApplication::where('is_deleted', 'No')->whereIn('status', [255, 501, 502])->count();

        return [
            'totalUnit' => str_pad($totalUnit, 2, '0', STR_PAD_LEFT),
            'totalEmp' => str_pad($totalEmp, 2, '0', STR_PAD_LEFT),
            'ActiveIds' => str_pad($activeIds, 2, '0', STR_PAD_LEFT),
            'InactiveIds' => str_pad($inactiveIds, 2, '0', STR_PAD_LEFT)
        ];
    }

    public static function getUnitData($address)
    {
        $roleName = 'Entity';

        $entityData = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->where('company_address', $address);

        $entities = $entityData->pluck('id');
        $totalUnit = $entityData->count();
        $totalEmp = EntityApplication::where('is_deleted', 'No')->whereIn('user_id', $entities->toArray())->count();
        $activeIds = EntityApplication::where('is_deleted', 'No')->whereBetween('status', [200, 210])->whereIn('user_id', $entities->toArray())->count();
        $inactiveIds = EntityApplication::where('is_deleted', 'No')->whereIn('status', [502, 501])->whereIn('user_id', $entities->toArray())->count();

        return [
            'totalUnit' => str_pad($totalUnit, 2, '0', STR_PAD_LEFT),
            'totalEmp' => str_pad($totalEmp, 2, '0', STR_PAD_LEFT),
            'ActiveIds' => str_pad($activeIds, 2, '0', STR_PAD_LEFT),
            'InactiveIds' => str_pad($inactiveIds, 2, '0', STR_PAD_LEFT)
        ];
    }

    public static function getEntityApplicationType($applicationtype)
    {
        $types = [
            0 => 'New',
            1 => 'Renew',
            2 => 'Surrender'
        ];

        return $types[$applicationtype] ?? '';
    }

    public static function getDataEntryAdminData()
    {
        $roleName = 'Entity';

        $totalUnit = User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->count();

        $totalApplies = LiqourApplication::count();
        $currentDate = Carbon::now();

        // Get start and end dates for the current week and month
        $startOfWeek = $currentDate->startOfWeek()->format('Y-m-d H:i:s');
        $endOfWeek = $currentDate->endOfWeek()->format('Y-m-d H:i:s');
        $startOfMonth = $currentDate->startOfMonth()->format('Y-m-d H:i:s');
        $endOfMonth = $currentDate->endOfMonth()->format('Y-m-d H:i:s');

        $endOfLastMonth = Carbon::now()->subMonths(1)->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonths(2)->startOfMonth();

        // Get counts for each period
        $monthlyCount = LiqourApplication::whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count();
        $lastTwoMonthlyCount = LiqourApplication::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        return [
            'totalApplies' => str_pad($totalApplies, 2, '0', STR_PAD_LEFT),
            'monthlyCount' => str_pad($monthlyCount, 2, '0', STR_PAD_LEFT),
            'lastTwoMonthlyCount' => str_pad($lastTwoMonthlyCount, 2, '0', STR_PAD_LEFT)
        ];
    }

    public static function expiredStatusChangeEmailCommonFunction($entityApplicationData)
    {
        Log::channel('entity-application-status')->info("Helper expiredStatusChangeEmailCommonFunction called: " . date("Y-m-d H:i:s"));

        try {
            // Check if the input is a collection
            if ($entityApplicationData instanceof \Illuminate\Support\Collection) {
                foreach ($entityApplicationData as $application) {
                    // Check if the expiration date is today or has passed
                    if (date('Y-m-d', strtotime($application->expire_date)) == date('Y-m-d') || date('Y-m-d', strtotime($application->expire_date)) <= date('Y-m-d')) {
                        $mailData = [
                            'data' => $application,
                            'mailType' => 'statusChangeEntityApplication'
                        ];
                    } else {
                        $mailData = [
                            'data' => $application,
                            'mailType' => 'statusChangeEntityApplicationTwoDaysEarly'
                        ];
                    }

                    // Get entity details
                    $getEntityData = Helper::getEntityDetail($application->user_id);
                    $entityMail = $getEntityData->email;

                    Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.3) Application Id: " . $application->id . " Email Send Start:");

                    try {
                        // Send the email
                        Mail::to($entityMail)
                            ->bcc(config('constant.CC_ADMIN_EMAIL_ADDRESS'))
                            ->send(new SendMailable($mailData));

                        Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $application->id . " Email Sent Successfully:");
                    } catch (\Exception $e) {
                        Log::channel('entity-application-status')->error("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $application->id . " Email Send Failed: " . $e->getMessage());
                    }

                    Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $application->id . " Email Send Ends:");
                }
            } else {
                // For single entity data
                if (date('Y-m-d', strtotime($entityApplicationData->expire_date)) == date('Y-m-d') || date('Y-m-d', strtotime($entityApplicationData->expire_date)) <= date('Y-m-d')) {
                    $mailData = [
                        'data' => $entityApplicationData,
                        'mailType' => 'statusChangeEntityApplication'
                    ];
                } else {
                    Log::channel('entity-application-status')->info("Helper Two Days Early Expire Data: " . date("Y-m-d H:i:s") . " Condition Called");

                    $mailData = [
                        'data' => $entityApplicationData,
                        'mailType' => 'statusChangeEntityApplicationTwoDaysEarly'
                    ];
                }

                // Get entity details
                $getEntityData = Helper::getEntityDetail($entityApplicationData->user_id);
                $entityMail = $getEntityData->email;

                Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.3) Application Id: " . $entityApplicationData->id . " Email Send Start:");

                try {
                    // Send the email
                    Mail::to($entityMail)
                        ->bcc(config('constant.CC_ADMIN_EMAIL_ADDRESS'))
                        ->send(new SendMailable($mailData));

                    Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $entityApplicationData->id . " Email Sent Successfully:");
                } catch (\Exception $e) {
                    Log::channel('entity-application-status')->error("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $entityApplicationData->id . " Email Send Failed: " . $e->getMessage());
                }

                Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id: " . $entityApplicationData->id . " Email Send Ends:");
            }
        } catch (\Throwable $th) {
            Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.4) Email Data Failed:");
            Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.4) Email Data Failed Reason: " . "\n" . json_encode($th->getMessage()));
        }
    }

    public static function bccExpiredStatusChangeEmailCommonFunction($entityApplicationData)
    {

        Log::channel('entity-application-status')->info("Helper expiredstatuChangeEmailCommonFunction called: " .date("Y-m-d H:i:s") ."");

        try {

            if ($entityApplicationData instanceof \Illuminate\Support\Collection)
            {

                foreach ($entityApplicationData as $application)
                {

                    $mailData = array(
                        'data' => $application,
                        'mailType' => 'statusChangeEntityApplication',
                        // 'expireApplicationSubject' => 'Expiration'
                    );

                    Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s")." (2.1.3) Application Id :".$application->id." Email Send Start: ");
                    try {

                        Mail::to('rushabh.patodia@yopmail.com')->bcc(config('constant.CC_ADMIN_EMAIL_ADDRESS'))->send(new SendMailable($mailData));

                        Log::channel('entity-application-status')->info("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id : " . $application->id . " Email Sent Successfully: ");
                    } catch (\Exception $e) {
                        Log::channel('entity-application-status')->error("Time: " . date("Y-m-d H:i:s") . " (2.1.5) Application Id : " . $application->id . " Email Send Failed: " . $e->getMessage());
                    }
                    Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1.5) Application Id :".$application->id." Email Send Ends:");
                }
            }


        } catch (\Throwable $th) {
           Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1.4) Email Data Failed : ");
            Log::channel('entity-application-status')->info("Time: " .date("Y-m-d H:i:s") ." (2.1.4) Email Data Failed Reason: "."\n".json_encode($th->getMessage()));
        }

    }

    public static function sendMail($emailData)
    {
        try {
            $emailData['signature'] = Config::get('constant.SIGNATURE');
            $emailData['emailNote'] = Config::get('constant.EMAIL_NOTE');

            Mail::to($emailData['email'])->send(new DefaultMail($emailData));
			Mail::to(Config::get('constant.CC_ADMIN_EMAIL_ADDRESS'))->send(new DefaultMail($emailData));
            return 1;
        } catch (\Exception $e) {
            //
        }
    }
}
