/*!
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @license   Proprietary. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Stamped onto every compiled bundle. The per-file headers in resources/js are
// lost the moment the bundle is minified, and a deployed server ships only the
// bundle — so without this, the JavaScript actually served by production would
// carry no authorship at all. Written as `/*! */` because that is the form
// esbuild classifies as a legal comment and preserves through minification.
const banner = `/*!
 * Peerstack Exam System
 *
 * Copyright (c) 2026 Damir Pashayev. All rights reserved.
 * Author: Damir Pashayev <pashayevdamir@gmail.com>
 * https://github.com/pasayevdemir
 *
 * Proprietary. Not open source. This bundle is part of a work whose copyright
 * is held by the author named above; removing this notice does not transfer it.
 * Authorship fingerprint (SHA-256):
 * a7e9122a4821f9871b6101467a9475750e073318f0f77225da9b007b2a690d29
 */`;

// Rollup's `output.banner` covers JavaScript chunks only, so CSS emitted by the
// build would otherwise ship unattributed. This stamps the stylesheets too.
//
// The wording is narrower than the JS banner on purpose: some of this CSS is
// vendored (KaTeX, Bootstrap, FontAwesome) and belongs to its own authors,
// whose notices esbuild preserves further down the same file. Claiming the
// stylesheet outright would be false, so the notice claims only what is
// actually held — the compilation — and says so.
const stampCss = () => ({
    name: 'peerstack-css-attribution',
    generateBundle(_options, bundle) {
        const notice =
            '/*! Part of the Peerstack Exam System. Compilation copyright (c) 2026 ' +
            'Damir Pashayev <pashayevdamir@gmail.com>, https://github.com/pasayevdemir — ' +
            'all rights reserved. Third-party components remain the property of their ' +
            'respective authors; their notices are preserved below. */\n';

        for (const asset of Object.values(bundle)) {
            if (asset.type !== 'asset' || !asset.fileName.endsWith('.css')) continue;
            if (String(asset.source).includes('Pashayev')) continue;
            asset.source = notice + asset.source;
        }
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        stampCss(),
    ],
    build: {
        rollupOptions: {
            output: {
                banner,
            },
        },
    },
    // Keep `/*! */` comments rather than stripping every comment, so the banner
    // above and the per-file headers survive into the minified output.
    esbuild: {
        legalComments: 'inline',
    },
});
