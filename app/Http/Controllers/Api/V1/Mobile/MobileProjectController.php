<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Mobile\Concerns\ResolvesMobileProjectAccess;
use Illuminate\Http\Request;

class MobileProjectController extends BaseMobileController
{
    use ResolvesMobileProjectAccess;

    public function index(Request $request)
    {
        $projects = $this->allowedProjects($request->user());

        return $this->successResponse('Projects loaded.', $this->transformProjects($projects));
    }
}
