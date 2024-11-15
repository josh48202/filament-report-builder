<?php

namespace Wjbecker\FilamentReportBuilder\Exports\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Exports\Jobs\ExportCsv as FilamentExportCsv;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\Csv\Writer;
use SplTempFileObject;
use Throwable;

class ExportCsv extends FilamentExportCsv
{
    public function handle(): void
    {
        /** @var Authenticatable $user */
        $user = $this->export->user;

        auth()->login($user);

        $exceptions = [];

        $processedRows = 0;
        $successfulRows = 0;

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->setDelimiter($this->exporter::getCsvDelimiter());

        $query = EloquentSerializeFacade::unserialize($this->query);

//        foreach ($this->exporter->getCachedColumns() as $column) {
//            $column->applyRelationshipAggregates($query);
//            $column->applyEagerLoading($query);
//        }

        foreach ($query->find($this->records) as $record) {
            try {
                $csv->insertOne(($this->exporter)($record));

                $successfulRows++;
            } catch (Throwable $exception) {
                $exceptions[$exception::class] = $exception;
            }

            $processedRows++;
        }

        $filePath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . str_pad(strval($this->page), 16, '0', STR_PAD_LEFT) . '.csv';
        $this->export->getFileDisk()->put($filePath, $csv->toString(), Filesystem::VISIBILITY_PRIVATE);

        $this->export->refresh();

        $exportProcessedRows = $this->export->processed_rows + $processedRows;
        $this->export->processed_rows = ($exportProcessedRows < $this->export->total_rows) ?
            $exportProcessedRows :
            $this->export->total_rows;

        $exportSuccessfulRows = $this->export->successful_rows + $successfulRows;
        $this->export->successful_rows = ($exportSuccessfulRows < $this->export->total_rows) ?
            $exportSuccessfulRows :
            $this->export->total_rows;

        $this->export->save();

        $this->handleExceptions($exceptions);
    }
}
