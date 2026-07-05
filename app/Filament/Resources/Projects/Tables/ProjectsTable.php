<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['creator', 'condominium'])->withCount('blocks'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(36),
                TextColumn::make('area_name')
                    ->label('Area')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('creator.display_name')
                    ->label('Added by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('condominium.name')
                    ->label('Condominium')
                    ->placeholder('—')
                    ->limit(24),
                TextColumn::make('blocks_count')
                    ->label('Blocks')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                Action::make('map')
                    ->label('Area')
                    ->icon('heroicon-o-map')
                    ->modalHeading(fn (Project $record): string => $record->name)
                    ->modalContent(fn (Project $record) => $record->polygon !== null
                        ? view('filament.area-preview', ['polygon' => $record->polygon])
                        : view('filament.map-preview', ['lat' => $record->lat, 'lng' => $record->lng]))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Project $record): bool => $record->status === 'pending')
                    ->modalHeading(fn (Project $record): string => "Approve “{$record->name}”?")
                    ->modalDescription('Check the drawn footprint below — approving publishes the project area on the map and awards points to the contributor.')
                    ->modalContent(fn (Project $record) => $record->polygon !== null
                        ? view('filament.area-preview', ['polygon' => $record->polygon])
                        : view('filament.map-preview', ['lat' => $record->lat, 'lng' => $record->lng]))
                    ->modalWidth('3xl')
                    ->requiresConfirmation()
                    ->action(function (Project $record): void {
                        $record->update(['status' => 'approved']);
                        $record->creator?->awardPoints(Project::POINTS_ON_APPROVAL);
                        Notification::make()
                            ->title("“{$record->name}” approved")
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Project $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Project $record): string => "Reject “{$record->name}”?")
                    ->modalDescription('The project stays hidden from the map. Its submitter keeps their other contributions.')
                    ->action(function (Project $record): void {
                        $record->update(['status' => 'rejected']);
                        Notification::make()->title('Project rejected')->send();
                    }),
                EditAction::make(),
            ]);
    }
}
