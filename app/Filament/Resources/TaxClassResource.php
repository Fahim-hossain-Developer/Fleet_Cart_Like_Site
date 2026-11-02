<?php

namespace App\Filament\Resources;

use App\Domain\Checkout\Models\TaxClass;
use Filament\Forms\Form;

class TaxClassResource extends CommerceResource
{
    protected static ?string $model = TaxClass::class;

    protected static ?string $slug = 'tax-classes';

    protected static string $permission = 'checkout.manage';

    public static function form(Form $form): Form
    {
        return $form->schema([...static::names()])->columns(2);
    }

    public static function getPages(): array
    {
        return ['index' => TaxClassResource\Pages\ManageRecords::route('/')];
    }
}
