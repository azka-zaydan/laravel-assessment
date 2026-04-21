<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiLogResource;
use App\Repositories\ApiLogRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApiLogController extends Controller
{
    public function __construct(private readonly ApiLogRepository $repo) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min(200, $request->integer('per_page', 50)));

        $paginated = $this->repo
            ->queryBuilder()
            ->paginate($perPage);

        return ApiLogResource::collection($paginated);
    }
}
