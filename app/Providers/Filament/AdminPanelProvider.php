<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->brandLogo(fn () => new HtmlString(
                '<div style="display:flex;flex-direction:column;align-items:center;height:100%">'
                .'<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#F59E0B" stroke-width="0.7" stroke-linecap="round" stroke-linejoin="round" style="height:65%;width:auto">'
                .'<rect x="9.9" y="1.4" width="1.4" height="3" rx="0.7" />'
                .'<rect x="12.7" y="1.4" width="1.4" height="3" rx="0.7" />'
                .'<ellipse cx="12" cy="5.6" rx="3.8" ry="1.2" />'
                .'<path d="M8.2 5.6 V9.6 M15.8 5.6 V9.6" />'
                .'<ellipse cx="12" cy="9.6" rx="6.3" ry="1.5" />'
                .'<path d="M5.7 9.6 V14.6 M18.3 9.6 V14.6" />'
                .'<ellipse cx="12" cy="14.6" rx="9.3" ry="1.7" />'
                .'<path d="M2.7 14.6 V20.3 Q12 23.7 21.3 20.3 V14.6" />'
                .'</svg>'
                .'<span style="font-family:\'Dancing Script\',cursive;font-weight:700;font-size:1.9rem;line-height:1;color:#F59E0B;transform:skewX(-10deg);margin-top:0.2rem">Wishlisti</span>'
                .'</div>',
            ))
            ->brandLogoHeight('5.5rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString(
                    '<link rel="preconnect" href="https://fonts.googleapis.com">'
                    .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                    .'<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&display=swap" rel="stylesheet">'
                    .'<style>'
                    .'.fi-sidebar-header-logo-ctn .fi-logo, .fi-topbar .fi-logo { height: 2.5rem !important; }'
                    .'.fi-sidebar-header-logo-ctn .fi-logo span, .fi-topbar .fi-logo span { font-size: 1.05rem !important; margin-top: 0 !important; }'
                    // Keeps the signature footer (BODY_END) at the bottom of
                    // the screen on short pages, instead of right under the
                    // content: the page grows to fill the screen, the footer
                    // takes what's left.
                    .'.fi-body { display: flex; flex-direction: column; }'
                    .'.fi-body > .fi-layout, .fi-body > .fi-simple-layout { flex: 1 0 auto; min-height: 0; height: auto; }'
                    .'.fi-body > .wishlisti-signature { flex-shrink: 0; }'
                    .'</style>',
                ),
            )
            // At the end of the body (not the content area's footer), so it's
            // centered on the whole screen rather than next to the sidebar.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => new HtmlString(
                    '<footer class="wishlisti-signature" style="display:flex;flex-wrap:wrap;text-align:center;align-items:center;justify-content:center;gap:0.375rem;padding:1.5rem 1rem;font-size:0.8rem;color:rgb(113 113 122)">'
                    .'<span>© '.now()->year.' Wishlisti | Desenvolvido por Julio Cesar Aquino</span>'
                    .'<a href="https://instagram.com/juliucaezer" target="_blank" rel="noopener noreferrer" aria-label="Instagram de Julio Cesar Aquino" style="display:inline-flex;color:inherit">'
                    .'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                    .'<rect width="20" height="20" x="2" y="2" rx="5" ry="5" />'
                    .'<path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />'
                    .'<line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />'
                    .'</svg>'
                    .'</a>'
                    .'</footer>',
                ),
            );
    }
}
