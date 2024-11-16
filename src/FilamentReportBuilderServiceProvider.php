<?php

namespace Wjbecker\FilamentReportBuilder;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentReportBuilderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-report-builder')
            ->hasViews()
            ->hasMigrations(['create_reports_table', 'create_report_exports_table']);
    }
}
