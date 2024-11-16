<?php

namespace Wjbecker\FilamentReportBuilder\Models;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $fillable = ['report_id', 'export_id'];

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function export(): BelongsTo
    {
        return $this->belongsTo(Export::class);
    }
}
