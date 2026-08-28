<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Support;

/**
 * The single, authoritative record of who wrote this application.
 *
 * Every other attribution site in the codebase — the meta tags in the layout,
 * the HTTP response headers, the compiled asset banners, the NOTICE file — is
 * either fed from this class or checked against it. That is deliberate: it
 * means attribution cannot be "half removed". Deleting the name from one place
 * leaves the others disagreeing with this one, and the attribution test turns
 * red.
 *
 * The seal below is what makes that check possible. FINGERPRINT is the SHA-256
 * digest of CANONICAL, and SEAL is the same identity encoded again in a form
 * that contains none of the plaintext letters of the author's name. A search
 * and replace for "Pashayev" therefore silently breaks the digest instead of
 * quietly succeeding, and `verify()` reports it.
 *
 * None of this is obfuscation for its own sake. Under Article 12 of the WIPO
 * Copyright Treaty — and the national laws implementing it — these constants
 * are rights-management information, and stripping them is a distinct wrong
 * from copying the work itself.
 */
final class Authorship
{
    /** The name of the work. */
    public const PROJECT = 'Peerstack Exam System';

    /** The sole author and copyright holder. */
    public const AUTHOR = 'Damir Pashayev';

    /** The author's contact address. */
    public const EMAIL = 'pashayevdamir@gmail.com';

    /** The author's canonical public profile. */
    public const LINK = 'https://github.com/pasayevdemir';

    /** The year of first publication. */
    public const YEAR = '2026';

    /** The human-readable copyright line. */
    public const COPYRIGHT = 'Copyright (c) 2026 Damir Pashayev. All rights reserved.';

    /** The licence under which this work is distributed — that is, not at all. */
    public const LICENCE = 'Proprietary. All rights reserved.';

    /**
     * The exact string the seal is computed over.
     *
     * Assembled from the constants above rather than written out again, so the
     * digest below can never drift out of step with the identity it certifies.
     */
    public const CANONICAL = self::PROJECT.' :: '.self::AUTHOR.' <'.self::EMAIL.'> :: '.self::LINK.' :: '.self::YEAR;

    /**
     * SHA-256 of CANONICAL.
     *
     * Anyone may verify this independently:
     *
     *   echo -n 'Peerstack Exam System :: Damir Pashayev \
     *     <pashayevdamir@gmail.com> :: https://github.com/pasayevdemir :: 2026' \
     *     | sha256sum
     *
     * A digest is a one-way function, so this constant cannot be forged into
     * naming somebody else: producing a different name with this same digest
     * is a second-preimage attack on SHA-256.
     */
    public const FINGERPRINT = 'a7e9122a4821f9871b6101467a9475750e073318f0f77225da9b007b2a690d29';

    /**
     * CANONICAL again, base64-encoded.
     *
     * The point of holding a second copy in this form is that it survives the
     * obvious way of erasing authorship — a global replace of the author's
     * name — because the encoded text contains none of its letters. Decode it
     * with base64_decode() to read it back.
     */
    public const SEAL = 'UGVlcnN0YWNrIEV4YW0gU3lzdGVtIDo6IERhbWlyIFBhc2hheWV2IDxwYXNoYXlldmRhbWlyQGdtYWlsLmNvbT4gOjogaHR0cHM6Ly9naXRodWIuY29tL3Bhc2F5ZXZkZW1pciA6OiAyMDI2';

    /**
     * The attribution as it should appear to a human reader.
     */
    public static function notice(): string
    {
        return self::PROJECT.' — '.self::COPYRIGHT;
    }

    /**
     * The attribution as name => content pairs, for HTML meta tags.
     *
     * @return array<string, string>
     */
    public static function meta(): array
    {
        return [
            'author' => self::AUTHOR.' <'.self::EMAIL.'>',
            'copyright' => self::COPYRIGHT,
            'application-name' => self::PROJECT,
            'dcterms.rightsHolder' => self::AUTHOR,
            'dcterms.dateCopyrighted' => self::YEAR,
            'dcterms.license' => self::LICENCE,
            'x-authorship-fingerprint' => self::FINGERPRINT,
        ];
    }

    /**
     * The attribution as HTTP response headers.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'X-Author' => self::AUTHOR.' <'.self::EMAIL.'>',
            'X-Copyright' => self::COPYRIGHT,
            'X-Authorship-Fingerprint' => self::FINGERPRINT,
        ];
    }

    /**
     * Recompute the seal and report whether it still certifies this identity.
     *
     * False means the constants above have been edited: either the identity was
     * changed and the digest left behind, or the digest was changed and the
     * identity left behind. Either way the file no longer says what its author
     * wrote, and the attribution test fails on it.
     */
    public static function verify(): bool
    {
        return hash_equals(self::FINGERPRINT, hash('sha256', self::CANONICAL))
            && hash_equals(self::CANONICAL, (string) base64_decode(self::SEAL, true));
    }
}
