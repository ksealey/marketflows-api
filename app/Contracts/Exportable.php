<?php
namespace App\Contracts;

interface Exportable
{
    static public function exports() : array;
    static public function exportFilename($user, array $input) : string;
    static public function onExport($user, array $input, iterable $results);
    static public function onChunkedExport($user, array $input, $query) : string;
    static public function exportQuery($user, array $input);
}