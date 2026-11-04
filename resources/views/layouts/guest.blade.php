{{-- Public pages. The one site shell behind PLATFORM_UI_V2 (`shell` from the page, `page` by default), the old guest layout until then (deleted in Phase 12). --}}
@include(config('platform.ui_v2') ? 'layouts.site' : 'layouts.legacy.guest', ['shell' => $shell ?? 'page'])
