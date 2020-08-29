<?php
namespace App\Contracts;

interface Exportable
{
    static public function exports() : array;
    static public function exportFilename($user, array $input) : string;
    static public function exportQuery($user, array $input);
}