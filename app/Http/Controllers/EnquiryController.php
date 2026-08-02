<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Takes an enquiry and keeps it.
 *
 * Scope §12 draws a line here: the wording around this form is the CMS's, but the fields, the
 * validation and wherever an enquiry eventually goes are the development team's, and a CMS user
 * must not be able to change them. So they live in code, and none of it is editable through the
 * admin — only the heading, intro, consent wording and confirmation message are.
 */
class EnquiryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'suburb' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:4000'],
            'consent' => ['accepted'],
            'page' => ['nullable', 'string', 'max:190'],
        ], [
            'consent.accepted' => 'Please tick the box to say we may contact you.',
            'email.email' => 'That email address does not look right.',
        ]);

        Enquiry::create([
            'name' => $this->clean($data['name']),
            'email' => $this->clean($data['email']),
            'phone' => $this->clean($data['phone'] ?? null),
            'suburb' => $this->clean($data['suburb'] ?? null),
            'message' => $this->clean($data['message'] ?? null),
            'consented' => true,
            'page_slug' => $data['page'] ?? null,
        ]);

        /* The confirmation itself is the editor's wording, so the page shows it — this only says
           that something arrived. */
        return back()->with('enquiry', 'sent');
    }

    private function clean(?string $value): ?string
    {
        return $value === null ? null : (trim(strip_tags($value)) ?: null);
    }
}
