<?php
namespace App\Services;

use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use Carbon\Carbon;

class ExportService
{
    public $dateColumns = [
        'created_at',
        'updated_at'
    ];

    protected function export($user, $input, $query, $exports, $fileName)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(config('app.name'))
                    ->setLastModifiedBy('System')
                    ->setTitle($fileName)
                    ->setSubject($fileName);   

        $sheet = $spreadsheet->getActiveSheet();

        $headers = array_values($exports);
        $col     = 'A';
        $headerToColumnMap = [];
        foreach( $headers as $h ){
            $headerToColumnMap[$h] = $col;
            $sheet->setCellValue($col . '1', $h);
            $col ++;
        }
        $sheet->getStyle("A1:{$col}1")->getFont()->setBold(true); // Make header bold

        $row   = 2;
        $start = 0;
        $limit = 2500;
        while( true ){
            $_query  = clone $query;
            $results = $_query->limit($limit)
                              ->offset($start)
                              ->get();

            if( ! count($results) )
                break;
            
            foreach( $results as $idx => $result ){
                $data = $result->toArray();
                foreach($data as $prop => $value){
                    $header = $exports[$prop] ?? null;
                    if( $header ){
                        if( $value && in_array($prop, $this->dateColumns ) ){
                            $date = new Carbon($value);
                            $date->setTimeZone($user->timezone);
                            $value = $date->format('Y-m-d g:ia');
                        }
                        $sheet->setCellValue($headerToColumnMap[$header] . $row, $value);
                    }
                }
                $row++;
            }

            if( count($results) < $limit ) // Skip additional query if we're clearly at the end already
                break;

            $start += $limit;
        }
        
        //  Resize columns to fit data
        $resizeCol = 'A';
        while( $resizeCol != $col ){
            $sheet->getColumnDimension($resizeCol)->setAutoSize(true);
            $resizeCol++;
        }

        return new Xlsx($spreadsheet);
    }

    public function exportAsFile($user, $input, $query, $exports, $fileName)
    {
        $writer      = $this->export($user, $input, $query, $exports, $fileName);
        $storagePath = storage_path(str_random(40));

        $writer->save($storagePath);

        return $storagePath;
    }

    public function exportAsOutput($user, $input, $query, $exports, $fileName)
    {
        $writer = $this->export($user, $input, $query, $exports, $fileName);
    
        return $this->save($writer, $fileName);
    }

    public function save($writer, $fileName)
    {
        ob_start();
        
        $writer->save('php://output');
        
        return response(ob_get_clean())->withHeaders([
            'X-Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Type'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment;filename="'.$fileName.'.xlsx"',
            'Cache-Control' => 'max-age=0'
        ]);
    }
}