<?php

/**
 * Authorship and provenance metadata for the Peerstack Exam System.
 *
 * Read by the application layout so attribution is rendered from a single source
 * rather than hard-coded into each view.
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 */

return [

    'author' => 'Damir Pashayev',

    'email' => 'pashayevdamir@gmail.com',

    'github' => 'https://github.com/pasayevdemir',

    'copyright' => '© 2026 Damir Pashayev. All rights reserved.',

    'project' => 'Peerstack Exam System',

    /*
     * Build fingerprint. Derived from the author identity, so it is stable
     * across builds and recomputable by the author to demonstrate provenance.
     */
    'fingerprint' => substr(hash('sha256', 'Damir Pashayev <pashayevdamir@gmail.com>'), 0, 16),

];
