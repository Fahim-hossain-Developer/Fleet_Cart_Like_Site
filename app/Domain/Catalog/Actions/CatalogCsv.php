<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CatalogCsv
{
    public const COLUMNS = ['sku', 'slug', 'name_en', 'name_bn', 'price', 'qty', 'status'];

    /** @return array{imported:int,errors:list<array{row:int,messages:array<mixed>}>} */
    public function import(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new \RuntimeException('CSV not readable');
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        $errors = [];
        $count = 0;
        $row = 1;
        if ($header) {
            $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        }
        if (! $header || array_diff(self::COLUMNS, $header)) {
            fclose($handle);

            return ['imported' => 0, 'errors' => [['row' => 1, 'messages' => [__('catalog.invalid_header')]]]];
        }
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $row++;
            if ($row > 501) {
                $errors[] = ['row' => $row, 'messages' => [__('catalog.csv_limit')]];
                break;
            }
            if (count($values) !== count($header)) {
                $errors[] = ['row' => $row, 'messages' => [__('catalog.invalid_row')]];

                continue;
            }
            $d = array_combine($header, $values);
            $validator = Validator::make($d, ['sku' => ['required', 'string', 'max:220', Rule::unique('products', 'sku'), Rule::unique('product_variants', 'sku')], 'slug' => ['required', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', 'max:220', Rule::unique('products', 'slug')], 'name_en' => 'required|string|max:255', 'name_bn' => 'nullable|string|max:255', 'price' => 'required|integer|min:0|max:100000000000', 'qty' => 'required|integer|min:0|max:1000000', 'status' => 'required|in:draft,active']);
            if ($validator->fails()) {
                $errors[] = ['row' => $row, 'messages' => $validator->errors()->all()];

                continue;
            }
            DB::transaction(fn () => Product::create(['sku' => $d['sku'], 'slug' => $d['slug'], 'name' => ['en' => $d['name_en'], 'bn' => $d['name_bn'] ?: $d['name_en']], 'price' => (int) $d['price'], 'qty' => (int) $d['qty'], 'status' => $d['status']]));
            $count++;
        }
        fclose($handle);

        return ['imported' => $count, 'errors' => $errors];
    }

    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
    }
}
