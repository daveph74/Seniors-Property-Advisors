<!doctype html>
<html lang="en-AU">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex" />
<title>{{ $title }} — Seniors Property Advisors</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />
@verbatim
<style>
:root {
  --paper: #FFFDFA;
  --paper-warm: #FFF9EE;
  --surface: #FFFFFF;
  --ink: #0F1B2D;
  --ink-soft: #1D2A3D;
  --text: #3A4A60;
  --text-mid: #5B6A7E;
  --text-quiet: #8C99AB;
  --line: #E6EAF0;
  --line-soft: #EDF0F5;
  --navy: #12294C;
  --accent: #2F5FA8;
  --accent-bg: #EEF3FA;
  --accent-border: #CFDEF4;
  --warning-text: #8A5300;
  --warning-bg: #FDF4E4;
  --warning-border: #F3E0BE;
  --success-text: #166149;
  --success-bg: #E9F5F0;
  --success-border: #CDE8DE;
  --neutral-bg: #F2F4F8;
  --neutral-border: #E2E7EF;
  --radius: 8px;
  --radius-lg: 12px;
  --radius-xl: 14px;
  --shadow-card: 0 1px 2px rgba(16,32,58,0.04);
  --shadow-frame: 0 8px 28px rgba(16,32,58,0.08);
  --sans: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
  --serif: 'Instrument Serif', ui-serif, Georgia, 'Times New Roman', serif;
  --mono: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, monospace;
}

@media (prefers-color-scheme: dark) {
  :root {
    --paper: #0E1725;
    --paper-warm: #131E2F;
    --surface: #162234;
    --ink: #F2F5FA;
    --ink-soft: #E3EAF4;
    --text: #BAC6D8;
    --text-mid: #93A2B8;
    --text-quiet: #6F7F96;
    --line: #253449;
    --line-soft: #1D2A3D;
    --navy: #DCE6F5;
    --accent: #86B2EA;
    --accent-bg: #17293F;
    --accent-border: #2A445F;
    --warning-text: #F0C583;
    --warning-bg: #2A2113;
    --warning-border: #4A3A1D;
    --success-text: #86D9B6;
    --success-bg: #12271F;
    --success-border: #234438;
    --neutral-bg: #16222F;
    --neutral-border: #253449;
    --shadow-card: 0 1px 2px rgba(0,0,0,0.4);
    --shadow-frame: 0 8px 28px rgba(0,0,0,0.5);
  }
}

* { box-sizing: border-box; }

html { scroll-behavior: smooth; scroll-padding-top: 2rem; }

body {
  margin: 0;
  background: var(--paper);
  color: var(--text);
  font-family: var(--sans);
  font-size: 17px;
  line-height: 1.7;
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
}

a { color: var(--accent); text-decoration-thickness: 1px; text-underline-offset: 2px; }
a:hover { color: var(--navy); }

:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 3px;
  border-radius: 2px;
}

.skip {
  position: absolute; left: -9999px; top: 0;
  background: var(--navy); color: #fff; padding: 12px 18px; z-index: 20;
  border-radius: 0 0 var(--radius) 0;
}
.skip:focus { left: 0; color: #fff; }

/* ---------- shell ---------- */

.shell {
  max-width: 1180px;
  margin: 0 auto;
  padding: 0 32px;
  display: grid;
  grid-template-columns: 244px minmax(0, 1fr);
  gap: 64px;
  align-items: start;
}

/* ---------- contents ---------- */

.toc {
  position: sticky;
  top: 0;
  max-height: 100vh;
  overflow-y: auto;
  padding: 40px 0 48px;
  scrollbar-width: thin;
}

.toc__brand {
  display: block;
  font-family: var(--serif);
  font-size: 20px;
  line-height: 1.25;
  color: var(--navy);
  text-decoration: none;
  padding-bottom: 18px;
  margin-bottom: 18px;
  border-bottom: 1px solid var(--line);
}
.toc__brand span {
  display: block;
  font-family: var(--sans);
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--text-quiet);
  margin-bottom: 6px;
}

.toc__heading {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--text-quiet);
  margin: 0 0 12px;
}

