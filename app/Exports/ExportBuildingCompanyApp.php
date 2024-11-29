<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\LiqourApplication; // Assuming LiqourApplication is your model

class ExportBuildingCompanyApp implements FromCollection, WithHeadings
{
    // Constructor to accept data
    public function __construct($data, $type)
    {
        $this->data = $data;
        $this->type = $type; // Type could be 'entity' or 'id-card' or others
    }

    // Return the collection of data to be exported
    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        // Define headings based on type
        if ($this->type === 'Id-cards') {
            return [
                'Employee Name',
                'DOB',
                'Gender',
                'Serial Number',
                'Company Unit',
                'Application Type',
                'Issue Date',
                'Expire Date',
                'Building',
                'Company Name',
            ];
        } else {
            return [
                'Company Name',
                'Entity Email',
                'Unit Category',
                'Application Number',
                'Registration Number',
                'Company Building',
                'Authorized Person',
                'Mobile Number',
            ];
        }
    }
}
