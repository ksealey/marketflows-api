<?php

namespace App\Services;

class ReportService
{
    use \App\Traits\Helpers\HandlesDateFilters;

    public $conditionFields = [
        'calls.type',
        'calls.category',
        'calls.sub_category',
        'calls.source',
        'calls.medium',
        'calls.content',
        'calls.campaign',
        'calls.keyword',
        'calls.forwarded_to',
        'calls.duration',
        'calls.is_paid',
        'calls.is_organic',
        'calls.is_direct',
        'calls.is_search',
        'calls.is_referral',
        'calls.is_remarketing',
        'calls.first_call',
        'calls.recording_enabled',
        'calls.transcription_enabled',
        'contacts.first_name',
        'contacts.last_name',
        'contacts.number',
        'contacts.city',
        'contacts.state',
    ];

    public $fieldAliases = [
        'calls' => [
            'calls.type'                => 'call_type',
            'calls.category'            => 'call_category',
            'calls.sub_category'        => 'call_sub_category',
            'calls.source'              => 'call_source',
            'calls.medium'              => 'call_medium',
            'calls.content'             => 'call_content',
            'calls.campaign'            => 'call_campaign',
            'calls.keyword'             => 'call_keyword',
            'calls.recording_enabled'   => 'call_recording_enabled',
            'calls.forwarded_to'        => 'call_forwarded_to',
            'calls.duration'            => 'call_duration',
            'calls.first_call'          => 'call_first_call',
            'calls.recording_enabled' => 'call_recording_enabled',
            'calls.transcription_enabled' => 'call_transcription_enabled',
            'calls.is_paid'             => 'call_is_paid',
            'calls.is_organic'          => 'call_is_organic',
            'calls.is_referral'         => 'call_is_referral',
            'calls.is_remarketing'      => 'call_is_remarketing',
            'calls.is_direct'           => 'call_is_direct',
            'calls.is_search'           => 'call_is_search',
            'contacts.first_name'       => 'contact_first_name',
            'contacts.last_name'        => 'contact_last_name',
            'contacts.country_code'     => 'contact_country_code',
            'contacts.number'           => 'contact_number',
            'contacts.city'             => 'contact_city',
            'contacts.state'            => 'contact_state',
            'calls.created_at'          => 'call_date',
        ]
    ];

    public $fieldLabels = [
        'calls' => [
            'call_type'                => 'Type',
            'call_category'            => 'Category',
            'call_sub_category'        => 'Sub-Category',
            'call_source'              => 'Source',
            'call_medium'              => 'Medium',
            'call_content'             => 'Content',
            'call_campaign'            => 'Campaign',
            'call_keyword'             => 'Keyword',
            'call_recording_enabled'   => 'Recording Enabled',
            'call_forwarded_to'        => 'Forwarded To Phone Number',
            'call_duration'            => 'Call Duration',
            'call_first_call'          => 'First Time Caller',
            'call_recording_enabled'   => 'Recording Enabled',
            'call_transcription_enabled' => 'Transcription Enabled',
            'call_is_paid'             => 'Is Paid',
            'call_is_organic'          => 'Is Organic',
            'call_is_referral'         => 'Is Referral',
            'call_is_remarketing'      => 'Is Remarketing',
            'call_is_direct'           => 'Is Direct',
            'call_is_search'           => 'Is Search',
            'call_date'                => 'Call Date',
            'contact_first_name'       => 'Caller First Name',
            'contact_last_name'        => 'Caller Last Name',
            'contact_country_code'     => 'Caller Country Code',
            'contact_number'           => 'Caller Phone Number',
            'contact_city'             => 'Caller City',
            'contact_state'            => 'Caller State',
        ]
    ];

    public $fieldTypes = [
        'calls' => [
            'calls.type'                => 'string',
            'calls.category'            => 'string',
            'calls.sub_category'        => 'string',
            'calls.source'              => 'string',
            'calls.medium'              => 'string',
            'calls.content'             => 'string',
            'calls.campaign'            => 'string',
            'calls.keyword'             => 'string',
            'calls.recording_enabled'   => 'boolean',
            'calls.forwarded_to'        => 'string',
            'calls.duration'            => 'integer',
            'calls.first_call'          => 'boolean',
            'calls.recording_enabled' => 'boolean',
            'calls.transcription_enabled' => 'boolean',
            'calls.is_paid'             => 'boolean',
            'calls.is_organic'          => 'boolean',
            'calls.is_referral'         => 'boolean',
            'calls.is_remarketing'      => 'boolean',
            'calls.is_direct'           => 'boolean',
            'calls.is_search'           => 'boolean',
            'contacts.first_name'       => 'string',
            'contacts.last_name'        => 'string',
            'contacts.number'           => 'string',
            'contacts.city'             => 'string',
            'contacts.state'            => 'string',
        ]
    ];

    public $dateFields = [
        'calls' => [
            'call_date' => 'M j, Y g:ia'
        ]
    ];

