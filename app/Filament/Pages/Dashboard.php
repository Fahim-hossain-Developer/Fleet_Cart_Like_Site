<?php

namespace App\Filament\Pages;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getTitle(): string
    {
        return __('admin.dashboard');
    }

    public function getSubheading(): ?string
    {
        return __('admin.foundation');
    }
}
