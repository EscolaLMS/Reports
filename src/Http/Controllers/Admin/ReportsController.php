<?php

namespace EscolaLms\Reports\Http\Controllers\Admin;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Reports\Actions\FindReport;
use EscolaLms\Reports\Http\Requests\Admin\ReportRequest;
use EscolaLms\Reports\Http\Resources\MeasurementCollection;
use EscolaLms\Reports\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;

class ReportsController extends EscolaLmsBaseController
{

    public function metrics(): JsonResponse
    {
        return $this->sendResponse(config('reports.metrics'), __('Enabled metrics'));
    }

    public function report(ReportRequest $request, FindReport $action): JsonResponse
    {
        $report = $action->handle($request->getMetric(), $request->getDate(), $request->getLimit());
        if (is_null($report)) {
            return $this->sendError(__("No report found for given date"));
        }
        return $this->sendResponseForResource(MeasurementCollection::make($report->measurements), __("Report data"));
    }
}
