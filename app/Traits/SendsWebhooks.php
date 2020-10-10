<?php
namespace App\Traits;

use Exception;
use App;

trait SendsWebhooks
{
    public function sendWebhook($method, $url, $data = [])
    {
        $ok         = false;
        $statusCode = 0;
        $error      = null;
        
        try{
            $client      = App::make('HTTPClient');
            $fieldsKey   = $method == 'GET' ? 'query' : 'form_params';
            $contentType = $method == 'GET' ? 'application/text' : 'application/x-www-form-urlencoded';  
            $response    = $client->request($method, $url, [
                'headers' => [
                    'X-Sender'     => 'MarketFlows',
                    'Content-Type' => $contentType
                ],
                $fieldsKey => $data,
                'connect_timeout' => 5
            ]);
            $ok         = true;
            $statusCode = $response->getStatusCode();
        }catch(Exception $e){
            $statusCode = 500;
            $error      = 'Unknown error';
            if( method_exists($e, 'getResponse') ){
                $response   = $e->getResponse();
                if( $response ){
                    $statusCode = $response->getStatusCode();
                    $error      = $e->getResponse()->getBody();
                }
            }
        }

        return (object)[
            'ok'            => $ok,
            'status_code'   => $statusCode,
            'error'         => $error, 
        ];
    }
}