<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\NewsletterCampaign;
use App\Models\Admin;

final class NewsletterCampaignPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, NewsletterCampaign $c): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, NewsletterCampaign $c): bool
    {
        return $this->viewAny($a) && $c->status === 'draft';
    }

    public function delete(Admin $a, NewsletterCampaign $c): bool
    {
        return $this->viewAny($a) && $c->status === 'draft';
    }
}
