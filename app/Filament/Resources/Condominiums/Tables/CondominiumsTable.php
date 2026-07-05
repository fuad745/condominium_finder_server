<?php

declare(strict_types=1);

namespace App\Filament\Resources\Condominiums\Tables;

use App\Models\Condominium;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CondominiumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator')->withCount('projects'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->limit(32),
                TextColumn::make('area_name')->label('Area')->placeholder('—'),
                TextColumn::make('creator.display_name')
                    ->label('Added by')->placeholder('—')->toggleable(),
                TextColumn::make('projects_count')->label('Projects')->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([
                Action::make('area')
                    ->label('Area')
                    ->icon('heroicon-o-map')
                    ->modalHeading(fn (Condominium $record): string => $record->name)
                    ->modalContent(fn (Condominium $record) => view('filament.area-preview', [
                        'polygon' => $record->polygon,
                    ]))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Condominium $record): bool => $record->status === 'pending')
                    ->modalHeading(fn (Condominium $record): string => "Approve “{$record->name}”?")
                    ->modalDescription('Check the drawn footprint below — approving publishes it on the map and awards points.')
                    ->modalContent(fn (Condominium $record) => view('filament.area-preview', [
                        'polygon' => $record->polygon,
                    ]))
                    ->modalWidth('3xl')
                    ->requiresConfirmation()
                    ->action(function (Condominium $record): void {
                        $record->update(['status' => 'approved']);
                        $record->creator?->awardPoints(Condominium::POINTS_ON_APPROVAL);
                        Notification::make()->title("“{$record->name}” approved")->success()->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Condominium $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('The condominium stays hidden from the map.')
                    ->action(function (Condominium $record): void {
                        $record->update(['status' => 'rejected']);
                        Notification::make()->title('Condominium rejected')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
