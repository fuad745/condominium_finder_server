<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockEdits\Pages;

use App\Filament\Resources\BlockEdits\BlockEditResource;
use Filament\Resources\Pages\ListRecords;

class ListBlockEdits extends ListRecords
{
    protected static string $resource = BlockEditResource::class;
}
