<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;

/** Stands in for your app's own User model. */
class ExampleUser extends User implements FilamentUser
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
