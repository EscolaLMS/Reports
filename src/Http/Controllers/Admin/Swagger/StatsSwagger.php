<?php

namespace EscolaLms\Reports\Http\Controllers\Admin\Swagger;

use EscolaLms\Reports\Http\Requests\Admin\CartStatsRequest;
use EscolaLms\Reports\Http\Requests\Admin\CourseStatsRequest;
use EscolaLms\Reports\Http\Requests\Admin\DateRangeStatsRequest;
use Illuminate\Http\JsonResponse;

interface StatsSwagger
{
    /**
     * @OA\Get(
     *     path="/api/admin/stats/available",
     *     summary="Get list of available stats for models",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Schema(
     *                          type="string"
     *                      )
     *                  )
     *              )
     *          )
     *     ),
     * )
     */
    public function available(): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/stats/course/{course_id}",
     *     summary="Calculate stats for Course",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="course_id",
     *          required=true,
     *          in="path",
     *          description="Course ID",
     *          @OA\Schema(
     *              type="integer",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="stats",
     *          required=false,
     *          in="query",
     *          description="array of stats to be calculated, leave empty to calculate all available stats",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="string"
     *                  )
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Schema(
     *                          type="string"
     *                      )
     *                  )
     *              )
     *          )
     *     ),
     * )
     */
    public function course(CourseStatsRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/stats/cart",
     *     summary="Calculate stats for Cart",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="stats",
     *          required=false,
     *          in="query",
     *          description="array of stats to be calculated, leave empty to calculate all available stats",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="string"
     *                  )
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Schema(
     *                          type="string"
     *                      )
     *                  )
     *              )
     *          )
     *     ),
     * )
     */
    public function cart(CartStatsRequest $request): JsonResponse;

    /**
     * @OA\Get(
     *     path="/api/admin/stats/date-range",
     *     summary="Calculate stats for Course",
     *     description="",
     *     tags={"Admin Reports"},
     *      security={
     *          {"passport": {}},
     *      },
     *     @OA\Parameter(
     *          name="date_from",
     *          required=false,
     *          in="path",
     *          description="Date from",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="date_to",
     *          required=false,
     *          in="path",
     *          description="Date to",
     *          @OA\Schema(
     *              type="date",
     *          ),
     *      ),
     *     @OA\Parameter(
     *          name="stats",
     *          required=false,
     *          in="query",
     *          description="array of stats to be calculated, leave empty to calculate all available stats",
     *          @OA\Schema(
     *              type="array",
     *              @OA\Items(
     *                  @OA\Schema(
     *                      type="string"
     *                  )
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              @OA\Schema(
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Schema(
     *                          type="string"
     *                      )
     *                  )
     *              )
     *          )
     *     ),
     * )
     */
    public function dateRange(DateRangeStatsRequest $request): JsonResponse;
}