    public $booleanFields = [
        'calls' => [
            'call_recording_enabled',
            'call_first_call',
            'call_is_paid',
            'call_is_organic',
            'call_is_referral',
            'call_is_remarketing',
            'call_is_direct',
            'call_is_search'
        ]
    ];

    public $moduleLabels = [
        'calls' => 'Calls'
    ];

    public function formatData($reportType, $reportData, $startDate, $endDate)
    {
        $data = [];

        if( $reportType === 'line' ){
            $data = $this->lineDatasetData($reportData, $startDate, $endDate);
        }elseif( $reportType === 'bar' ){
            $groupKeys = array_column($reportData->toArray(), 'group_by');
            $data      = $this->barDatasetData($reportData, $groupKeys);
        }

        return $data;
    }

    public function lineDatasetData(iterable $reportData, $startDate, $endDate)
    {
        $dataset = [];
        $diff    = $startDate->diff($endDate);
       
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getComparisonFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;

        $data = [];
        foreach($reportData as $d){
            $data[$d->group_by] = $d->count;
        }
        
        while( $dateIncrementor->format($comparisonFormat) <= $endDate->format($comparisonFormat) ){
            $dataset[] = [
                'label' => $dateIncrementor->format($displayFormat),
                'y'     => $data[$dateIncrementor->format($comparisonFormat)] ?? 0
            ];
            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $dataset;
    }

    public function barDatasetData(iterable $reportData, iterable $groupKeys, $condense = true)
    {
        $dataset   = [];
        $lookupMap = [];
        $dataType  = '';
        foreach($reportData as $data){
            $dataType = $data->group_by_type;
            $lookupMap[$data->group_by] = $data->count;
        }

        
        if( ! $condense ){
            foreach($groupKeys as $idx => $groupKey){
                $label = $groupKey;
                if( $dataType == 'boolean' ){
                    $label = $label ? 'Yes' : 'No';
                }
                $value     = $lookupMap[$groupKey] ?? 0;
                $dataset[] = [
                    'label' => $label,
                    'y'     => $lookupMap[$groupKey] ?? 0
                ];
            }
            return $dataset;
        }

        $otherCount = 0;
        $hasOthers  = false;
        foreach($groupKeys as $idx => $groupKey){
            $value = $lookupMap[$groupKey] ?? 0;
            $label = $groupKey;
            if( $dataType == 'boolean' ){
                $label = $label === null ? 'Not Set' : ($label ? 'Yes' : 'No');
            }
            
            if( $idx >= 9 ){
                $otherCount += $value;
                $hasOthers  = true;
            }else{
                $dataset[] = [
                    'label' => $label,
                    'y'     => $lookupMap[$groupKey] ?? 0
                ];
            }
        }

        if( $hasOthers ){
            $dataset[] = [
                'label' => 'Other',
                'y'     => $otherCount
            ];
        }
 
        return $dataset;
    }

    public function countLabels($inputData, $condense = false)
    {
        $values = array_map(function($input){
            if( $input->group_by_type === 'integer' ){
                return strval($input->group_by);
            }
            if( $input->group_by_type === 'boolean' ){
                return $input->group_by === null ? 'Not Set' : ($input->group_by ? 'Yes' : 'No');
            }
            return  strval($input->group_by);
        }, $inputData->toArray());

        $labels = array_values($values);
        if( $condense && count($values) > 10 ){
            $labels   = array_splice($labels, 0, 9);
            $labels[] = 'Other';
        }

        return $labels;
    }

    public function timeframeLabels($startDate, $endDate)
    {
        $labels             = [];
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getComparisonFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $diff               = $startDate->diff($endDate);
       
        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;
        while( $dateIncrementor->format($comparisonFormat) <= $end->format($comparisonFormat) ){
            $labels[] = $dateIncrementor->format($displayFormat);

            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $labels;
    }

    public function total(iterable $inputData)
    {
        return array_sum(array_column($inputData->toArray(), 'count'));
    }

   

    public function fieldAliases($module)
    {
        return $this->fieldAliases[$module];
    }

    public function fieldAlias($module, $column)
    {
        return $this->fieldAliases[$module][$column];
    }

    public function fieldColumn($module, $field)
    {
        foreach( $this->fields[$module] as $column => $alias ){
            if( $field === $alias ){
                return $column;
            }
        }

        return null;
    }

    public function fieldType($module, $field)
    {
        return $this->fieldTypes[$module][$field] ?? null;
    }

    public function fieldLabel($module, $fieldAlias)
    {
        return $this->fieldLabels[$module][$fieldAlias];
    }

    public function dateField($module, $fieldAlias)
    {
        return $this->dateFields[$module][$fieldAlias] ?? null;
    }

    public function booleanField($module, $fieldAlias)
    {
        return in_array($fieldAlias, $this->booleanFields[$module]);
    }

    public function moduleLabel($module)
    {
        return $this->moduleLabels[$module];
    }
}