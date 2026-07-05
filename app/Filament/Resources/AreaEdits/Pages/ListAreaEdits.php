<?php

declare(strict_types=1);

namespace App\Filament\Resources\AreaEdits\Pages;

use App\Filament\Resources\AreaEdits\AreaEditResource;
use Filament\Resources\Pages\ListRecords;

class ListAreaEdits extends ListRecords
{
    protected static string $resource = AreaEditResource::class;
}
