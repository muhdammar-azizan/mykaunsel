<?php

return [
    /*
     * Smooth page changes in the counselor and organization admin portals: the sidebar, header and footer stay in place and only the page fades
     * (a browser "view transition", Chrome and Edge). OFF by default: it adds work for the browser on every click, which a slow computer feels
     * as a slower menu. Switch it on with PORTAL_PAGE_TRANSITION=true once it is known to feel right on the machines that matter.
     * See docs/PORTAL_PAGES.md.
     */
    'page_transition' => (bool) env('PORTAL_PAGE_TRANSITION', false),
];
