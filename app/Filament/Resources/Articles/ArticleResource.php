<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Articles\Pages\ViewArticle;
use App\Models\Article;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string | \UnitEnum | null $navigationGroup = 'Procesos';
    protected static ?string $navigationLabel = 'Artículos';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-book-open';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, callable $set) =>
                    $set('slug', \Illuminate\Support\Str::slug($state))
                ),

            TextInput::make('slug')
                ->label('Slug (URL)')
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Se genera automáticamente del título.'),

            Select::make('category')
                ->label('Categoría')
                ->required()
                ->searchable()
                ->options(Article::getCategoryOptions()),

            Select::make('subcategory')
                ->label('Subcategoría')
                ->searchable()
                ->options(Article::getSubcategoryOptions())
                ->nullable(),

            TagsInput::make('tags')
                ->label('Etiquetas')
                ->placeholder('Agregá etiquetas...')
                ->nullable(),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'draft' => 'Borrador',
                    'published' => 'Publicado',
                    'obsolete' => 'Obsoleto',
                ])
                ->default('published')
                ->required(),

            MarkdownEditor::make('body')
                ->label('Contenido')
                ->required()
                ->columnSpanFull()
                ->toolbarButtons([
                    'bold',
                    'italic',
                    'strike',
                    'link',
                    'heading',
                    'bulletList',
                    'orderedList',
                    'codeBlock',
                    'table',
                    'undo',
                    'redo',
                ])
                ->helperText('Soporta Markdown completo: títulos, listas, código, tablas, links.'),

            FileUpload::make('attachments')
                ->label('Archivos adjuntos')
                ->multiple()
                ->disk('public')
                ->directory('articles/attachments')
                ->nullable()
                ->helperText('PDF, imágenes, scripts, etc.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Article::getCategoryOptions()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'infraestructura' => 'primary',
                        'redes' => 'info',
                        'servidores' => 'warning',
                        'seguridad' => 'danger',
                        'procedimientos' => 'success',
                        'finops' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('subcategory')
                    ->label('Sub')
                    ->formatStateUsing(fn ($state) => Article::getSubcategoryOptions()[$state] ?? $state)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'obsolete' => 'Obsoleto',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'obsolete' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('author.name')
                    ->label('Autor')
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Modificado')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->searchPlaceholder('Buscar artículos...')
            ->filters([
                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(Article::getCategoryOptions()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'published' => 'Publicado',
                        'obsolete' => 'Obsoleto',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Infolists\Components\TextEntry::make('title')
                ->label('Título')
                ->size('lg'),
            \Filament\Infolists\Components\TextEntry::make('category')
                ->label('Categoría')
                ->badge()
                ->formatStateUsing(fn ($state) => Article::getCategoryOptions()[$state] ?? $state),
            \Filament\Infolists\Components\TextEntry::make('subcategory')
                ->label('Subcategoría')
                ->formatStateUsing(fn ($state) => Article::getSubcategoryOptions()[$state] ?? $state),
            \Filament\Infolists\Components\TextEntry::make('tags')
                ->label('Etiquetas')
                ->badge(),
            \Filament\Infolists\Components\TextEntry::make('author.name')
                ->label('Autor'),
            \Filament\Infolists\Components\TextEntry::make('updated_at')
                ->label('Última modificación')
                ->dateTime('d/m/Y H:i'),
            \Filament\Infolists\Components\MarkdownEntry::make('body')
                ->label('')
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'view' => ViewArticle::route('/{record}'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole('admin') ||
               auth()->user()->hasRole('it');
    }
}
