// The invitation page: loads the e-mails of the listed members in batches of 10 and offers them as checkboxes
// under each name (the default choice ticked); a type toggle ticks or unticks that type for everyone, as when
// adding payments in h.skauting. The form sends shown[] for the rows that got their checkboxes.
const BATCH = 10;

type Address = { email: string; label: string; source: string; selected: boolean };

export function initMemberAddresses(): void {
    const loader = document.querySelector<HTMLElement>('[data-address-loader]');
    const form = loader?.closest('form');
    if (!loader || !form) {
        return;
    }
    const ids = (loader.dataset.personIds ?? '').split(',').filter((id) => id !== '');
    const label = loader.querySelector<HTMLElement>('[data-progress-label]');
    // Types the leader switched before every row arrived apply to the rows that come later too.
    const switched = new Map<string, boolean>();

    for (const toggle of form.querySelectorAll<HTMLInputElement>('[data-address-type-toggle]')) {
        toggle.addEventListener('change', () => {
            const type = toggle.dataset.addressTypeToggle ?? '';
            switched.set(type, toggle.checked);
            for (const box of form.querySelectorAll<HTMLInputElement>(`input[data-address-type="${type}"]`)) {
                box.checked = toggle.checked;
            }
        });
    }

    const render = (id: string, addresses: Address[]): void => {
        const cell = form.querySelector<HTMLElement>(`[data-addresses="${id}"]`);
        if (!cell) {
            return;
        }
        cell.replaceChildren();
        const shown = document.createElement('input');
        shown.type = 'hidden';
        shown.name = 'shown[]';
        shown.value = id;
        cell.append(shown);
        if (addresses.length === 0) {
            const none = document.createElement('span');
            none.className = 'text-danger';
            none.textContent = 'bez e-mailu ve skautISu';
            cell.append(none);
            return;
        }
        addresses.forEach((address, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'form-check';
            const box = document.createElement('input');
            box.type = 'checkbox';
            box.className = 'form-check-input';
            box.id = `address-${id}-${index}`;
            box.name = `emails[${id}][]`;
            box.value = address.email;
            box.dataset.addressType = address.source;
            box.checked = switched.get(address.source) ?? address.selected;
            const text = document.createElement('label');
            text.className = 'form-check-label';
            text.htmlFor = box.id;
            text.textContent = `${address.email} (${address.label})`;
            wrapper.append(box, text);
            cell.append(wrapper);
        });
    };

    const run = async (): Promise<void> => {
        for (let start = 0; start < ids.length; start += BATCH) {
            const batch = ids.slice(start, start + BATCH);
            const target = new URL(loader.dataset.addressLoader ?? '', window.location.href);
            target.searchParams.set('ids', batch.join(','));
            const response = await fetch(target, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                if (label) {
                    label.textContent = 'E-maily se nepodařilo načíst. Přidaní členové dostanou výchozí adresy, upravit je půjde v seznamu přihlášek.';
                }
                return;
            }
            const data = (await response.json()) as { persons: Record<string, Address[]> };
            for (const id of batch) {
                render(id, data.persons[id] ?? []);
            }
            if (label) {
                label.textContent = `Načítám e-maily ze skautISu… ${Math.min(start + BATCH, ids.length)} z ${ids.length}`;
            }
        }
        loader.hidden = true;
        loader.dataset.done = '1';
    };

    void run();
}