.toc__list { list-style: none; margin: 0; padding: 0; }

.toc__link {
  display: block;
  position: relative;
  padding: 6px 0 6px 14px;
  font-size: 14px;
  line-height: 1.45;
  color: var(--text-mid);
  text-decoration: none;
  border-left: 2px solid transparent;
  transition: color .15s ease, border-color .15s ease;
}
.toc__link:hover { color: var(--navy); border-left-color: var(--line); }
.toc__link[aria-current="true"] {
  color: var(--navy);
  font-weight: 600;
  border-left-color: var(--accent);
}
.toc__link em {
  font-style: normal;
  font-family: var(--serif);
  font-size: 15px;
  color: var(--accent);
  margin-right: 7px;
}

/* ---------- document ---------- */

.doc {
  max-width: 68ch;
  padding: 40px 0 120px;
}

/* ---------- masthead ---------- */

.masthead {
  padding: 40px 0 44px;
  border-bottom: 1px solid var(--line);
  margin-bottom: 8px;
}

.masthead__eyebrow {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--accent);
  margin: 0 0 20px;
}

.masthead h1 {
  font-family: var(--serif);
  font-weight: 400;
  font-size: clamp(2.7rem, 6vw, 4rem);
  line-height: 1.04;
  letter-spacing: -0.015em;
  color: var(--navy);
  margin: 0 0 26px;
}

.masthead p {
  font-size: 19px;
  line-height: 1.65;
  color: var(--text-mid);
  margin: 0 0 16px;
}
.masthead p:first-of-type { color: var(--ink-soft); }
.masthead p strong { color: var(--navy); }

/* ---------- chapters ---------- */

.chapter { padding-top: 60px; }
.chapter + .chapter { border-top: 1px solid var(--line-soft); }

.chapter h2 {
  font-family: var(--serif);
  font-weight: 400;
  font-size: clamp(1.85rem, 3.4vw, 2.5rem);
  line-height: 1.15;
  letter-spacing: -0.01em;
  color: var(--navy);
  margin: 0 0 26px;
  scroll-margin-top: 24px;
}

.chapter__eyebrow {
  display: flex;
  align-items: baseline;
  gap: 10px;
  font-family: var(--sans);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--text-quiet);
  margin-bottom: 4px;
}

.chapter__num {
  font-family: var(--serif);
  font-size: 2.7rem;
  line-height: 1;
  letter-spacing: -0.02em;
  color: var(--accent);
}

.chapter__title { display: block; }
.chapter__title::first-letter { text-transform: uppercase; }

/* the one section a client administrator cannot open */
.chapter--restricted {
  background: var(--neutral-bg);
  border: 1px solid var(--neutral-border);
  border-radius: var(--radius-xl);
  padding: 40px 36px 44px;
  margin-top: 60px;
}
.chapter--restricted + .chapter { border-top: 0; }

.chapter h3 {
  font-family: var(--sans);
  font-size: 1.12rem;
  font-weight: 600;
  letter-spacing: -0.005em;
  color: var(--ink);
  margin: 42px 0 14px;
  scroll-margin-top: 24px;
}

/* ---------- prose ---------- */

.doc p { margin: 0 0 20px; }
.doc strong { color: var(--navy); font-weight: 600; }
.doc em { color: var(--text-mid); }

.doc ul, .doc ol { margin: 0 0 22px; padding: 0; list-style: none; }
.doc li { position: relative; margin-bottom: 12px; padding-left: 26px; }
.doc li:last-child { margin-bottom: 0; }

.doc ul > li::before {
  content: "";
  position: absolute;
  left: 6px;
  top: 0.72em;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: var(--accent);
}

.doc ol { counter-reset: step; }
.doc ol > li { counter-increment: step; padding-left: 38px; }
.doc ol > li::before {
  content: counter(step);
  position: absolute;
  left: 0;
  top: 0.16em;
  width: 25px;
  height: 25px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--accent-bg);
  border: 1px solid var(--accent-border);
  color: var(--accent);
  font-size: 12.5px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.doc li > ul, .doc li > ol { margin-top: 12px; }

