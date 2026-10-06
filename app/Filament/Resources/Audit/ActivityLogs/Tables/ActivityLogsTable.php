<?php

namespace App\Filament\Resources\Audit\ActivityLogs\Tables;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Events\EventAppearance;
use App\Models\Events\EventRsvpSetting;
use App\Models\Events\EventSection;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\Premium\FeatureGrant;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    /**
     * Maps logged attribute keys to the pt-BR labels already used across
     * the admin forms, so the "what changed" view reads naturally instead
     * of showing raw column names.
     *
     * @var array<string, string>
     */
    private const ATTRIBUTE_LABELS = [
        'title' => 'Título',
        'type' => 'Tipo de evento',
        'event_date' => 'Data do evento',
        'event_time' => 'Horário do evento',
        'description' => 'Texto do evento',
        'story' => 'História',
        'story_title' => 'Título da seção de história',
        'cover_image' => 'Imagem de capa',
        'gallery' => 'Galeria de fotos',
        'primary_color' => 'Cor primária',
        'secondary_color' => 'Cor secundária',
        'font_color_primary' => 'Cor da fonte principal',
        'font_color_secondary' => 'Cor da fonte secundária',
        'font_family' => 'Fonte',
        'is_published' => 'Publicado',
        'cover_effect_intensity' => 'Intensidade do efeito de desfoque',
        'collect_companions' => 'Pedir dados dos acompanhantes',
        'fields' => 'Dados pedidos na confirmação',
        'companion_fields' => 'Dados pedidos de cada acompanhante',
        'child_age_limit' => 'Idade das crianças (menos de … anos)',
        'children_dont_pay' => 'Crianças não pagam',
        'age' => 'Idade',
        'feature' => 'Recurso premium',
        'source' => 'Origem',
        'expires_at' => 'Expira em',
        'name' => 'Nome',
        'image' => 'Imagem',
        'price' => 'Preço',
        'quantity_total' => 'Quantidade disponível',
        'is_active' => 'Ativo',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Quem')
                    ->placeholder('Sistema'),
                TextColumn::make('description')
                    ->label('Ação'),
                TextColumn::make('subject_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Event::class, EventAppearance::class, EventRsvpSetting::class, EventSection::class => 'Evento',
                        EventProduct::class => 'Presente',
                        Order::class => 'Pedido',
                        PremiumPurchase::class => 'Compra Premium',
                        FeatureGrant::class => 'Recurso premium',
                        Guest::class => 'Convidado',
                        default => '—',
                    }),
                TextColumn::make('subject')
                    ->label('Item')
                    ->state(function (Activity $record): string {
                        $subject = $record->subject;

                        return match (true) {
                            $subject instanceof Event => $subject->title,
                            $subject instanceof EventAppearance, $subject instanceof EventRsvpSetting, $subject instanceof EventSection => $subject->event->title,
                            $subject instanceof Order => "Pedido #{$subject->id} ({$subject->event?->title})",
                            $subject instanceof PremiumPurchase => "Compra #{$subject->id} ({$subject->event?->title})",
                            $subject instanceof EventProduct => $subject->name,
                            $subject instanceof Guest => "{$subject->name} ({$subject->event?->title})",
                            $subject instanceof FeatureGrant => $subject->feature->label().' — '.match (true) {
                                $subject->grantable instanceof Event => $subject->grantable->title,
                                $subject->grantable instanceof User => $subject->grantable->name,
                                default => '(excluído)',
                            },
                            default => '(excluído)',
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('Tipo')
                    ->options([
                        'event' => 'Evento',
                        'product' => 'Presente',
                        'premium' => 'Recurso premium',
                        'guest' => 'Convidado',
                        'payment' => 'Pagamento',
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $type) => $query->whereIn('subject_type', match ($type) {
                            'event' => [Event::class, EventAppearance::class, EventRsvpSetting::class],
                            'product' => [EventProduct::class],
                            'guest' => [Guest::class],
                            'payment' => [Order::class, PremiumPurchase::class],
                            default => [FeatureGrant::class],
                        }),
                    )),
            ])
            ->recordActions([
                Action::make('viewChanges')
                    ->label('Ver detalhes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('O que mudou')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (Activity $record) => new HtmlString(self::renderChanges($record))),
            ]);
    }

    private static function renderChanges(Activity $record): string
    {
        $attributes = $record->attribute_changes?->get('attributes', []) ?? [];
        $old = $record->attribute_changes?->get('old', []) ?? [];

        if (blank($attributes) && blank($old)) {
            return '<p class="text-sm text-gray-500">Nenhum detalhe de alteração registrado.</p>';
        }

        $keys = collect(array_keys($attributes))->merge(array_keys($old))->unique();

        $rows = $keys->map(function (int|string $key) use ($attributes, $old) {
            $key = (string) $key;
            $label = e(self::ATTRIBUTE_LABELS[$key] ?? $key);
            $oldValue = e(self::formatValue($old[$key] ?? null));
            $newValue = e(self::formatValue($attributes[$key] ?? null));

            return <<<HTML
                <div class="py-2 border-b border-gray-100 dark:border-gray-700">
                    <p class="text-sm font-medium">{$label}</p>
                    <p class="text-sm text-gray-500">{$oldValue} → {$newValue}</p>
                </div>
            HTML;
        })->implode('');

        return "<div>{$rows}</div>";
    }

    private static function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (is_string($value) && Feature::tryFrom($value)) {
            return Feature::from($value)->label();
        }

        if (is_array($value)) {
            return implode(', ', $value) ?: '—';
        }

        return (string) $value;
    }
}
