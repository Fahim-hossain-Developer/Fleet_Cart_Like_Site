<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\ContactMessage;

final class SubmitContact
{
    /** @param array<string,mixed> $input */
    public function execute(array $input): void
    {
        $d = validator($input, ['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:254', 'message' => 'required|string|min:10|max:5000', 'consent' => 'accepted', 'website' => 'nullable|string|max:0'])->validate();
        ContactMessage::create(['name' => $d['name'], 'email' => strtolower(trim($d['email'])), 'message' => $d['message'], 'consented_at' => now()]);
    }
}
