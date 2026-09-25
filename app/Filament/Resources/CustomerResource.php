<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\ViewCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\EntitlementsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\OrdersRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'Shop';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'customer';

    protected static ?string $pluralModelLabel = 'customers';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('email_verified_at')->label('Verified')
                    ->dateTime()->placeholder('Not verified')->sortable(),
                TextColumn::make('orders_count')->counts('orders')->label('Orders'),
                TextColumn::make('entitlements_count')->counts('entitlements')->label('Reports'),
                TextColumn::make('created_at')->label('Joined')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('email_verified_at')->label('Verified')
                    ->dateTime()->placeholder('Not verified'),
                TextEntry::make('created_at')->label('Joined')->dateTime(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            EntitlementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
