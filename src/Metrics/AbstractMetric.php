<?php

namespace EscolaLms\Reports\Metrics;

use Cron\CronExpression;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Config\Repository as Config;

abstract class AbstractMetric implements MetricContract
{
    protected bool $history;
    protected int $limit;
    protected string $cron;

    protected function __construct(Config $config)
    {
        $configuration = $config->get('reports.metric_configuration')[get_class($this)] ?? [];
        $this->history = $configuration['history'] ?? true;
        $this->limit = $configuration['limit'] ?? 10;
        $this->cron = $configuration['cron'] ?? '0 0 * * *';
        if (!CronExpression::isValidExpression($this->cron)) {
            $this->cron = '0 0 * * *';
        }
    }

    public function schedule(Schedule $schedule): void
    {
        if ($this->saveHistory()) {
            $schedule->call(fn () => $this->calculateAndStore())->cron($this->cronExpression());
        }
    }

    public function saveHistory(): bool
    {
        return $this->history;
    }

    public function cronExpression(): string
    {
        return $this->cron;
    }

    public function defaultLimit(): int
    {
        return $this->limit;
    }
}