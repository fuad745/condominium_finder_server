<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockReports\Pages;

use App\Filament\Resources\BlockReports\BlockReportResource;
use Filament\Resources\Pages\ListRecords;

class ListBlockReports extends ListRecords
{
    protected static string $resource = BlockReportResource::class;
}
