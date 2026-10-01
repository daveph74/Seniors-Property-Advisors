<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New {{ $enquiry->sourceLabel() }} enquiry</title>
</head>
<body style="margin:0;padding:24px;background:#F5FAFD;font-family:Arial,Helvetica,sans-serif;color:#1D242E;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #E3EAF2;border-radius:8px;">
        <tr>
            <td style="padding:28px 32px;">
                <h1 style="margin:0 0 6px;font-size:20px;color:#0D223F;">New {{ $enquiry->sourceLabel() }} enquiry</h1>
                <p style="margin:0 0 24px;font-size:14px;color:#5A6472;">
                    Reference {{ $enquiry->reference() }} &middot; {{ $enquiry->created_at?->timezone(config('app.timezone'))->format('j M Y, g:i a') }}
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.5;">
                    <tr><td style="padding:4px 0;width:160px;color:#5A6472;">Name</td><td style="padding:4px 0;">{{ $enquiry->name }}</td></tr>
                    <tr><td style="padding:4px 0;color:#5A6472;">Email</td><td style="padding:4px 0;"><a href="mailto:{{ $enquiry->email }}" style="color:#3E79BF;">{{ $enquiry->email }}</a></td></tr>
                    @if (filled($enquiry->phone))
                        <tr><td style="padding:4px 0;color:#5A6472;">Phone</td><td style="padding:4px 0;"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $enquiry->phone) }}" style="color:#3E79BF;">{{ $enquiry->phone }}</a></td></tr>
                    @endif
                    @if (filled($enquiry->suburb))
                        <tr><td style="padding:4px 0;color:#5A6472;">Suburb</td><td style="padding:4px 0;">{{ $enquiry->suburb }}</td></tr>
                    @endif
                    @foreach ($answers as $answer)
                        <tr><td style="padding:4px 0;color:#5A6472;">{{ $answer['label'] }}</td><td style="padding:4px 0;">{{ $answer['value'] }}</td></tr>
                    @endforeach
                    @if (filled($enquiry->page_slug))
                        <tr><td style="padding:4px 0;color:#5A6472;">Sent from</td><td style="padding:4px 0;">{{ $enquiry->page_slug }}</td></tr>
                    @endif
                </table>

                @if (filled($enquiry->message))
                    <h2 style="margin:24px 0 8px;font-size:15px;color:#0D223F;">In their own words</h2>
                    <p style="margin:0;font-size:15px;line-height:1.6;white-space:pre-line;">{{ $enquiry->message }}</p>
                @endif

                <p style="margin:28px 0 0;">
                    <a href="{{ $cmsUrl }}" style="display:inline-block;padding:10px 18px;background:#0D223F;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;">Open in the CMS</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
