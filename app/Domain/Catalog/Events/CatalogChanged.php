<?php

namespace App\Domain\Catalog\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

final readonly class CatalogChanged implements ShouldDispatchAfterCommit
{
    public function __construct(public Model $record) {}
}
