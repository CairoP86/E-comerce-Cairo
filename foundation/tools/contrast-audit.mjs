// Contrast audit over what the browser actually renders, not over the tokens.
//
// The token guards (tests/Feature/AdminLightThemeTest.php and StorefrontContrastTest.php) keep the
// palette honest; this checks what survives after every rule has been applied: each visible text
// node against the real background painted behind it.
//
// Usage: npm run audit:contrast -- --base=http://127.0.0.1:8092 [--email=... --password=...]
// Playwright ships as a dev dependency; the browser binary is downloaded separately, once:
//   npx playwright install chromium
//
// Thresholds follow WCAG 2.1 AA: 4.5 for normal text, 3.0 for large text (>=24px, or >=18.66px bold).
import { chromium } from 'playwright';

const args = Object.fromEntries(process.argv.slice(2).map(a => a.replace(/^--/, '').split('=')));
const base = args.base ?? 'http://127.0.0.1:8092';
const publicPages = [['home', '/'], ['catálogo', '/catalog'], ['carrito', '/cart'], ['login', '/login'], ['registro', '/register']];
const privatePages = [['panel', '/admin'], ['productos', '/admin/catalog/products'], ['pedidos', '/admin/orders'], ['categorías', '/admin/catalog/categories'],
    ['marcas', '/admin/catalog/brands'], ['proveedores', '/admin/commercial/suppliers'], ['reglas', '/admin/commercial/rules'], ['auditoría', '/admin/audit'], ['mi cuenta', '/account']];

const audit = () => {
    const luminance = channels => {
        const [r, g, b] = channels.map(value => { value /= 255; return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4; });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const parse = value => (value.match(/[\d.]+/g) || []).map(Number);
    const ratio = (front, back) => { const [high, low] = [luminance(front), luminance(back)].sort((a, b) => b - a); return (high + 0.05) / (low + 0.05); };
    // The first ancestor that actually paints something is the background the text sits on.
    const backgroundOf = element => {
        for (let node = element; node; node = node.parentElement) {
            const colour = parse(getComputedStyle(node).backgroundColor);
            if (colour.length >= 3 && (colour[3] === undefined || colour[3] > 0.95)) return colour.slice(0, 3);
        }
        return [255, 255, 255];
    };
    const failures = [];
    for (const element of document.querySelectorAll('body *')) {
        if (!element.childNodes.length || element.offsetParent === null) continue;
        const text = [...element.childNodes].filter(node => node.nodeType === 3).map(node => node.textContent.trim()).join(' ').trim();
        if (!text) continue;
        const style = getComputedStyle(element);
        if (style.visibility === 'hidden' || Number(style.opacity) < 0.6) continue;
        // Decorative lettering inside art marked aria-hidden is exempt (WCAG 1.4.3, incidental text).
        // It is an exemption for decoration only: anything that carries meaning must not be hidden.
        if (element.closest('[aria-hidden="true"]')) continue;
        const size = parseFloat(style.fontSize);
        const large = size >= 24 || (Number(style.fontWeight) >= 700 && size >= 18.66);
        const value = ratio(parse(style.color).slice(0, 3), backgroundOf(element));
        if (value < (large ? 3 : 4.5)) failures.push({ text: text.slice(0, 48), ratio: Number(value.toFixed(2)), needs: large ? 3 : 4.5, selector: (element.className || element.tagName).toString().slice(0, 40) });
    }
    return failures;
};

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
let total = 0;

const check = async (label, path, width) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(200);
    const failures = await page.evaluate(audit);
    total += failures.length;
    console.log(`${String(width).padEnd(5)} ${label.padEnd(14)} ${failures.length ? 'FALLA ' + JSON.stringify(failures) : 'ok'}`);
};

for (const width of [1440, 1280, 390]) {
    for (const [label, path] of publicPages) await check(label, path, width);
}

if (args.email && args.password) {
    await page.goto(base + '/login', { waitUntil: 'networkidle' });
    await page.fill('input[type=email]', args.email);
    await page.fill('input[type=password]', args.password);
    await Promise.all([page.waitForURL('**/account'), page.click('form button.button.full')]);
    for (const width of [1440, 1280, 390]) {
        for (const [label, path] of privatePages) await check(label, path, width);
    }
} else {
    console.log('Sin --email y --password: se revisaron solo las páginas públicas.');
}

console.log(total ? `\n${total} combinaciones por debajo del mínimo.` : '\nSin fallos de contraste.');
await browser.close();
process.exit(total ? 1 : 0);
