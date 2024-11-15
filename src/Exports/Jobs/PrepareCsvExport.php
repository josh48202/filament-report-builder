<?php

namespace Wjbecker\FilamentReportBuilder\Exports\Jobs;

use Filament\Actions\Exports\Jobs\PrepareCsvExport AS FilamentPrepareCsvExport;
class PrepareCsvExport extends FilamentPrepareCsvExport
{
    public function getExportCsvJob(): string
    {
        return ExportCsv::class;
    }
}
