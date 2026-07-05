<?php

declare(strict_types=1);

namespace App\Filament\Resources\Blocks\Pages;

use App\Filament\Resources\Blocks\BlockResource;
use Filament\Resources\Pages\ListRecords;

class ListBlocks extends ListRecords
{
    protected static string $resource = BlockResource::class;
}
