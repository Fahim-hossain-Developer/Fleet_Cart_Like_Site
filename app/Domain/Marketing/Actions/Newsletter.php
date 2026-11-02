<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Marketing\Models\NewsletterMessage;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Jobs\DeliverNewsletter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Newsletter
{
    public function hash(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), config('app.key'));
    }

    /** @param array<string,mixed> $input */
    public function subscribe(array $input): void
    {
        $d = validator($input, ['email' => 'required|email:rfc|max:254', 'consent' => 'accepted', 'website' => 'nullable|string|max:0', 'source' => 'nullable|in:storefront,blog,popup'])->validate();
        $email = strtolower(trim($d['email']));
        $hash = $this->hash($email);
        $key = 'newsletter:'.$hash;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => __('orders.throttled')]);
        }RateLimiter::hit($key, 86400);
        DB::transaction(function () use ($d, $email, $hash) {
            NewsletterSubscriber::upsert([['email_hash' => $hash, 'email' => Crypt::encryptString($email), 'status' => 'pending', 'source' => $d['source'] ?? 'storefront', 'locale' => app()->getLocale(), 'generation' => 0, 'created_at' => now(), 'updated_at' => now()]], ['email_hash'], ['updated_at']);
            $s = NewsletterSubscriber::where('email_hash', $hash)->lockForUpdate()->firstOrFail();
            if ($s->status === 'active') {
                return;
            }$token = Str::random(64);
            $unsubscribe = Str::random(64);
            $s->update(['status' => 'pending', 'generation' => $s->generation + 1, 'confirmation_hash' => hash('sha256', $token), 'confirmation_expires_at' => now()->addDay(), 'unsubscribe_hash' => hash('sha256', $unsubscribe), 'unsubscribe_token' => $unsubscribe, 'consented_at' => now(), 'confirmed_at' => null, 'unsubscribed_at' => null, 'locale' => app()->getLocale()]);
            $m = NewsletterMessage::create(['newsletter_subscriber_id' => $s->id, 'kind' => 'confirmation', 'generation' => $s->generation, 'payload' => ['token' => $token], 'expires_at' => now()->addDay()]);
            DB::afterCommit(function () use ($m) {
                try {
                    DeliverNewsletter::dispatch($m->id);
                } catch (\Throwable $e) {
                    Log::warning('Newsletter confirmation pending', ['message_id' => $m->id]);
                }
            });
        }, 3);
    }

    public function confirm(string $token): bool
    {
        if (! preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
            return false;
        }

        return DB::transaction(function () use ($token) {
            $s = NewsletterSubscriber::where('confirmation_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $s || $s->status !== 'pending' || ! $s->confirmation_expires_at || now()->gte($s->confirmation_expires_at)) {
                return false;
            }$s->update(['status' => 'active', 'confirmed_at' => now(), 'confirmation_hash' => null, 'confirmation_expires_at' => null]);

            return true;
        });
    }

    public function unsubscribe(string $token): void
    {
        if (! preg_match('/^[a-zA-Z0-9]{64}$/D', $token)) {
            return;
        }
        NewsletterSubscriber::where('unsubscribe_hash', hash('sha256', $token))->update(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'confirmation_hash' => null, 'confirmation_expires_at' => null]);
    }

    public function masked(NewsletterSubscriber $s): string
    {
        $email = $s->email;
        $at = strrpos($email, '@');

        return $at === false ? '••••' : mb_substr($email, 0, 2).'••••'.substr($email, $at);
    }
}
