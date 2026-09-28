<?php

namespace App\Filament\Resources\Events\Events\Pages\Concerns;

use App\Enums\Premium\Feature;
use App\Filament\Support\PremiumLock;
use App\Models\Events\Event;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * For event edit pages behind a premium feature. Hosts without it still
 * see the page — locked, with the way to the premium page — so they know
 * the feature exists; the admin can always edit, with a reminder when the
 * settings aren't in effect yet.
 */
trait RequiresPremiumFeature
{
    use LocksPremiumNavigationItem;

    protected function isLocked(): bool
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return ! auth()->user()?->isAdmin() && ! $event->hasFeature(static::premiumFeature());
    }

    /**
     * @param  array<int, Component>  $components
     */
    protected function premiumForm(Schema $schema, array $components): Schema
    {
        /** @var Event $event */
        $event = $this->getRecord();

        if ($this->isLocked()) {
            return $schema->components([PremiumLock::callout(static::premiumFeature(), $event)]);
        }

        return $schema->components([
            ...($event->hasFeature(static::premiumFeature()) ? [] : [PremiumLock::notGrantedNotice(static::premiumFeature())]),
            ...$components,
        ]);
    }

    protected function getFormActions(): array
    {
        return $this->isLocked() ? [] : parent::getFormActions();
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_if($this->isLocked(), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}
