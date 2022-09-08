<?php

namespace EscolaLms\Reports\Http\Controllers\Admin;

use EscolaLms\Cart\Models\Cart;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Reports\Http\Controllers\Admin\Swagger\StatsSwagger;
use EscolaLms\Reports\Http\Requests\Admin\CartStatsRequest;
use EscolaLms\Reports\Http\Requests\Admin\CourseStatsRequest;
use EscolaLms\Reports\Services\Contracts\StatsServiceContract;
use Illuminate\Http\JsonResponse;

class StatsController extends EscolaLmsBaseController implements StatsSwagger
{
    private StatsServiceContract $statsService;

    public function __construct(StatsServiceContract $statsService)
    {
        $this->statsService = $statsService;
    }

    public function available(): JsonResponse
    {
        return $this->sendResponse($this->statsService->getAvailableStats(), __('List of available stats for models'));
    }

    public function course(CourseStatsRequest $request): JsonResponse
    {
        $course = $request->getCourse();
        return $this->sendResponse($this->statsService->calculate($course, $request->getStats()), __('Stats for Course'));
    }

    public function cart(CartStatsRequest $request): JsonResponse
    {
        return $this->sendResponse($this->statsService->calculate(new Cart(), $request->getStats()), __('Stats for Cart'));
    }
}
