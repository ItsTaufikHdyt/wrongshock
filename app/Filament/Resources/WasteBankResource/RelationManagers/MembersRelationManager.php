<?php

namespace App\Filament\Resources\WasteBankResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberMemberships';

    protected static ?string $title = 'Anggota';

    protected function getTableHeading(): string
    {
        $memberships = $this->getOwnerRecord()->memberMemberships();

        return sprintf(
            'Anggota (Aktif: %d, Tidak Aktif: %d)',
            (clone $memberships)->where('status', 'active')->count(),
            (clone $memberships)->where('status', 'inactive')->count(),
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Nama')->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Keanggotaan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'active' ? 'Aktif' : 'Tidak Aktif'),
                Tables\Columns\TextColumn::make('joined_at')->label('Bergabung')->dateTime('d M Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['active' => 'Aktif', 'inactive' => 'Tidak Aktif']),
            ])
            ->actions([])
            ->headerActions([])
            ->bulkActions([]);
    }
}