/* a sentence the author stopped to make on its own */
.doc p.lede {
  font-family: var(--serif);
  font-weight: 400;
  font-size: clamp(1.45rem, 2.6vw, 1.85rem);
  line-height: 1.3;
  letter-spacing: -0.01em;
  color: var(--navy);
  margin: 2px 0 24px;
}

/* ---------- the rules that stop you ---------- */

.doc blockquote {
  margin: 30px 0;
  padding: 26px 30px;
  background: var(--accent-bg);
  border: 1px solid var(--accent-border);
  border-left: 4px solid var(--accent);
  border-radius: var(--radius-lg);
  position: relative;
}
.doc blockquote p,
.doc blockquote p.lede {
  margin: 0;
  font-family: var(--sans);
  font-size: 18px;
  font-weight: 400;
  line-height: 1.6;
  letter-spacing: 0;
  color: var(--ink-soft);
}
.doc blockquote p + p { margin-top: 12px; }
.doc blockquote strong { color: var(--navy); }

/* ---------- addresses and keys ---------- */

.doc code {
  font-family: var(--mono);
  font-size: 0.855em;
  padding: 2px 6px;
  background: var(--paper-warm);
  border: 1px solid var(--line);
  border-radius: 5px;
  color: var(--ink-soft);
  white-space: nowrap;
}

/* ---------- tables ---------- */

.table-wrap {
  margin: 0 0 26px;
  overflow-x: auto;
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-card);
}

.doc table {
  width: 100%;
  border-collapse: collapse;
  font-size: 15px;
}

.doc thead th {
  text-align: left;
  padding: 13px 18px;
  background: var(--paper-warm);
  border-bottom: 1px solid var(--line);
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: var(--text-quiet);
  white-space: nowrap;
}

.doc tbody td {
  padding: 13px 18px;
  border-bottom: 1px solid var(--line-soft);
  vertical-align: top;
  line-height: 1.55;
}
.doc tbody tr:last-child td { border-bottom: 0; }
.doc tbody tr:nth-child(even) td { background: color-mix(in srgb, var(--paper-warm) 45%, transparent); }
.doc tbody td:first-child { color: var(--ink); font-weight: 500; }

/* ---------- role pills ---------- */

.pill {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.02em;
  white-space: nowrap;
}
.pill--both {
  background: var(--success-bg);
  border: 1px solid var(--success-border);
  color: var(--success-text);
}
.pill--super {
  background: var(--warning-bg);
  border: 1px solid var(--warning-border);
  color: var(--warning-text);
}

/* ---------- colophon ---------- */

.colophon {
  margin-top: 72px;
  padding-top: 22px;
  border-top: 1px solid var(--line);
  font-size: 13px;
  line-height: 1.6;
  color: var(--text-quiet);
}
.colophon strong { color: var(--text-mid); font-weight: 600; }

/* ---------- narrow ---------- */

@media (max-width: 900px) {
  .shell {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
    padding: 0 22px;
  }
  .toc {
    position: static;
    max-height: none;
    overflow: visible;
    padding: 28px 0 0;
    border-bottom: 1px solid var(--line);
  }
  /* One scrolling row, not a wrapped block: seventeen chips stacked is a wall of navigation
     between a reader and the first sentence of the document they opened. */
  .toc__list {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    padding-bottom: 22px;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }
  .toc__list::-webkit-scrollbar { display: none; }
  .toc__link {
    flex: none;
    padding: 5px 13px;
    border: 1px solid var(--line);
    border-left-width: 1px;
    border-radius: 999px;
    font-size: 13px;
    white-space: nowrap;
  }
  .toc__link[aria-current="true"] {
    border-color: var(--accent-border);
    background: var(--accent-bg);
  }
  .doc { padding-top: 8px; }
  .masthead { padding-top: 32px; }
  .chapter--restricted { padding: 30px 22px 34px; }
  body { font-size: 16.5px; }
}

/* ---------- print ---------- */

