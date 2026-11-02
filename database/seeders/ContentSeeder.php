<?php

namespace Database\Seeders;

use App\Domain\Content\Models\FaqEntry;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Models\Page;
use App\Domain\Settings\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $flag = Setting::firstOrCreate(['key' => 'content.starter_seeded'], ['group' => 'internal', 'value' => false]);
        DB::transaction(function () use ($flag) {
            $flag = Setting::lockForUpdate()->findOrFail($flag->id);
            if ((bool) $flag->value) {
                return;
            }
            // Starter editorial content only. Never overwrite an owner's changes or publish legal promises.
            Page::firstOrCreate(['slug' => 'about'], ['title' => ['en' => 'About our store', 'bn' => 'আমাদের দোকান সম্পর্কে'], 'body' => ['en' => '<p>Browse our current collection, ask us about a product, or track an existing order. Our team is here to help you choose.</p>', 'bn' => '<p>আমাদের বর্তমান পণ্য দেখুন, পণ্য সম্পর্কে প্রশ্ন করুন অথবা অর্ডার ট্র্যাক করুন। আপনার পছন্দে সহায়তা করতে আমরা আছি।</p>'], 'status' => 'published']);
            foreach (['terms', 'privacy', 'returns'] as $slug) {
                Page::firstOrCreate(['slug' => $slug], ['title' => ['en' => ucfirst($slug).' — owner review required', 'bn' => 'প্রকাশের আগে মালিকের পর্যালোচনা প্রয়োজন'], 'body' => ['en' => '<p>Replace this draft with your actual store policy before publishing.</p>', 'bn' => '<p>প্রকাশের আগে দোকানের প্রকৃত নীতিমালা লিখুন।</p>'], 'status' => 'draft']);
            }
            foreach (['header', 'mobile', 'footer'] as $key) {
                $menu = Menu::firstOrCreate(['key' => $key], ['title' => ['en' => ucfirst($key), 'bn' => 'স্টোর মেনু'], 'status' => 'published']);
                if (! $menu->wasRecentlyCreated) {
                    continue;
                }
                foreach ([['/shop', 'Shop', 'দোকান'], ['/blog', 'Journal', 'ব্লগ'], ['/faq', 'Help', 'সহায়তা'], ['/contact', 'Contact', 'যোগাযোগ']] as $sort => $link) {
                    MenuItem::firstOrCreate(['menu_id' => $menu->id, 'url' => $link[0]], ['title' => ['en' => $link[1], 'bn' => $link[2]], 'status' => 'published', 'sort' => $sort]);
                }
            }
            FaqEntry::firstOrCreate(['group' => 'general', 'sort' => 0], ['title' => ['en' => 'How can I track an order?', 'bn' => 'কীভাবে অর্ডার ট্র্যাক করব?'], 'body' => ['en' => '<p>Use Track order with your order number and the phone number entered at checkout.</p>', 'bn' => '<p>অর্ডার নম্বর এবং চেকআউটে দেওয়া ফোন নম্বর দিয়ে অর্ডার ট্র্যাক করুন।</p>'], 'status' => 'published']);
            $flag->update(['value' => true]);
        });
    }
}
