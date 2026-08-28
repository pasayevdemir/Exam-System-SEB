# Attribution and Provenance

**Peerstack Exam System**
Copyright © 2026 **Damir Pashayev** <pashayevdamir@gmail.com>
<https://github.com/pasayevdemir> — All rights reserved.

This document records *where* authorship is stated in this work, *how* to verify
it independently, and *what* it means if any of it is missing. It is a reference
document: nothing here restricts what the software does, and none of it collects
or transmits any data about anyone.

---

## 1. The claim

This software — the exam engine, the anti-cheat and Safe Exam Browser
enforcement layer, the attempt lifecycle and autosave subsystem, the weighted
scoring engine, the question import pipeline, the mathematics rendering
pipeline, the administrative and student interfaces, the test suite, the
container topology and the deployment automation — is the original work of
**Damir Pashayev**, and is licensed to no one.

Third-party open-source components (Laravel, Bootstrap, KaTeX, CommonMark,
PhpSpreadsheet, FontAwesome, Axios, Vite) remain the property of their own
authors under their own licences. Nothing in this document claims otherwise, and
their notices are preserved wherever their code appears, including inside the
compiled bundles.

---

## 2. The seal

A single canonical string identifies this work and its author:

```
Peerstack Exam System :: Damir Pashayev <pashayevdamir@gmail.com> :: https://github.com/pasayevdemir :: 2026
```

Its SHA-256 digest — the **authorship fingerprint** — is:

```
a7e9122a4821f9871b6101467a9475750e073318f0f77225da9b007b2a690d29
```

Anyone may verify this in one command, with no access to this repository:

```bash
printf '%s' 'Peerstack Exam System :: Damir Pashayev <pashayevdamir@gmail.com> :: https://github.com/pasayevdemir :: 2026' | sha256sum
```

The fingerprint appears in the running application's HTML, in its HTTP response
headers, and in every compiled JavaScript bundle. Because SHA-256 is a one-way
function, this constant **cannot be re-pointed at a different name**: producing
another identity string with the same digest would require a second-preimage
attack on SHA-256.

The same identity is stored a second time, base64-encoded, in
[`app/Support/Authorship.php`](app/Support/Authorship.php). That encoded copy
contains none of the plaintext letters of the author's name, so the obvious way
of erasing authorship — a global search and replace for `Pashayev` — leaves it
untouched and breaks the seal instead of quietly succeeding.
`Authorship::verify()` reports the breakage, and the test suite fails on it.

---

## 3. Where authorship is stated

Attribution is not kept in one removable place. It is stated at nine
independent levels, four of which reach a *deployed* copy that has no source
files at all.

| # | Level | Location | Reaches a deployment? |
|---|-------|----------|----------------------|
| 1 | Source headers | Every first-party `.php`, `.js`, `.blade.php`, `.css` file (240 files) | No — source only |
| 2 | Licence files | [`LICENSE`](LICENSE), [`NOTICE`](NOTICE), [`AUTHORS`](AUTHORS) | No — source only |
| 3 | Package manifests | `composer.json`, `package.json` (`"license": "proprietary"`) | No — source only |
| 4 | Visible footer | "hazırlayan: **Damir Pashayev**" on every rendered page, from [`config/authorship.php`](config/authorship.php) | **Yes** |
| 5 | Rendered HTML | `<meta>` tags + a provenance comment in every page, from [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php) | **Yes** |
| 6 | HTTP headers | `X-Author`, `X-Copyright`, `X-Authorship-Fingerprint`, from [`app/Http/Middleware/AuthorshipHeaders.php`](app/Http/Middleware/AuthorshipHeaders.php) | **Yes** |
| 7 | Compiled assets | A `/*! */` legal-comment banner in every built JS and CSS bundle, from [`vite.config.js`](vite.config.js) | **Yes** |
| 8 | Cryptographic seal | `FINGERPRINT` and `SEAL` in [`app/Support/Authorship.php`](app/Support/Authorship.php) | **Yes** (via 4–7) |
| 9 | Enforcement | [`tests/Feature/AuthorshipAttributionTest.php`](tests/Feature/AuthorshipAttributionTest.php) — 17 tests | No — fails the build |

Levels 4 through 8 all read from a **single** source of truth,
`App\Support\Authorship`. Nothing restates the author's name as a second
literal, so there is one identity and one fingerprint on the page — not two
that could drift apart or contradict each other. Changing the name in one place
changes it nowhere else and breaks the seal.

