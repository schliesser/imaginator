import { beforeAll, describe, expect, it } from 'vitest';

// Importing the module registers the custom element (side effect).
import '../../Resources/Private/TypeScript/backend/aspect-ratios';

function mount(allowed: string, value = ''): HTMLElement {
  const host = document.createElement('imaginator-aspect-ratios');
  host.setAttribute(
    'data-breakpoints',
    JSON.stringify([
      { key: 'xs', minWidth: 0 },
      { key: 'lg', minWidth: 992 },
    ]),
  );
  host.setAttribute('data-allowed', allowed);
  host.setAttribute('data-value', value);

  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'data[tt_content][1][tx_imaginator_aspect_ratios]';
  input.value = value;
  host.appendChild(input);

  document.body.appendChild(host);
  return host;
}

function hidden(host: HTMLElement): HTMLInputElement {
  return host.querySelector('input[type="hidden"]') as HTMLInputElement;
}

function rows(host: HTMLElement): NodeListOf<HTMLElement> {
  return host.querySelectorAll('.imaginator-ar-row');
}

describe('imaginator-aspect-ratios', () => {
  beforeAll(() => {
    document.body.innerHTML = '';
  });

  it('renders one row per breakpoint', () => {
    const host = mount('1:1,16:9');
    expect(rows(host).length).toBe(2);
    host.remove();
  });

  it('offers only the allowed ratios plus an inherit option per row', () => {
    const host = mount('1:1,16:9');
    const select = rows(host)[0].querySelector('select') as HTMLSelectElement;
    const values = Array.from(select.options).map((o) => o.value);
    // inherit ('') + the two allowed ratios
    expect(values).toEqual(['', '1:1', '16:9']);
    host.remove();
  });

  it('serializes the selected ratio into the hidden input', () => {
    const host = mount('1:1,16:9');
    const lgRow = rows(host)[1];
    const select = lgRow.querySelector('select') as HTMLSelectElement;
    select.value = '16:9';
    select.dispatchEvent(new Event('change', { bubbles: true }));

    expect(JSON.parse(hidden(host).value)).toEqual({ lg: '16:9' });
    host.remove();
  });

  it('shows a live swatch reflecting the selected ratio', () => {
    const host = mount('1:1,16:9');
    const lgRow = rows(host)[1];
    const select = lgRow.querySelector('select') as HTMLSelectElement;
    select.value = '16:9';
    select.dispatchEvent(new Event('change', { bubbles: true }));

    const swatch = lgRow.querySelector('.imaginator-ar-swatch') as HTMLElement;
    expect(swatch.style.aspectRatio).toContain('16');
    expect(swatch.style.aspectRatio).toContain('9');
    host.remove();
  });

  it('previews the inherited (next-smaller) ratio on an inherit row', () => {
    // xs sets 1:1; lg is inherit and should preview that bubbled-up 1:1, flagged as inherited.
    const host = mount('1:1,16:9', JSON.stringify({ xs: '1:1' }));
    const lgSwatch = rows(host)[1].querySelector('.imaginator-ar-swatch') as HTMLElement;

    expect(lgSwatch.style.aspectRatio).toBe('1 / 1');
    expect(lgSwatch.classList.contains('imaginator-ar-swatch--inherited')).toBe(true);
    host.remove();
  });

  it('updates inherited previews when a smaller breakpoint changes', () => {
    const host = mount('1:1,16:9'); // both inherit, nothing set
    const xsSelect = rows(host)[0].querySelector('select') as HTMLSelectElement;
    const lgSwatch = rows(host)[1].querySelector('.imaginator-ar-swatch') as HTMLElement;

    // Nothing smaller set yet: lg shows the neutral placeholder.
    expect(lgSwatch.classList.contains('imaginator-ar-swatch--inherit')).toBe(true);

    xsSelect.value = '16:9';
    xsSelect.dispatchEvent(new Event('change', { bubbles: true }));

    // lg now inherits xs's 16:9.
    expect(lgSwatch.style.aspectRatio).toBe('16 / 9');
    expect(lgSwatch.classList.contains('imaginator-ar-swatch--inherited')).toBe(true);
    host.remove();
  });

  it('removes the key when inherit is chosen again', () => {
    const host = mount('1:1,16:9', JSON.stringify({ lg: '16:9' }));
    const lgRow = rows(host)[1];
    const select = lgRow.querySelector('select') as HTMLSelectElement;
    expect(select.value).toBe('16:9');

    select.value = '';
    select.dispatchEvent(new Event('change', { bubbles: true }));

    expect(hidden(host).value).toBe('');
    host.remove();
  });
});
