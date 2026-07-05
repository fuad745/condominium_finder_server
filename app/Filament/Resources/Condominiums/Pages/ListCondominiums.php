<?php

declare(strict_types=1);

namespace App\Filament\Resources\Condominiums\Pages;

use App\Filament\Resources\Condominiums\CondominiumResource;
use Filament\Resources\Pages\ListRecords;

class ListCondominiums extends ListRecords
{
    protected static string $resource = CondominiumResource::class;
}
