import { Tooltip } from 'bootstrap';

// On medium screens the navigation shows only the unit number of the role; the full name is then a tooltip.
// While the name is visible, the tooltip would only repeat it, so it stays closed.
export function initRoleTooltip(): void {
    const item = document.querySelector<HTMLElement>('[data-role-tooltip]');
    const name = document.querySelector<HTMLElement>('[data-role-name]');
    if (!item || !name) {
        return;
    }

    const tooltip = new Tooltip(item, { placement: 'bottom' });
    item.addEventListener('show.bs.tooltip', (event) => {
        if (name.offsetParent !== null) {
            event.preventDefault();
        }
    });
    // Opening the role dialog must not leave the tooltip hanging over it.
    item.addEventListener('click', () => tooltip.hide());
}
