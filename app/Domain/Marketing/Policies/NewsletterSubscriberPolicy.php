<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Models\Admin;

final class NewsletterSubscriberPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, NewsletterSubscriber $s): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return false;
    }

    public function update(Admin $a, NewsletterSubscriber $s): bool
    {
        return false;
    }

    public function delete(Admin $a, NewsletterSubscriber $s): bool
    {
        return false;
    }
}
