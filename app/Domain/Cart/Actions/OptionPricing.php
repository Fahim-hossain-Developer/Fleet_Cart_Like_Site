<?php

namespace App\Domain\Cart\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class OptionPricing
{
    /** @param array<array-key,mixed> $input
     * @return array{options:array<array-key,mixed>,fixed:int,percent:int,labels:list<array{label:string,value:string}>} */
    public function execute(Product $product, array $input, string $cartToken, bool $persisted = false): array
    {
        $options = [];
        $labels = [];
        $fixed = 0;
        $percent = 0;
        foreach ($product->options()->with('values')->get() as $option) {
            $value = $input[(string) $option->id] ?? null;
            if ($value === null || $value === '' || $value === []) {
                if ($option->required) {
                    throw ValidationException::withMessages(['options.'.$option->id => __('checkout.option_required', ['name' => $option->label])]);
                }

                continue;
            }
            $adjustment = (int) $option->price;
            $valid = collect();
            if (in_array($option->type, ['dropdown', 'radio', 'checkbox'], true)) {
                $values = $option->type === 'checkbox' ? (array) $value : [$value];
                $values = array_values(array_unique(array_map('strval', $values)));
                if (count($values) > 50) {
                    throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                }
                $valid = $option->values->whereIn('id', $values);
                if ($valid->count() !== count($values)) {
                    throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                }
                $adjustment += (int) $valid->sum('price');
                $value = $option->type === 'checkbox' ? $values : (string) $values[0];
            } elseif ($option->type === 'file') {
                if ($persisted) {
                    if (! is_array($value) || ! isset($value['file']) || ! str_starts_with($value['file'], 'cart-options/')) {
                        throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                    }
                } else {
                    if (! $value instanceof UploadedFile) {
                        throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                    }validator(['file' => $value], ['file' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:5120'])->validate();
                    $value = ['file' => $value->store('cart-options/'.$cartToken, 'local'), 'name' => mb_substr($value->getClientOriginalName(), 0, 200)];
                }
            } else {
                if (! is_scalar($value)) {
                    throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                }$value = (string) $value;
                validator(['value' => $value], ['value' => $option->type === 'date' ? 'date_format:Y-m-d' : 'string|max:1000'])->validate();
            }
            if ($option->price_type === 'percent') {
                if ($adjustment > 100) {
                    throw ValidationException::withMessages(['options' => __('checkout.invalid_options')]);
                }$percent += $adjustment;
            } else {
                $fixed += $adjustment;
            }
            $labelValue = in_array($option->type, ['dropdown', 'radio', 'checkbox'], true) ? $valid->map(fn ($v) => $v->label)->implode(', ') : ($option->type === 'file' ? ($value['name'] ?? __('checkout.file')) : (string) $value);
            $labels[] = ['label' => $option->label, 'value' => $labelValue];
            $options[(string) $option->id] = $value;
        }
        ksort($options);

        return ['options' => $options, 'fixed' => $fixed, 'percent' => $percent, 'labels' => $labels];
    }
}
