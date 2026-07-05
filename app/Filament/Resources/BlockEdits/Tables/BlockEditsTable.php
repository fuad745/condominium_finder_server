<?php

declare(strict_types=1);

namespace App\Filament\Resources\BlockEdits\Tables;

use App\Models\BlockEdit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class BlockEditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['block.project', 'suggester']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('block.block_number')
                    ->label('Block')
                    ->formatStateUsing(fn (BlockEdit $record): string => "Block {$record->block?->block_number}")
                    ->description(fn (BlockEdit $record): ?string => $record->block?->project?->name),
                TextColumn::make('suggester.display_name')
                    ->label('Suggested by')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->limit(48)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Compare')
                    ->icon('heroicon-o-map-pin')
                    ->modalHeading(fn (BlockEdit $record): string => "Block {$record->block?->block_number} — current vs suggested")
                    ->modalContent(fn (BlockEdit $record) => view('filament.map-compare', [
                        'oldLat' => $record->block->lat,
                        'oldLng' => $record->block->lng,
                        'newLat' => $record->new_lat,
                        'newLng' => $record->new_lng,
                    ]))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BlockEdit $record): bool => $record->status === 'pending' && $record->block !== null)
                    ->modalHeading(fn (BlockEdit $record): string => "Apply this correction to block {$record->block?->block_number}?")
                    ->modalDescription('Compare both pins below. Approving moves the block to the suggested location and awards points to the suggester.')
                    ->modalContent(fn (BlockEdit $record) => view('filament.map-compare', [
                        'oldLat' => $record->block->lat,
                        'oldLng' => $record->block->lng,
                        'newLat' => $record->new_lat,
                        'newLng' => $record->new_lng,
                    ]))
                    ->modalWidth('4xl')
                    ->requiresConfirmation()
                    ->action(function (BlockEdit $record): void {
                        DB::transaction(function () use ($record): void {
                            $block = $record->block;
                            $block->update([
                                'lat' => $record->new_lat,
                                'lng' => $record->new_lng,
                                'notes' => $record->new_notes ?? $block->notes,
                            ]);
                            $record->update([
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        });
                        $record->suggester?->awardPoints(BlockEdit::POINTS_ON_APPROVAL);
                        Notification::make()
                            ->title('Correction applied')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BlockEdit $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('The block keeps its current location.')
                    ->action(function (BlockEdit $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        Notification::make()->title('Suggestion rejected')->send();
                    }),
            ]);
    }
}
