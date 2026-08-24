/**
 * The page's CSRF token, and the ability to replace it.
 *
 * keep-alive can come back with a token from a rebuilt session (a laptop that
 * slept through the interval), and both the meta tag and every hidden _token
 * input have to adopt it or the final submit dies on a mismatch.
 */
export function createCsrf(doc = document) {
    const meta = doc.querySelector('meta[name="csrf-token"]');

    return {
        token() {
            return meta ? meta.getAttribute('content') : null;
        },

        adopt(token) {
            if (!token || !meta) {
                return;
            }

            meta.setAttribute('content', token);
            doc.querySelectorAll('input[name="_token"]').forEach(input => {
                input.value = token;
            });
        },
    };
}
