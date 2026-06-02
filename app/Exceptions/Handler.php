<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($this->isMobileApiRequest($request)) {
                return response()->json([
                    'status' => false,
                    'error_key' => 'unauthenticated',
                    'message' => 'Your session has expired. Please login again.',
                ], 401);
            }
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if ($this->isMobileApiRequest($request)) {
                return response()->json([
                    'status' => false,
                    'error_key' => 'validation_error',
                    'message' => 'Please check the submitted fields.',
                    'errors' => (object) $e->errors(),
                ], 422);
            }
        });

        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($this->isMobileApiRequest($request)) {
                return response()->json([
                    'status' => false,
                    'error_key' => 'forbidden',
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($this->isMobileApiRequest($request)) {
                return response()->json([
                    'status' => false,
                    'error_key' => 'not_found',
                    'message' => 'The requested record was not found.',
                ], 404);
            }
        });
    }

    protected function isMobileApiRequest(Request $request): bool
    {
        return $request->is('api/v1/mobile') || $request->is('api/v1/mobile/*');
    }
}
