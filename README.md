# Reports

Package for statistics & reports

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Reports/)
[![codecov](https://codecov.io/gh/EscolaLMS/Reports/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Reports)
[![phpunit](https://github.com/EscolaLMS/Reports/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Core/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/reports)](https://packagist.org/packages/escolalms/reports)
[![downloads](https://img.shields.io/packagist/v/escolalms/reports)](https://packagist.org/packages/escolalms/reports)
[![downloads](https://img.shields.io/packagist/l/escolalms/reports)](https://packagist.org/packages/escolalms/reports)

## Installation

```
composer require escolalms/reports
```

After installation use `php artisan vendor:publish --tag=reports` to publish config file.

## Configuration

By editing published config `reports.php` you can:

1. Change which metrics are available in API (by editing `metrics`)
2. Change settings for each Metric (by editing `metric_configuration`)
   1. `limit` defines how many data points will be calculated by default (if you don't pass limit as query parameter); for example: `TutorsPopularityMetric` with `limit` set to 10 will return popularity of 10 most popular Tutors
   2. `history` is a boolean that defines if this metric should be automatically calculated and stored in database
   3. `cron` is cron config which determines how often automatic calculation of metrics happens

## API

There are two endpoints defined in this package.

1. `GET /api/admin/reports/metrics` returns list of `metrics` configured in `reports.php` config file
2. `GET /api/admin/reports/report` calculates data for chosen metric; you can pass following query parameters to this endpoint:
   1. `metric={classname}` is required; `classname` is one of the `metrics` returned in `/api/admin/reports/metrics` endpoit
   2. `limit={int}` is optional; determines the maximum number of data points that will be returned
   3. `date={date}` is optional; will try to load historical report data for given date or return `404` if there is no data available; without this param, endpoint will return today's data
