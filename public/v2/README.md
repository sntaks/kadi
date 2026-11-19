# Kadi — Play Your Card icon family

Production vector reconstruction of the approved concept, with one shared geometry. Main design uses a gold play triangle centered in a navy spade on a tilted ivory card, plus a restrained gold page-turn corner.

## Palette
- Website/master/standard PWA background: transparent
- Maskable and Apple background `#0E0D0B` (site Night token)
- Border and spade `#0C1028`
- Ivory `#F4F1EA`
- Gold `#F8C038`

## Exports
- `master-2048.png`, `master-2048.svg`: transparent full-res master (SVG viewBox is 1024; PNG export is 2048px).
- `pwa-icons/icon-512.png`, `pwa-icons/icon-192.png`: transparent standard PWA (`purpose: any`).
- `pwa-icons/icon-maskable-512.png`, `pwa-icons/icon-maskable.svg`: geometry scaled to fit 40%-radius mask-safe circle.
- `pwa-icons/apple-touch-icon.png`: opaque square.
- `favicon-16x16.png`, `favicon-32x32.png`, `favicon-48x48.png`: transparent small-size optimizations. Gold page curl omitted; spade enlarged. The 16px variant has a gentler 12° tilt and a larger, straight-sided triangle for readability; 32/48px retain the 18° tilt.
- `favicon.ico`: 16/32/48-pixel ICO.
- `favicon.svg`: transparent scalable favicon; page curl intentionally omitted.
- `pwa-icons/badge-72.png`, `pwa-icons/badge.svg`: white single-color card and spade; transparent surrounding pixels and transparent play-triangle aperture.

## Website use
```html
<link rel="icon" type="image/svg+xml" href="/v2/favicon.svg">
<link rel="icon" sizes="32x32" type="image/png" href="/v2/favicon-32x32.png">
<link rel="icon" sizes="16x16" type="image/png" href="/v2/favicon-16x16.png">
<link rel="shortcut icon" href="/v2/favicon.ico">
<link rel="apple-touch-icon" href="/v2/pwa-icons/apple-touch-icon.png">
```

Use these `icons` in the PWA web manifest:
```json
[
 {"src":"/v2/pwa-icons/icon-192.png","sizes":"192x192","type":"image/png","purpose":"any"},
 {"src":"/v2/pwa-icons/icon-512.png","sizes":"512x512","type":"image/png","purpose":"any"},
 {"src":"/v2/pwa-icons/icon-maskable-512.png","sizes":"512x512","type":"image/png","purpose":"maskable"}
]
```

### Design note
SVG is reconstructed artwork, not an automatic pixel trace of the generated preview. All exports derive from the same four SVG design configurations (master, maskable, favicon and badge) with intentionally documented simplifications.

## Rebuilding and checking

From the repository root, run:

```sh
/usr/bin/python3 tools/assets/build_v2_icons.py
```

Requires system Python with Pillow, PyGObject/Rsvg and pycairo. It rebuilds PNGs, the ICO and `preview.png` from these SVGs; no image-generation service is needed. Exports preserve alpha and use supersampling for small curves. Checks cover sizes, transparency, opaque installed-app backgrounds, the maskable 40%-radius safe circle, and exact ICO frame contents.

`preview.png` shows transparent artwork on light, Night and Felt surfaces, favicons at actual size, and installed-app masks. Backgrounds in this preview are demonstration surfaces, not part of transparent exports.

The existing gold, navy and ivory already match `resources/css/night-table.css`; retain them. Maskable and Apple icons intentionally keep an opaque Night background rather than relying on device-selected transparency handling. Notification badges remain white with a transparent triangle aperture.

This family is active under `/v2/`: the site head, PWA manifest, push notifications, install dialog and offline page use these versioned URLs. Standalone card marks use `master-2048.svg`; structured data uses the 2048px PNG. The horizontal wordmark and distinct shortcut artwork retain their existing files. Legacy icons remain available for cached pages and old notifications. Installed-device icon updates are controlled by the browser/OS and can lag behind the deployment.
