<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\AreaEdit;
use App\Models\Block;
use App\Models\BlockEdit;
use App\Models\BlockReport;
use App\Models\Condominium;
use App\Models\Project;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ModerationStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pending = Condominium::query()->where('status', 'pending')->count()
            + Project::query()->where('status', 'pending')->count()
            + Block::query()->where('status', 'pending')->count()
            + BlockEdit::query()->where('status', 'pending')->count()
            + AreaEdit::query()->where('status', 'pending')->count();

        return [
            Stat::make('Awaiting review', (string) $pending)
                ->description('Pending condominiums, projects, blocks and edits')
                ->color($pending > 0 ? 'warning' : 'success'),
            Stat::make('Open reports', (string) BlockReport::query()->count())
                ->description('“This looks wrong” flags')
                ->color(BlockReport::query()->count() > 0 ? 'danger' : 'success'),
            Stat::make('Blocks on the map', (string) Block::query()->where('status', 'approved')->count())
                ->description(Block::query()->where('is_verified', true)->count() . ' verified'),
            Stat::make('Community', (string) User::query()->count())
                ->description(User::query()->where('is_trusted', true)->count() . ' trusted contributors'),
        ];
    }
}
