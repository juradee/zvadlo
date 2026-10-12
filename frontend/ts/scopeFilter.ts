// The event scope select offers only scopes not below the chosen organising unit (data-scopes-by-unit from
// the event form) and switches to the unit's own level when the unit changes; the server checks the same rule.
export function initScopeFilter(): void {
    for (const scope of document.querySelectorAll<HTMLSelectElement>('select[data-scopes-by-unit]')) {
        const allowed = JSON.parse(scope.dataset.scopesByUnit ?? '{}') as Record<string, string[]>;
        const defaults = JSON.parse(scope.dataset.defaultScopeByUnit ?? '{}') as Record<string, string | null>;
        const unit = scope.form?.querySelector<HTMLSelectElement>('select[name="unitId"]');
        if (!unit) {
            continue;
        }
        const apply = (): void => {
            const permitted = allowed[unit.value] ?? null;
            for (const option of scope.options) {
                if (option.value === '') {
                    continue;
                }
                const ok = permitted === null || permitted.includes(option.value);
                option.hidden = !ok;
                option.disabled = !ok;
            }
            if (scope.selectedOptions[0]?.disabled) {
                scope.value = '';
            }
        };
        unit.addEventListener('change', () => {
            apply();
            scope.value = defaults[unit.value] ?? '';
        });
        apply();
    }
}
