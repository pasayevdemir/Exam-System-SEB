<?php

/**
 * Authorship and provenance metadata for the Peerstack Exam System.
 *
 * Read by the application layout so attribution is rendered from a single source
 * rather than hard-coded into each view.
 *
 * Every value here is derived from App\Support\Authorship rather than written
 * out again. That class holds the seal — the SHA-256 fingerprint and the
 * base64 copy of the canonical identity — and is what the attribution test
 * checks. Restating the author's name here as a second literal would mean two
 * places to edit, two things to keep in step, and two different fingerprints
 * appearing on the same rendered page.
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @see Authorship
 * @see ATTRIBUTION.md
 */

use App\Support\Authorship;

return [

    'author' => Authorship::AUTHOR,

    'email' => Authorship::EMAIL,

    'github' => Authorship::LINK,

    'copyright' => '© '.Authorship::YEAR.' '.Authorship::AUTHOR.'. All rights reserved.',

    'project' => Authorship::PROJECT,

    /*
     * Build fingerprint: the SHA-256 of the canonical identity string, which
     * anyone can recompute without access to this repository. See ATTRIBUTION.md
     * for the string it is taken over and the one-line command to verify it.
     */
    'fingerprint' => Authorship::FINGERPRINT,

];
