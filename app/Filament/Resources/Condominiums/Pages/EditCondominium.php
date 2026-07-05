<?php

declare(strict_types=1);

namespace App\Filament\Resources\Condominiums\Pages;

use App\Filament\Resources\Condominiums\CondominiumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCondominium extends EditRecord
{
    protected static string $resource = CondominiumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
