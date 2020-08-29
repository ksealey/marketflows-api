<?php
namespace App\Services;

use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

class ExportService
{
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

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$fileName.'.xlsx"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
    }
}