<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OptionGroupResource\Pages;
use App\Filament\Traits\HasRestaurantScope;
use App\Models\OptionGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OptionGroupResource extends Resource
{
    use HasRestaurantScope;

    protected static ?string $model = OptionGroup::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Menu';
    protected static ?string $navigationLabel = 'Groupes d\'options';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $restaurantId = static::getRestaurantId();

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        $restaurantId = static::getRestaurantId();

        return $form->schema([
            Forms\Components\Section::make('Groupe d\'options')
                ->description('Ex: "Choix du complement", "Type de sauce", "Taille"')
                ->schema([
                    Forms\Components\Hidden::make('restaurant_id')
                        ->default(fn () => $restaurantId),
                    Forms\Components\Select::make('restaurant_id')
                        ->label('Restaurant')
                        ->relationship('restaurant', 'name')
                        ->required()
                        ->visible(fn () => static::isSuperAdmin()),
                    Forms\Components\TextInput::make('name')
                        ->label('Nom du groupe')
                        ->placeholder('Ex: Choix du complement')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->label('Type de choix')
                        ->options([
                            'single' => 'Choix unique (un seul)',
                            'multiple' => 'Choix multiple (plusieurs)',
                        ])
                        ->default('single')
                        ->required(),
                    Forms\Components\Toggle::make('is_required')
                        ->label('Obligatoire')
                        ->helperText('Le client doit faire un choix'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordre')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('Options disponibles')
                ->description('Les choix proposes au client')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nom de l\'option')
                                ->placeholder('Ex: Frites de plantain')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('price_adjustment')
                                ->label('Supplement (XAF)')
                                ->numeric()
                                ->default(0)
                                ->suffix('XAF')
                                ->helperText('0 = pas de supplement'),
                            Forms\Components\Toggle::make('is_default')
                                ->label('Par defaut'),
                            Forms\Components\Toggle::make('is_available')
                                ->label('Disponible')
                                ->default(true),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Ordre')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(5)
                        ->defaultItems(2)
                        ->addActionLabel('Ajouter une option')
                        ->reorderable()
                        ->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->badge()
                    ->color('warning')
                    ->visible(fn () => static::isSuperAdmin()),
                Tables\Columns\TextColumn::make('name')
                    ->label('Groupe')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'single' ? 'Choix unique' : 'Choix multiple')
                    ->color(fn ($state) => $state === 'single' ? 'info' : 'warning'),
                Tables\Columns\IconColumn::make('is_required')
                    ->label('Obligatoire')
                    ->boolean(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Options')
                    ->counts('items')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('menu_items_count')
                    ->label('Articles lies')
                    ->counts('menuItems')
                    ->badge(),
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
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOptionGroups::route('/'),
            'create' => Pages\CreateOptionGroup::route('/create'),
            'edit'   => Pages\EditOptionGroup::route('/{record}/edit'),
        ];
    }
}
