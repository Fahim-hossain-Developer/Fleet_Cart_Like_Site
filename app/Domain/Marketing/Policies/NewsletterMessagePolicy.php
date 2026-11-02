<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\NewsletterMessage;
use App\Models\Admin;

final class NewsletterMessagePolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, NewsletterMessage $s): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, NewsletterMessage $s): bool
    {
        return false;
    }

    public function delete(Admin $a, NewsletterMessage $s): bool
    {
        return false;
    }
}
