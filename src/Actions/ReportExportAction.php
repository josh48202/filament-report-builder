<?php

namespace Wjbecker\FilamentReportBuilder\Actions;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanExportRecords;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ExportAction as ExportTableAction;
use Filament\Tables\Actions\ExportBulkAction as ExportTableBulkAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Bus\PendingChain;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Component;
use Wjbecker\FilamentReportBuilder\Models\ReportExport;

class ReportExportAction extends Action
{
    use CanExportRecords;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function (ReportExportAction | ExportAction | ExportTableAction | ExportTableBulkAction $action, array $data, Component $livewire) {
            $exporter = $action->getExporter();

            if ($livewire instanceof HasTable) {
                $query = $livewire->getTableQueryForExport();
            } else {
                $query = $exporter::getModel()::query();
            }

            $query = $exporter::modifyQuery($query);

            if ($this->modifyQueryUsing) {
                $query = $this->evaluate($this->modifyQueryUsing, [
                    'query' => $query,
                ]) ?? $query;
            }

            $records = $action instanceof ExportTableBulkAction ? $action->getRecords() : null;

            $totalRows = $records ? $records->count() : $query->count();
            $maxRows = $action->getMaxRows() ?? $totalRows;

            if ($maxRows < $totalRows) {
                Notification::make()
                    ->title(__('filament-actions::export.notifications.max_rows.title'))
                    ->body(trans_choice('filament-actions::export.notifications.max_rows.body', $maxRows, [
                        'count' => Number::format($maxRows),
                    ]))
                    ->danger()
                    ->send();

                return;
            }

            $user = auth()->user();

            $options = array_merge(
                $action->getOptions(),
                Arr::except($data, ['columnMap']),
            );

            if ($action->hasColumnMapping()) {
                $columnMap = collect($data['columnMap'])
                    ->dot()
                    ->reduce(fn (Collection $carry, mixed $value, string $key): Collection => $carry->mergeRecursive([
                        Str::beforeLast($key, '.') => [Str::afterLast($key, '.') => $value],
                    ]), collect())
                    ->filter(fn (array $column): bool => $column['isEnabled'] ?? false)
                    ->mapWithKeys(fn (array $column, string $columnName): array => [$columnName => $column['label']])
                    ->all();
            } else {
                $columnMap = collect($exporter::getColumns($this->record))
                    ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
                    ->all();
            }

            $export = app(Export::class);
            $export->user()->associate($user);
            $export->exporter = $exporter;
            $export->total_rows = $totalRows;

            $exporter = $export->getExporter(
                columnMap: $columnMap,
                options: $options,
            );

            $export->file_disk = $action->getFileDisk() ?? $exporter->getFileDisk();
            $export->save();

            $export->file_name = $action->getFileName($export) ?? $exporter->getFileName($export);
            $export->save();

            $reportExport = app(ReportExport::class);
            $reportExport->report()->associate($this->record);
            $reportExport->export()->associate($export);
            $reportExport->save();

            $formats = $action->getFormats() ?? $exporter->getFormats();
            $hasCsv = in_array(ExportFormat::Csv, $formats);
            $hasXlsx = in_array(ExportFormat::Xlsx, $formats);

            $serializedQuery = EloquentSerializeFacade::serialize($query);

            $job = $action->getJob();
            $jobQueue = $exporter->getJobQueue();
            $jobConnection = $exporter->getJobConnection();
            $jobBatchName = $exporter->getJobBatchName();

            // We do not want to send the loaded user relationship to the queue in job payloads,
            // in case it contains attributes that are not serializable, such as binary columns.
            $export->unsetRelation('user');

            $makeCreateXlsxFileJob = fn (): CreateXlsxFile => app(CreateXlsxFile::class, [
                'export' => $export,
                'columnMap' => $columnMap,
                'options' => $options,
            ]);

            Bus::chain([
                Bus::batch([new $job(
                    $export,
                    query: $serializedQuery,
                    columnMap: $columnMap,
                    options: $options,
                    chunkSize: $action->getChunkSize(),
                    records: $action instanceof ExportTableBulkAction ? $action->getRecords()->all() : null,
                )])
                    ->when(
                        filled($jobQueue),
                        fn (PendingBatch $batch) => $batch->onQueue($jobQueue),
                    )
                    ->when(
                        filled($jobConnection),
                        fn (PendingBatch $batch) => $batch->onConnection($jobConnection),
                    )
                    ->when(
                        filled($jobBatchName),
                        fn (PendingBatch $batch) => $batch->name($jobBatchName),
                    )
                    ->allowFailures(),
                ...(($hasXlsx && (! $hasCsv)) ? [$makeCreateXlsxFileJob()] : []),
                app(ExportCompletion::class, [
                    'export' => $export,
                    'columnMap' => $columnMap,
                    'formats' => $formats,
                    'options' => $options,
                ]),
                ...(($hasXlsx && $hasCsv) ? [$makeCreateXlsxFileJob()] : []),
            ])
                ->when(
                    filled($jobQueue),
                    fn (PendingChain $chain) => $chain->onQueue($jobQueue),
                )
                ->when(
                    filled($jobConnection),
                    fn (PendingChain $chain) => $chain->onConnection($jobConnection),
                )
                ->dispatch();

            Notification::make()
                ->title($action->getSuccessNotificationTitle())
                ->body(trans_choice('filament-actions::export.notifications.started.body', $export->total_rows, [
                    'count' => Number::format($export->total_rows),
                ]))
                ->duration(2000)
                ->success()
                ->send();
        });
    }
}
