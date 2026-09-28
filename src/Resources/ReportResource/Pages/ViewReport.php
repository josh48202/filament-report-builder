<?php

namespace Wjbecker\FilamentReportBuilder\Resources\ReportResource\Pages;

use Illuminate\Support\Str;
use Wjbecker\FilamentReportBuilder\Actions\ReportExportAction;
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
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Wjbecker\FilamentReportBuilder\Support\ReportQueryBuilder;

class ViewReport extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable {
        paginateTableQuery as protected basePaginateTableQuery;
    }

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
                ->chunkSize(1000)
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
            ->columns($this->getColumns())
            ->paginated([25, 50, 100, 250, 500])
            ->defaultPaginationPageOption(50)
            ->extremePaginationLinks();
    }

    protected function paginateTableQuery(Builder $query): Paginator | CursorPaginator
    {
        $records = $this->basePaginateTableQuery($query);

        // Fewer links around the current page so the page numbers fit at narrower widths
        return $records instanceof LengthAwarePaginator ? $records->onEachSide(1) : $records;
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