@media print {
  @page { margin: 18mm 16mm; }
  html { scroll-behavior: auto; }
  body {
    background: #fff;
    color: #000;
    font-size: 10.5pt;
    line-height: 1.55;
  }
  .toc, .skip { display: none !important; }
  .shell { display: block; max-width: none; padding: 0; }
  .doc { max-width: none; padding: 0; }
  a { color: #000; text-decoration: underline; }
  .doc a[href^="http"]::after {
    content: " (" attr(href) ")";
    font-size: 8.5pt;
    color: #444;
    word-break: break-all;
  }
  /* Navy prints as mid grey on a mono office printer, which makes the emphasised words fainter
     than the sentence around them — the opposite of what bold is for. */
  .doc strong, .doc p.lede, .toc__brand { color: #000; }
  .masthead h1 { font-size: 26pt; color: #000; }
  .masthead { border-bottom: 1pt solid #999; }
  .chapter { padding-top: 22pt; break-inside: auto; }
  .chapter + .chapter { border-top: 0; }
  .chapter h2 {
    font-size: 16pt;
    color: #000;
    break-after: avoid;
    break-inside: avoid;
  }
  .chapter h3 { break-after: avoid; }
  .chapter--restricted {
    background: none;
    border: 1pt solid #999;
    padding: 14pt;
  }
  .doc blockquote,
  .table-wrap,
  .doc li { break-inside: avoid; }
  .doc blockquote {
    background: none;
    border: 1pt solid #999;
    border-left: 3pt solid #000;
  }
  .table-wrap { box-shadow: none; border: 1pt solid #999; }
  .doc thead th { background: #eee; color: #000; }
  .doc tbody tr:nth-child(even) td { background: none; }
  .doc code { background: none; border: 0; }
  .pill { border: 1pt solid #999 !important; background: none !important; color: #000 !important; }
  .colophon { color: #444; }
}
</style>
@endverbatim
</head>
<body>
<a class="skip" href="#guide">Skip to the guide</a>

<div class="shell">
    <aside class="toc">
        <a class="toc__brand" href="#top"><span>Seniors Property Advisors</span>{{ $title }}</a>
        <p class="toc__heading">Contents</p>
        <nav aria-label="Contents">
            <ul class="toc__list">
                @foreach ($contents as $entry)
                    <li>
                        <a class="toc__link" href="#{{ $entry['id'] }}">@if ($entry['number'])<em>{{ $entry['number'] }}</em>@endif{{ $entry['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>

    <main class="doc" id="top">
        <header class="masthead">
            <p class="masthead__eyebrow">Staff handbook</p>
            {!! $masthead !!}
        </header>

        <div id="guide">
            {!! $body !!}
        </div>

        <p class="colophon">
            <strong>{{ $title }}</strong> — generated from <code>docs/cms-user-guide.md</code>, which is
            the copy to edit. Rebuild with <code>php artisan docs:guide</code> after changing it.
        </p>
    </main>
</div>

@verbatim
<script>
(function () {
    var links = Array.prototype.slice.call(document.querySelectorAll('.toc__link'));
    var targets = links.map(function (link) {
        return { link: link, heading: document.getElementById(decodeURIComponent(link.hash.slice(1))) };
    }).filter(function (pair) { return pair.heading; });

    if (!targets.length) return;

    var current = null;
    var queued = false;

    /* The last heading to have passed the top of the window is the one being read. Measured on
       every frame that scrolls rather than remembered from an observer callback, because a
       remembered rectangle is a rectangle from where the page used to be. */
    function mark() {
        queued = false;

        var found = targets[0];

        for (var i = 0; i < targets.length; i += 1) {
            if (targets[i].heading.getBoundingClientRect().top <= 140) found = targets[i];
        }

        /* The last section is shorter than the window, so its heading never reaches the top and it
           could otherwise never be the marked one. At the bottom of the page it is what is being read. */
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
            found = targets[targets.length - 1];
        }

        if (found === current) return;

        if (current) current.link.removeAttribute('aria-current');
        found.link.setAttribute('aria-current', 'true');
        current = found;

        if (found.link.scrollIntoView && window.innerWidth > 900) {
            var box = found.link.getBoundingClientRect();
            if (box.top < 0 || box.bottom > window.innerHeight) {
                found.link.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function schedule() {
        if (queued) return;
        queued = true;
        window.requestAnimationFrame(mark);
    }

    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    mark();
})();
</script>
@endverbatim
</body>
</html>
