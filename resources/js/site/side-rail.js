// The side menu's three sizes (layouts/site, components/site/side-menu):
//   phones (< 768px)        hidden; Menu opens the full drawer
//   tablets (768–1239px)    an icon rail; Menu or a rail group opens the full drawer over the page
//   desktop (≥ 1240px)      full, and the player can collapse it to the rail (remembered in this browser)
// body.nt-railed switches the rail styles (resources/css/site-shell.css). An inline script in the layout sets it
// before the first paint with the same rule, so the page doesn't jump while Alpine starts.
const KEY = 'kadi.side.rail';
export const RAIL_MIN = 768;
export const FULL_MIN = 1240;

export function isRailed(width, collapsed) {
    return width >= RAIL_MIN && (width < FULL_MIN || collapsed);
}

function readCollapsed() {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
}

function writeCollapsed(collapsed) {
    try {
        if (collapsed) localStorage.setItem(KEY, '1');
        else localStorage.removeItem(KEY);
    } catch {
        // Private mode or blocked storage: the choice lasts until the page reloads.
    }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('siteShell', () => ({
        menuOpen: false,
        collapsed: readCollapsed(),
        width: window.innerWidth,

        get railed() {
            return isRailed(this.width, this.collapsed);
        },

        init() {
            this.apply();
            this.$watch('railed', () => this.apply());
        },

        apply() {
            document.body.classList.toggle('nt-railed', this.railed);
        },

        resized() {
            this.width = window.innerWidth;
            // Desktop has no drawer.
            if (this.width >= FULL_MIN) this.menuOpen = false;
        },

        toggleRail() {
            this.collapsed = !this.collapsed;
            writeCollapsed(this.collapsed);
        },

        // A rail group was tapped: show the full menu (desktop un-collapses, a tablet opens the drawer).
        expand() {
            if (this.width >= FULL_MIN) {
                this.collapsed = false;
                writeCollapsed(false);
            } else {
                this.menuOpen = true;
            }
        },
    }));
});
