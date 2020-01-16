<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException'
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    { 
        if( $request->expectsJson() && App::environment(['prod', 'production']) ){ 
            //  Resource missing
            if( $exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ){
                return response([
                    'error' => 'Not found'
                ], 404);
            }

            if( $exception instanceof \Illuminate\Auth\AuthenticationException ){
                return response([
                    'error' => 'Unauthenticated'
                ], 401);
            }

            if( $exception instanceof \Illuminate\Auth\AuthenticationException ){
                return response([
                    'error' => 'Too many requests'
                ], 429);
            }

            return response([
                'error' => 'An unknown error has occurred'
            ], 500);
        }
       
        return parent::render($request, $exception);
    }
}
