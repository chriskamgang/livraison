<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Utilisateurs';
    protected static ?string $navigationLabel = 'Tous les utilisateurs';
    protected static ?int $navigationSort = 3;

    // Masqué du menu — on utilise ClientResource et DriverResource à la place
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informations personnelles')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nom complet')
                        ->required(),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    Forms\Components\TextInput::make('phone')
                        ->label('Téléphone')
                        ->tel(),
                    Forms\Components\Select::make('role')
                        ->label('Rôle')
                        ->options([
                            'client'      => 'Client',
                            'driver'      => 'Livreur',
                            'admin'       => 'Administrateur',
                            'super_admin' => 'Super Admin',
                        ])
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Statut')
                        ->options([
                            'active'    => 'Actif',
                            'pending'   => 'En attente',
                            'suspended' => 'Suspendu',
                            'banned'    => 'Banni',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create'),
                ])->columns(2),

            Forms\Components\Section::make('Informations livreur')
                ->schema([
                    Forms\Components\Select::make('vehicle_type')
                        ->label('Type de véhicule')
                        ->options([
                            'moto'     => 'Moto',
                            'voiture'  => 'Voiture',
                            'velo'     => 'Vélo',
                            'tricycle' => 'Tricycle',
                        ]),
                    Forms\Components\TextInput::make('vehicle_number')
                        ->label('Numéro du véhicule'),
                    Forms\Components\TextInput::make('license_number')
                        ->label('Numéro de permis'),
                    Forms\Components\Toggle::make('is_verified')
                        ->label('Vérifié')
                        ->default(false),
                ])->columns(2)
                ->visible(fn (Forms\Get $get) => $get('role') === 'driver'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=fff&background=f97316'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin'       => 'warning',
                        'driver'      => 'info',
                        'client'      => 'success',
                        default       => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'    => 'success',
                        'pending'   => 'warning',
                        'suspended' => 'danger',
                        'banned'    => 'danger',
                        default     => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_online')
                    ->label('En ligne')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Vérifié')
                    ->boolean(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn ($state) => $state > 0 ? '⭐ ' . number_format($state, 1) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rôle')
                    ->options([
                        'client'      => 'Clients',
                        'driver'      => 'Livreurs',
                        'admin'       => 'Admins',
                        'super_admin' => 'Super Admins',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active'    => 'Actifs',
                        'pending'   => 'En attente',
                        'suspended' => 'Suspendus',
                    ]),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Vérifié'),
                Tables\Filters\TernaryFilter::make('is_online')
                    ->label('En ligne'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
