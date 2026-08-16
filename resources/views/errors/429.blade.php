<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>One moment — Seniors Property Advisors</title>
    {{--
      Written for the person, not the protocol: no "429", no "rate limit", no jargon. Somebody who
      reads too fast for a limiter is far more likely to be a reader on a shared connection than an
      attacker, and on a site for seniors a page that blames them costs a visit.

      No stylesheet: this response must not depend on the built assets, since one reason for seeing it
      is a burst that also hit the asset routes. Type large enough to read without leaning in.
    --}}
    <style>
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            background: #f4f6f9; color: #12294c; padding: 24px;
            font: 400 20px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 34rem; text-align: center; }
        h1 { font-size: 30px; line-height: 1.25; margin: 0 0 12px; }
        p { margin: 0 0 20px; }
        a {
            display: inline-block; padding: 14px 26px; border-radius: 12px;
            background: #12294c; color: #fff; text-decoration: none; font-weight: 600;
        }
        a:focus-visible { outline: 3px solid #1b3a69; outline-offset: 3px; }
    </style>
</head>
<body>
    <main>
        <h1>We’re a bit busy just now</h1>
        <p>Please wait {{ $words ?? 'a minute' }} and try again — nothing has gone wrong, and nothing you sent has been lost.</p>
        <p><a href="{{ url('/') }}">Back to the home page</a></p>
    </main>
</body>
</html>
