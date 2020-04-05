<?php
namespace App\Traits;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use Spreadsheet;
use Xlsx;
use Worksheet;
use SpreadsheetSettings;
use DB;
use DateTime;
use DateTimeZone;

trait PerformsExport
{
    static public function exports() : array
    {
        return self::$exports;
    }

    static public function exportFileName() : string
    {
        return self::$exportFileName;
    }

    static public function onExport($results)
    {
        $exportName  = self::exportFileName();
        $exports     = self::exports();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($exportName)
                    ->setSubject($exportName);
                    
        $sheet = $spreadsheet->getActiveSheet();

        //  Set headers
        $headers = [];
        if( count($results) ){
            $sample = (array)$results[0];
            foreach($sample as $prop => $value){
                if( isset($exports[$prop]) )
                    $headers[] = $exports[$prop];
            }
        }else{
            $headers = array_values($exports);
        }
        
        $row     = 1;
        $col     = 'A';
        foreach( $headers as $header ){
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $row++;
        $sheet->getStyle("A1:{$col}1")->getFont()->setBold(true); // Make header bold

        //  Set data
        foreach( $results as $idx => $result ){
            $col  = 'A';
            $data = (array)$result;
            foreach($data as $prop => $value){
                if( isset($exports[$prop]) ){
                    $sheet->setCellValue($col . $row, $value);
                    $col++;
                } 
            }
            $row++;
        }
        
        //  Resize columns to fit data
        $resizeCol = 'A';
        while( $resizeCol != $col ){
            $sheet->getColumnDimension($resizeCol)->setAutoSize(true);
            $resizeCol++;
        }
       
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . preg_replace('/[^0-9A-z]+/', '-',  $exportName). '.xlsx' . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    static public function onChunkedExport($query) : string
    {
        $exportName  = self::exportFileName();
        $exports     = self::exports();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($exportName)
                    ->setSubject($exportName);
                    
        $sheet = $spreadsheet->getActiveSheet();

        //  Set headers
        $headers = [];
        $_query  = clone $query;
        if( $result = $_query->first() ){
            $sample = (array)$result;
            foreach($sample as $prop => $value){
                if( isset($exports[$prop]) )
                    $headers[] = $exports[$prop];
            }
        }else{
            $headers = array_values($exports);
        }
        
        $row     = 1;
        $col     = 'A';
        foreach( $headers as $header ){
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }
        $row++;
        $sheet->getStyle("A1:{$col}1")->getFont()->setBold(true); // Make header bold

        $start   = 0;
        $limit   = 2500;
        while( true ){
            $_query  = clone $query;
            $results = $_query->limit($limit)
                            ->offset($start)
                            ->get();

            if( ! count($results) )
                break;
            
            foreach( $results as $idx => $result ){
                $col  = 'A';
                $data = (array)$result;
                foreach($data as $prop => $value){
                    if( isset($exports[$prop]) ){
                        $sheet->setCellValue($col . $row, $value);
                        $col++;
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
       
        $tempFile = storage_path() . '/' . str_random(40) . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}