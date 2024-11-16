<?php

namespace Wjbecker\FilamentReportBuilder\Exports;

use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Wjbecker\FilamentReportBuilder\Models\Report;
use Wjbecker\FilamentReportBuilder\Models\ReportExport;

class ReportExporter extends Exporter
{
    private ?Report $report;

    public function __construct(Export $export, array $columnMap, array $options)
    {
        parent::__construct($export, $columnMap, $options);
        $this->report = ReportExport::where('export_id', $export->id)->first()->report ?? null;
    }

    public static function getColumns($report = null): array
    {
        $columns = [];
        foreach ($report->data['columns'] as $header) {
            $data = json_decode($header['column_data']);
            $columns[] = ExportColumn::make($data->item)
                ->label($header['column_title'])
                ->formatStateUsing(function ($state): string {
                    if ($state && gettype($state) === 'object' && class_exists(get_class($state))) {
                        $class = new \ReflectionClass(get_class($state));

                        if ($class->isSubclassOf(Carbon::class)) {
                            return $state->toDateString();
                        }

                        if ($class->isEnum()) {
                            return $state->getLabel();
                        }

                        return '';
                    } elseif(is_null($state)) {
                        return '';
                    }

                    return $state;
                });
        }

        return $columns;
    }

    public function getCachedColumns(): array
    {
        return $this->cachedColumns ?? array_reduce(static::getColumns($this->report), function (array $carry, ExportColumn $column): array {
            $carry[$column->getName()] = $column->exporter($this);

            return $carry;
        }, []);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your report export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
