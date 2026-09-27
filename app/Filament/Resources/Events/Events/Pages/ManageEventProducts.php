<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Models\Catalog\ProductTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManageEventProducts extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'products';

    protected static ?string $title = 'Presentes';

    protected static ?string $navigationLabel = 'Presentes';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_template_id')
                    ->label('Item do catálogo (opcional)')
                    ->relationship('productTemplate', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Selecione para preencher automaticamente, ou deixe em branco para criar um item personalizado.')
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if (blank($state)) {
                            return;
                        }

                        $template = ProductTemplate::find($state);

                        if (! $template) {
                            return;
                        }

                        $set('name', $template->name);
                        $set('description', $template->description);
                        $set('image', $template->image);
                        $set('price', $template->suggested_price);
                    }),
                TextInput::make('name')
                    ->label('Nome do item')
                    ->placeholder('Ex: Uma cadeira para o Arthur descansar')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrição')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Imagem')
                    ->image()
                    ->disk('public')
                    ->imageEditor()
                    ->imagePreviewHeight('160')
                    ->directory('event-products')
                    ->helperText('Opcional. Se não enviar, uma imagem genérica será usada.'),
                TextInput::make('price')
                    ->label('Preço')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                TextInput::make('quantity_total')
                    ->label('Quantidade disponível')
                    ->helperText('Use 1 para um item único, ou um número maior para permitir várias contribuições (ex: cotas).')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('image')
                    ->label(''),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('quantity_total')
                    ->label('Disponível')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity_purchased')
                    ->label('Presenteado')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Criar produto'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
