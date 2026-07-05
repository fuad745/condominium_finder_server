<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $password = $this->form->getRawState()['password'] ?? null;
        if (is_string($password) && $password !== '') {
            $data['password_hash'] = Hash::make($password);
        }
        $data['auth_provider'] = 'email';

        return $data;
    }
}
