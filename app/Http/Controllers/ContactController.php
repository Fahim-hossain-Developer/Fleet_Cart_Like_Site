<?php

namespace App\Http\Controllers;

use App\Domain\Content\Actions\SubmitContact;
use Illuminate\Http\Request;

final class ContactController extends Controller
{
    public function show(): mixed
    {
        return view('themes.default.content.contact');
    }

    public function store(Request $r): mixed
    {
        app(SubmitContact::class)->execute($r->all());

        return redirect()->route('contact')->with('status', __('content.contact_received'));
    }
}
