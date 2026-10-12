// Fills the hidden field of the bug report form with what the browser knows about itself (as h.skauting does).
export function initBugReportDiagnostics(): void {
    for (const field of document.querySelectorAll<HTMLInputElement>('[data-bug-report-diagnostics]')) {
        const nav = navigator as Navigator & { deviceMemory?: number; connection?: { effectiveType?: string; downlink?: number; rtt?: number; saveData?: boolean } };
        field.value = JSON.stringify({
            capturedAt: new Date().toISOString(),
            page: { url: window.location.href, referrer: document.referrer || null, title: document.title },
            navigator: {
                userAgent: navigator.userAgent,
                language: navigator.language,
                languages: navigator.languages,
                platform: navigator.platform,
                cookieEnabled: navigator.cookieEnabled,
                online: navigator.onLine,
                hardwareConcurrency: navigator.hardwareConcurrency,
                maxTouchPoints: navigator.maxTouchPoints,
                deviceMemory: nav.deviceMemory ?? null,
            },
            screen: { width: window.screen.width, height: window.screen.height, colorDepth: window.screen.colorDepth, orientation: window.screen.orientation?.type ?? null },
            viewport: { innerWidth: window.innerWidth, innerHeight: window.innerHeight, devicePixelRatio: window.devicePixelRatio },
            locale: { timezone: Intl.DateTimeFormat().resolvedOptions().timeZone, timezoneOffsetMinutes: new Date().getTimezoneOffset() },
            connection: nav.connection ? { effectiveType: nav.connection.effectiveType ?? null, downlink: nav.connection.downlink ?? null, rtt: nav.connection.rtt ?? null, saveData: nav.connection.saveData ?? null } : null,
        });
    }
}
