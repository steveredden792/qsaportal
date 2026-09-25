<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Entitlement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntitlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'entitlements';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('issue.report.name')->label('Report')->wrap(),
                TextColumn::make('issue.version_label')->label('Issue'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Entitlement $record): string => $record->status())
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expiring' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->label('Purchased')->dateTime()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
