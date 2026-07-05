<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->id !== auth()->id()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $password = $this->form->getRawState()['password'] ?? null;
        if (is_string($password) && $password !== '') {
            $data['password_hash'] = Hash::make($password);
        }

        return $data;
    }
}
