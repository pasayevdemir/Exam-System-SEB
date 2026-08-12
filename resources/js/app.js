import './bootstrap';

// Bootstrap's own JS bundle (dropdowns, modals, alert dismiss via
// data-bs-dismiss, etc.) — auto-init relies on `bootstrap` being global,
// same as when it was loaded from the CDN <script> tag.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
