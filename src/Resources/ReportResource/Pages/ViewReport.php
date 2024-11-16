<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Illuminate\Support\Str;
use Wjbecker\FilamentReportBuilder\Actions\ReportExportAction;
use Wjbecker\FilamentReportBuilder\Exports\Jobs\PrepareCsvExport;
use Wjbecker\FilamentReportBuilder\Exports\ReportExporter;
use Wjbecker\FilamentReportBuilder\Resources\ReportResource;
use Wjbecker\FilamentReportBuilder\Models\Report;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Wjbecker\FilamentReportBuilder\Support\ReportQueryBuilder;

class ViewReport extends Page implements HasTable
{
    use InteractsWithRecord, InteractsWithTable;

    protected static string $resource = ReportResource::class;

    protected static string $view = 'filament-report-builder::report-resource.pages.view-report';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name.' Report';
    }

    protected function getHeaderActions(): array
    {
        return [
            ReportExportAction::make()->label('Export')
                ->exporter(ReportExporter::class)
                ->record($this->record)
                ->columnMapping(false)
                ->fileName(function() {
                    if (isset($this->getRecord()->data['filename']) && $this->getRecord()->data['filename'] != '') {
                        return $this->getRecord()->data['filename'];
                    }
                    return Str::of($this->getRecord()->name)->snake().'_'.now()->toDateString();
                })
                ->keyBindings('mod+x'),
            Action::make('edit')
                ->url(fn (Report $record): string => route(static::getResource()::getRouteBaseName().'.edit', $record))
                ->keyBindings('mod+e'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return (new ReportQueryBuilder($this->getRecord()))->query();
            })
            ->columns($this->getColumns());
    }

    public function getColumns(): array
    {
        return collect($this->getRecord()->data['columns'])
            ->map(function ($header) {
                $attribute = json_decode($header['column_data']);
                return TextColumn::make((isset($attribute->name) ? $attribute->name.'.' : '').$attribute->item)
                    ->label($header['column_title'])
                    ->sortable(in_array('is_sortable', $header['column_options']))
                    ->searchable(in_array('is_searchable', $header['column_options']));
            })
            ->toArray();
    }
}
