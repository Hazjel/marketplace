<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;

abstract class Controller
{
    // A rule the request broke (404/422) is the caller's error, not a 500
    // that pages ops.
    protected function domainErrorResponse(\Exception $e)
    {
        if (in_array($e->getCode(), [404, 422], true)) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $e->getCode());
        }

        return ResponseHelper::exceptionResponse($e);
    }
}
