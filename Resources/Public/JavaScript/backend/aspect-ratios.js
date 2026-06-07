/**
 * FormEngine web component for per-breakpoint aspect ratios at the content-element level. Renders
 * one row per configured breakpoint with an allowed-ratio chooser (plus an "inherit" option that
 * lets the next-smaller breakpoint's ratio bubble up) and a live swatch. The selected ratios are
 * serialized as a `{ "<breakpoint>": "<ratio>" }` JSON object into the hidden input the PHP node
 * emitted; an empty object writes an empty value so nothing is stored.
 */
class AspectRatiosElement extends HTMLElement {
    input;
    fieldId = '';
    value = {};
    breakpoints = [];
    swatches = new Map();
    connectedCallback() {
        const existing = this.querySelector('input[type="hidden"]');
        if (existing === null) {
            return;
        }
        this.input = existing;
        this.fieldId = this.getAttribute('data-field') ?? existing.id;
        this.value = this.parseValue(this.getAttribute('data-value'));
        this.breakpoints = this.parseBreakpoints(this.getAttribute('data-breakpoints'));
        const allowed = (this.getAttribute('data-allowed') ?? '')
            .split(',')
            .map((ratio) => ratio.trim())
            .filter((ratio) => ratio !== '');
        const container = document.createElement('div');
        container.className = 'imaginator-ar-rows';
        this.breakpoints.forEach((breakpoint) => container.appendChild(this.buildRow(breakpoint, allowed)));
        this.appendChild(container);
        this.refreshSwatches();
    }
    buildRow(breakpoint, allowed) {
        const row = document.createElement('div');
        row.className = 'imaginator-ar-row';
        row.dataset.breakpoint = breakpoint.key;
        const label = document.createElement('label');
        label.className = 'imaginator-ar-label form-label';
        label.textContent = breakpoint.key.toUpperCase();
        const select = document.createElement('select');
        select.className = 'imaginator-ar-select form-select form-select-sm';
        select.appendChild(this.option('', 'inherit'));
        allowed.forEach((ratio) => select.appendChild(this.option(ratio, ratio)));
        select.value = this.value[breakpoint.key] ?? '';
        label.htmlFor = select.id = `${this.fieldId}-${breakpoint.key}`;
        const swatchCell = document.createElement('span');
        swatchCell.className = 'imaginator-ar-swatch-cell';
        const swatch = document.createElement('span');
        swatch.className = 'imaginator-ar-swatch';
        swatchCell.appendChild(swatch);
        this.swatches.set(breakpoint.key, swatch);
        select.addEventListener('change', () => {
            const ratio = select.value;
            if (ratio === '') {
                delete this.value[breakpoint.key];
            }
            else {
                this.value[breakpoint.key] = ratio;
            }
            // Changing one breakpoint changes what the larger "inherit" rows preview.
            this.refreshSwatches();
            this.serialize();
        });
        row.appendChild(label);
        row.appendChild(select);
        row.appendChild(swatchCell);
        return row;
    }
    /**
     * Recompute every swatch. Breakpoints are ordered smallest-first; an unset ("inherit") breakpoint
     * shows the nearest-smaller set ratio (the one that bubbles up at render time), styled as an
     * inherited preview. With nothing smaller set, it falls back to a neutral hatched placeholder.
     */
    refreshSwatches() {
        let inherited = null;
        this.breakpoints.forEach((breakpoint) => {
            const swatch = this.swatches.get(breakpoint.key);
            if (swatch === undefined) {
                return;
            }
            const own = this.value[breakpoint.key] ?? '';
            if (own !== '') {
                inherited = own;
                this.applySwatch(swatch, own, false);
            }
            else {
                this.applySwatch(swatch, inherited, true);
            }
        });
    }
    applySwatch(swatch, ratio, isInherited) {
        swatch.classList.remove('imaginator-ar-swatch--inherit', 'imaginator-ar-swatch--inherited');
        if (ratio === null || ratio === '') {
            swatch.style.removeProperty('aspect-ratio');
            swatch.classList.add('imaginator-ar-swatch--inherit');
            return;
        }
        swatch.style.aspectRatio = ratio.replace(':', ' / ');
        if (isInherited) {
            swatch.classList.add('imaginator-ar-swatch--inherited');
        }
    }
    serialize() {
        this.input.value = Object.keys(this.value).length === 0 ? '' : JSON.stringify(this.value);
        // Mark the field dirty so FormEngine's unsaved-changes guard warns on close. FormEngine detects
        // changes via the `has-change` class on `[name^="data["]` fields (see markFieldAsChanged).
        this.input.classList.add('has-change');
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    option(value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }
    parseBreakpoints(raw) {
        if (raw === null || raw === '') {
            return [];
        }
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        }
        catch {
            return [];
        }
    }
    parseValue(raw) {
        if (raw === null || raw === '') {
            return {};
        }
        try {
            const parsed = JSON.parse(raw);
            return parsed !== null && typeof parsed === 'object' ? parsed : {};
        }
        catch {
            return {};
        }
    }
}
if (customElements.get('imaginator-aspect-ratios') === undefined) {
    customElements.define('imaginator-aspect-ratios', AspectRatiosElement);
}
export default AspectRatiosElement;
