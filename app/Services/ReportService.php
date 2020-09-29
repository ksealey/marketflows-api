<?php

namespace App\Services;

class ReportService
{
    use \App\Traits\Helpers\HandlesDateFilters;

    public $fieldAliases = [
        'calls' => [
            'calls.type'                => 'call_type',
            'calls.category'            => 'call_category',
            'calls.sub_category'        => 'call_sub_category',
            'calls.source'              => 'call_source',
            'calls.medium'              => 'call_medium',
            'calls.content'             => 'call_content',
            'calls.campaign'            => 'call_campaign',
            'calls.recording_enabled'   => 'call_recording_enabled',
            'calls.forwarded_to'        => 'call_forwarded_to',
            'calls.duration'            => 'call_duration',
            'calls.first_call'          => 'call_first_call',
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
            'call_recording_enabled'   => 'Recording Enabled',
            'call_forwarded_to'        => 'Forwarded To Phone Number',
            'call_duration'            => 'Call Duration',
            'call_first_call'          => 'First Time Caller',
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
            'calls.recording_enabled'   => 'boolean',
            'calls.forwarded_to'        => 'string',
            'calls.duration'            => 'integer',
            'calls.first_call'          => 'boolean',
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
            'call_first_call'
        ]
    ];

    public $moduleLabels = [
        'calls' => 'Calls'
    ];

    public function lineDatasetData(iterable $callData, $startDate, $endDate)
    {
        $dataset = [];
        $diff    = $startDate->diff($endDate);
       
        $timeIncrement      = $this->getTimeIncrement($startDate, $endDate);
        $comparisonFormat   = $this->getComparisonFormat($startDate, $endDate);
        $displayFormat      = $this->getDisplayFormat($startDate, $endDate);

        $dateIncrementor = clone $startDate;
        $end             = clone $endDate;

        $data = [];
        foreach($callData as $call){
            $data[$call->group_by] = $call->count;
        }
        
        while( $dateIncrementor->format($comparisonFormat) <= $endDate->format($comparisonFormat) ){
            $dataset[] = [
                'value' => $data[$dateIncrementor->format($comparisonFormat)] ?? 0
            ];
            $dateIncrementor->modify('+1 ' . $timeIncrement);
        }

        return $dataset;
    }

    public function barDatasetData(iterable $inputData, iterable $groupKeys, $condense = true)
    {
        $dataset   = [];
        $lookupMap = [];
        foreach($inputData as $data){
            $lookupMap[$data->group_by] = $data->count;
        }

        if( ! $condense ){
            foreach($groupKeys as $idx => $groupKey){
                $value     = $lookupMap[$groupKey] ?? 0;
                $dataset[] = [
                    'value' => $lookupMap[$groupKey] ?? 0
                ];
            }

            return $dataset;
        }

        $otherCount = 0;
        $hasOthers  = false;
        foreach($groupKeys as $idx => $groupKey){
            $value = $lookupMap[$groupKey] ?? 0;
            
            if( $idx >= 9 ){
                $otherCount += $value;
                $hasOthers  = true;
            }else{
                $dataset[] = [
                    'value' => $lookupMap[$groupKey] ?? 0
                ];
            }
        }

        if( $hasOthers ){
            $dataset[] = [
                'value' => $otherCount
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
                return $input->group_by ? 'Yes' : 'No';
            }
            return  $input->group_by;
        }, $inputData->toArray());

        $labels = [];

        if( ! $condense || count($values) <= 10 ){
            $labels = $values;
        }else{
            $labels   = $values;
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