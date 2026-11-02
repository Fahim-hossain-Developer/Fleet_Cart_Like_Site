<?php

namespace App\Domain\Content\Events;

use Illuminate\Database\Eloquent\Model;

final readonly class ContentChanged
{
    public function __construct(public Model $record) {}
}
