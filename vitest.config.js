import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        // The exam modules are all DOM-driven — they read inputs, flip classes
        // and write to sessionStorage — so there is nothing meaningful to test
        // without a document.
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        restoreMocks: true,
    },
});