Level 9 is the one that matters most in practice. Removing attribution from any
of levels 1–8 turns the test suite red. Someone removing it therefore has to
edit the test suite as well: a second, deliberate, separately dated commit,
sitting in version control immediately beside the removal it was covering.

### Checking a deployment from outside

A running instance can be checked without any access to its source:

```bash
curl -sI https://<host>/ | grep -i '^x-author\|^x-copyright\|^x-authorship'
curl -s  https://<host>/ | grep -i 'dcterms.rightsHolder\|Damir Pashayev'
```

---

## 4. Anchor digests

SHA-256 of each attribution-bearing anchor file, as of commit
`5bcd71e0968973b5c6c2c801a25fca9fb62a3911`:

```
c06fdc74174600ecc328c7a50b6b3a5a9527ec94699a01b35a150f4f5a20e0e5  LICENSE
aec3191bbd6a0d6f0c63494702ad94cc0a2b40445e76affe004551ae681b671f  NOTICE
7b649a1f958d54e06f53c621e310f4d981735b4c8961c7cd4e7f0ec776b5d05f  AUTHORS
3f3a02f5b63e13a73f15b2890402271a369fe164a22dcb144429976f664422d3  app/Support/Authorship.php
4c6c5f0e49007948ff84f0aa26864d287e134e5f9c19399be5f876a356097fa6  app/Http/Middleware/AuthorshipHeaders.php
0fc1af3405a8743421d07bfa8979549fee1c0bcdca6d60be2d575d2827eb6a35  config/authorship.php
d105e0bb34907f563337e7ff17440abfb0d58514b5b143e6f11092171a65cb63  tests/Feature/AuthorshipAttributionTest.php
d36d55d1c596d05698591ec259fcb8fce6690b2a2399fa8ec0d6965c5f75c001  vite.config.js
52de5be0de420a3e4f51c7d54e789ae5c87309ba0d2108e625af1f9a5ca80bb4  composer.json
628ad42642b53155726f925bfdcdbdc5918926939402001abe428b6bdafa8f6a  package.json
```

Regenerate with:

```bash
sha256sum LICENSE NOTICE AUTHORS app/Support/Authorship.php \
  app/Http/Middleware/AuthorshipHeaders.php config/authorship.php \
  tests/Feature/AuthorshipAttributionTest.php \
  vite.config.js composer.json package.json
```

These digests are a snapshot, not a lock. They will change when the files
legitimately change; their purpose is to fix a point in time that can be cited
later, alongside the commit hash above.

---

## 5. The primary evidence

The strongest proof of authorship in this repository is **not** any of the
notices above. It is the version control history: every commit is authored by
Damir Pashayev, each carries a timestamp, and together they record the work
being built incrementally over time. A copy without that history is a copy
without provenance, and the absence is itself informative.

To inspect it:

```bash
git log --format='%H %ad %an <%ae> %s' --date=iso
git shortlog -sne --all
```

Recommended hardening, if not already in place:

- Sign future commits and tags (`git config commit.gpgsign true`), so
  authorship is cryptographically attested rather than merely asserted — Git
  author fields are self-reported and trivially forged; signatures are not.
- Keep an independent copy of the repository, including its full history.
- Tag releases (`git tag -s v1.0 -m 'Peerstack Exam System v1.0'`).

---

## 6. On removal

Removing, obscuring, or altering these notices **does not transfer copyright,
does not grant a licence, and does not create any authorship interest in
another party.** Copyright in this work arises from its creation and is held by
the author named above regardless of what any particular copy says.

Beyond the copyright itself, these notices are *rights-management information*.
Under Article 12 of the WIPO Copyright Treaty — and the national laws
implementing it, including the EU Information Society Directive (2001/29/EC,
Art. 7) and 17 U.S.C. § 1202 — knowingly removing or falsifying such
information, in order to conceal or enable infringement, is a **separate and
independently actionable wrong**, distinct from the act of copying.

The layered design above exists for exactly that reason. It does not make
copying impossible; nothing can. It makes copying **leave a mark** — and makes
the removal of attribution a documented, dated, deliberate act rather than a
silent one.

---

## 7. Contact

Questions about licensing, or requests for written permission to use this work:

**Damir Pashayev** — <pashayevdamir@gmail.com> — <https://github.com/pasayevdemir>

---

*See also: [LICENSE](LICENSE) for licence terms, [NOTICE](NOTICE) for the full
attribution notice, [AUTHORS](AUTHORS) for the authorship record.*
