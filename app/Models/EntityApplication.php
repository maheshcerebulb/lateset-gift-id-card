<?php
namespace App\Models;
use Auth;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EntityApplication extends Model
{
    use HasFactory;
    const ENTITY_APPLICATION_APPROVED                   = 200;
    const ENTITY_APPLICATION_DRAFT                      = 201;
    const ENTITY_APPLICATION_SUBMITTED                  = 202;
    const ENTITY_APPLICATION_REJECTED                   = 500;
    const ENTITY_APPLICATION_EXPIRED                    = 501;
    const ENTITY_APPLICATION_SUBMITTED_FOR_SURRENDER    = 401;
    const ENTITY_APPLICATION_DEACTIVATED                = 502;
    const ENTITY_APPLICATION_READY_TO_COLLECT           = 203;
    protected $fillable = [
        'first_name',
        'last_name',
        'designation',
        'gender',
        'email',
        'mobile_number',
        'application_number',
        'date_of_birth',
        'type',
        'sub_type',
        'contractor_name',
        'expire_date',
        'application_type',
        'surrender_reason',
        'surrender_signature',
        'surrender_comment',
        'user_id',
        'status',
        'qrcode',
        'serial_no',
        'image',
        'signature',
        'dial_code',
        'country_code',
        'issue_date',
        'created_at',
		'department',
        'other_entity',
        'app_unique_id',
        'is_deleted'
    ];

    public function user()
    {
        return $this->belongsTo(User::class); // Make sure the user_id is the correct foreign key
    }
    public function getFullNameAttribute()
    {
        // return "{$this->first_name} {$this->last_name}";
        return $this->attributes['first_name'] . ' ' . $this->attributes['last_name'];
    }
    public function setFullnameAttribute($value)
    {
        $names = explode(' ', $value, 2);
        $this->attributes['first_name'] = $names[0];
        $this->attributes['last_name'] = isset($names[1]) ? $names[1] : null;
    }
    public static function getTableDataWithEntity()
    {
        if(Auth::user()->getRoleNames()->first() == 'Admin')
        {
            $recentEntityApplicationData = self::select('entity_applications.*',DB::raw("CONCAT(user.authorized_person_first_name,' ', user.authorized_person_last_name) AS entity_name"))
                            ->leftjoin('users as user','user.id','=','entity_applications.user_id')
                            ->whereNotIn('status', [201,202,205])
                            ->where('entity_applications.is_deleted','No')
                            ->latest()->take(5)->get();
            return $recentEntityApplicationData;
        }
        else
        {
            $recentEntityApplicationData = self::select('entity_applications.*',DB::raw("CONCAT(user.authorized_person_first_name,' ', user.authorized_person_last_name) AS entity_name"))
                                        ->leftjoin('users as user','user.id','=','entity_applications.user_id')
                                        ->whereNotIn('entity_applications.status', [201])
                                        ->where('entity_applications.is_deleted','No')
                                        ->latest()->take(5)->get();
            return $recentEntityApplicationData;
        }
    }
    public static function excelUploadedDataDateFormate($excelDate)
    {
        $date = ($excelDate - 25569) * 86400;
        // Format the Unix timestamp to Y-m-d
        return date('Y-m-d',$date);
    }
    public static function insertEntityApplicationData($data)
    {
        return self::insertGetId((array) $data);
    }
    public static function filterBaseCompanyData($filter){
        $filters = explode(',', $filter);
        $buildingDataQuery = User::select('users.id', 'users.company_name', 'users.company_address', 'users.company_building')
            ->whereNotIn('company_name', ['cerebulb'])
            ->groupBy('users.id', 'users.company_name', 'users.company_address', 'users.company_building');
            // Apply filter if not '0'
            if (in_array('0', $filters)) {
                $buildingData = $buildingDataQuery->get();
            } else {
                $buildingData = $buildingDataQuery->whereIn('users.company_building', $filters)->get();
            }
            // Process and sort unique company names
            return $buildingData
                ->sortBy('company_name')
                ->unique('company_name')
                ->pluck('company_name')
                ->toArray();
    }
    public static function filterBuildingData(){
        $filterBuildingData=User::select('users.id', 'users.company_name','users.company_address','users.company_building')
            ->join('entity_applications', 'users.id', '=', 'entity_applications.user_id')
            ->whereNotIn('company_name', ['cerebulb'])
            ->groupBy('users.id', 'users.company_name','users.company_address','users.company_building')
            ->get();
            foreach ($filterBuildingData as $data) {
                $address = $data->company_address;
                // Check if the address already exists in the array
                if (!isset($groupedByBuilding[$address])) {
                    $groupedByBuilding[$address] = [];
                }
                // Add company to the respective building group
                $groupedByBuilding[$address][] = [
                    'id' => $data->id,
                    'company_name' => $data->company_name,
                ];
            }
        $uniqueBuildings = $filterBuildingData
            ->sortBy('company_building') // or 'application_count' if you need to sort by a different field
            ->unique('company_building');
            $uniqueBuildingsArray = $uniqueBuildings->values();
            $uniqueBuildings->values()->all();
            return $uniqueBuildings;
    }
    public static function yearList(){
        return self::select([DB::raw('extract(year FROM created_at) AS year')])->distinct()->get();
    }
    public static function getBuildingList (){
        return User::select('users.company_building')
        ->join('entity_applications', 'users.id', '=', 'entity_applications.user_id')
        ->whereNotNull('users.company_building')  // Ensure company_building is not NULL
        ->distinct()  // Ensures unique company_building values
        ->orderBy('users.company_building', 'asc')
        ->get();
    }
    public static function getCompanyList($filter){
        $filters = explode(',', $filter);
        $buildingDataQuery = User::select('users.id', 'users.company_name', 'users.company_address', 'users.company_building')
            ->join('entity_applications', 'users.id', '=', 'entity_applications.user_id')
            ->whereNotNull('users.company_name')  // Ensure company_name is not NULL
            ->distinct()
            ->orderBy('users.company_name', 'asc');
        if (in_array('0', $filters)) {
            $buildingData = $buildingDataQuery->get();
        } else {
            $buildingData = $buildingDataQuery->whereIn('users.company_building', $filters)->get();
        }
        return $buildingData;
    }
}
