<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    use HasFactory;
    protected $table = 'id_card_scans';
    public $fillable = [
        'application_id',
        'serial_no',
        'scanned_at'
    ];
}
