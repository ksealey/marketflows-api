<?php 
namespace App\Helpers;

class CDNHelper
{
    protected static $faking = false;

    static public function fake()
    {
        self::$faking = true;
    }

    static public function invalidate($path)
    {

        return; // TODO:  Update

        $cloudFront = new \Aws\CloudFront\CloudFrontClient([
            'version'     => 'latest',
            'region'      => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_ACCESS_KEY_ID'),
            ]
        ]);

        $cloudFront->createInvalidation([
            'DistributionId' => env('AWS_CDN_DISTRIBUTION_ID'),
            'InvalidationBatch' => [
                'CallerReference' => str_random(16),
                'Paths' => [
                    'Items' => [$path], 
                    'Quantity' => 1
                ]
            ]
        ]);
    }
}