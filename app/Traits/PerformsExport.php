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
    static public function onExport($user, $input, $results)
    {
        $exportName  = self::exportFileName($user, $input);
        $exports     = self::exports();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($exportName)
                    ->setSubject($exportName);
                    
        $sheet             = $spreadsheet->getActiveSheet();
        $headers           = array_values($exports);
        $col               = 'A';
        $headerToColumnMap = [];
        foreach( $headers as $h ){
            $headerToColumnMap[$h] = $col;
            $col ++;
        }

        $col = 'A';
        foreach( $headers as $header ){
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle("A1:{$col}1")->getFont()->setBold(true); // Make header bold

        $row = 2;
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

    static public function onChunkedExport($user, $input, $query) : string
    {
        $exportName  = self::exportFileName($user, $input);
        $exports     = self::exports();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
                    ->setCreator(env('APP_NAME'))
                    ->setLastModifiedBy('System')
                    ->setTitle($exportName)
                    ->setSubject($exportName);   

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
       
        $tempFile = storage_path() . '/' . str_random(40) . '.xlsx';
        $writer   = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }
}