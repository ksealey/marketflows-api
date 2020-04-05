<?php
namespace App\Contracts;

interface Exportable
{
    static public function exports() : array;
    static public function exportFilename() : string;
    static public function onExport(iterable $results);
    static public function onChunkedExport($query) : string;
    static public function exportQuery($user, array $input);
}