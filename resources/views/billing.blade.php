<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Billing — New Order</title>
    <style>
        :root {
            --bg: #f3f5f8;
            --surface: #ffffff;
            --text: #1c2430;
            --muted: #566174;
            --border: #d3d9e2;
            --primary: #1d4ed8;
            --primary-dark: #1e3a8a;
            --danger: #b42318;
            --danger-bg: #fef3f2;
            --danger-border: #fda29b;
            --warn: #8a4b08;
            --warn-bg: #fffaeb;
            --warn-border: #f9d58a;
            --ok: #05603a;
            --ok-bg: #ecfdf3;
            --info-bg: #eff4ff;
            --radius: 10px;
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        [hidden] { display: none !important; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.45;
        }

        h1, h2 { margin: 0; line-height: 1.25; }
        code { font-family: ui-monospace, Consolas, "Courier New", monospace; font-size: 0.9em; }

        .site-header { background: var(--primary-dark); color: #ffffff; padding: 1rem 1.25rem; }
        .site-header h1 { font-size: 1.3rem; }

        .layout {
            max-width: 76rem;
            margin: 0 auto;
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-start;
        }
        .main-col { flex: 3 1 40rem; min-width: 0; display: grid; gap: 1rem; }
        .side-col { flex: 1 1 18rem; min-width: 0; display: grid; gap: 1rem; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
        }
        .card h2 { font-size: 1.05rem; margin-bottom: 0.75rem; }
        #order-form { display: grid; gap: 1rem; }

        .muted { color: var(--muted); }
        .hint { display: block; min-height: 1.2em; margin-top: 0.2rem; font-size: 0.8rem; color: var(--warn); }
        .note { font-size: 0.9rem; color: var(--muted); }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr));
            gap: 0.75rem 1rem;
        }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }

        input, select, button { font: inherit; }
        input[type="text"], input[type="email"], input[type="number"], select {
            width: 100%;
            padding: 0.55rem 0.65rem;
            border: 1px solid #8b96a8;
            border-radius: 6px;
            background: #ffffff;
            color: var(--text);
        }
        input[readonly] { background: #eceff4; color: var(--muted); }
        input[aria-invalid="true"] { border-color: var(--danger); }
        input:focus-visible, select:focus-visible, button:focus-visible, [tabindex="-1"]:focus-visible {
            outline: 3px solid #7aa2ff;
            outline-offset: 2px;
        }

        #customer-status { display: block; min-height: 1.2em; margin-top: 0.2rem; font-size: 0.85rem; }
        #customer-status[data-kind="ok"] { color: var(--ok); }
        #customer-status[data-kind="info"] { color: var(--primary); }
        #customer-status[data-kind="warn"] { color: var(--warn); }

        button {
            padding: 0.55rem 1rem;
            border: 1px solid #8b96a8;
            border-radius: 6px;
            background: #ffffff;
            color: var(--text);
            cursor: pointer;
        }
        button:hover:not(:disabled) { background: #eceff4; }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        button.primary { background: var(--primary); border-color: var(--primary); color: #ffffff; font-weight: 600; }
        button.primary:hover:not(:disabled) { background: var(--primary-dark); }
        button.icon-button { padding: 0.45rem 0.65rem; line-height: 1; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 0.4rem 0.5rem;
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 2px solid var(--border);
        }
        td { padding: 0.5rem; vertical-align: top; border-bottom: 1px solid var(--border); }
        .lines-table { min-width: 40rem; }
        .lines-table .product-select { min-width: 15rem; }
        .lines-table .qty { width: 6rem; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .table-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-top: 0.75rem; }
        #products-status { margin: 0; font-size: 0.9rem; color: var(--muted); }
        #lines-empty { margin: 0.75rem 0 0; color: var(--muted); }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
            gap: 1rem 2rem;
        }
        .totals { display: grid; grid-template-columns: 1fr auto; gap: 0.35rem 1rem; margin: 0; }
        .totals dt { color: var(--muted); }
        .totals dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
        .totals .strong { font-weight: 700; font-size: 1.1rem; color: var(--text); }
        .tag {
            display: inline-block;
            padding: 0 0.4rem;
            border-radius: 999px;
            background: var(--info-bg);
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 600;
            vertical-align: middle;
        }
        .cash { display: grid; gap: 0.5rem; align-content: start; }
        .denoms { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 0.4rem; }
        .denoms:empty { display: none; }
        .denoms li {
            padding: 0.1rem 0.65rem;
            border: 1px solid #b8c7f5;
            border-radius: 999px;
            background: var(--info-bg);
            font-size: 0.85rem;
        }

        .messages { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 8px; }
        .messages:empty { display: none; }
        .messages[data-kind="error"] {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger);
        }
        .messages ul { margin: 0.4rem 0 0; padding-left: 1.2rem; }
        .submit-row { margin-top: 1rem; }

        .bill { border: 2px solid var(--ok); }
        .bill:focus { outline: none; }
        .bill h2 { color: var(--ok); }
        .bill .totals { margin-top: 0.75rem; max-width: 24rem; margin-left: auto; }
        .bill .denoms { margin-top: 0.5rem; justify-content: flex-end; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }

        .side-col h2 { color: var(--warn); }
        .stock-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; }
        .stock-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--warn-border);
            border-radius: 8px;
            background: var(--warn-bg);
        }
        .stock-list li[data-level="out"] { border-color: var(--danger-border); background: var(--danger-bg); }
        .stock-list li[data-level="out"] .badge { color: var(--danger); }
        .badge { font-weight: 700; white-space: nowrap; color: var(--warn); }
        #low-stock-status { margin: 0 0 0.5rem; font-size: 0.9rem; color: var(--muted); }
        #low-stock-status:empty { display: none; }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
    <style media="print">
        body { background: #ffffff; }
        .no-print, .site-header, .side-col, #order-form { display: none !important; }
        .layout { display: block; padding: 0; }
        .bill { border: none; padding: 0; }
    </style>
</head>
<body>
    <header class="site-header">
        <h1>Store Billing — New Order</h1>
    </header>

    <noscript>
        <p class="card">This billing screen needs JavaScript to build an order.</p>
    </noscript>

    {{-- Limits come from config/inventory.php so the UI never drifts from server validation. --}}
    <div class="layout" id="app"
         data-max-quantity="{{ (int) config('inventory.max_quantity_per_line', 1000) }}"
         data-max-lines="{{ (int) config('inventory.max_lines_per_order', 50) }}">

        <main class="main-col">
            <section id="bill" class="card bill" aria-labelledby="bill-heading" tabindex="-1" hidden></section>

            <form id="order-form" novalidate>
                <section class="card" aria-labelledby="customer-heading">
                    <h2 id="customer-heading">Customer</h2>
                    <div class="field-grid">
                        <div>
                            <label for="customer-email">Email</label>
                            <input id="customer-email" type="email" autocomplete="off" inputmode="email"
                                   placeholder="customer@example.com" aria-describedby="customer-status">
                            <span id="customer-status" role="status" aria-live="polite"></span>
                        </div>
                        <div>
                            <label for="customer-name">Name</label>
                            <input id="customer-name" type="text" autocomplete="off" maxlength="255"
                                   placeholder="Required for a new customer">
                        </div>
                    </div>
                </section>

                <section class="card" aria-labelledby="products-heading">
                    <h2 id="products-heading">Products</h2>
                    <div class="table-wrap">
                        <table class="lines-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Qty</th>
                                    <th scope="col" class="num">Price</th>
                                    <th scope="col" class="num">Line total</th>
                                    <th scope="col"><span class="sr-only">Remove line</span></th>
                                </tr>
                            </thead>
                            <tbody id="lines"></tbody>
                        </table>
                    </div>
                    <p id="lines-empty" hidden>No products added yet.</p>
                    <div class="table-actions">
                        <button type="button" id="add-line">+ Add Product</button>
                        <p id="products-status" role="status" aria-live="polite"></p>
                        <button type="button" id="retry-products" hidden>Retry</button>
                    </div>
                </section>

                <section class="card" aria-labelledby="payment-heading">
                    <h2 id="payment-heading">Payment</h2>
                    <p class="note">Totals on this screen are an estimate. The final bill is calculated by the server.</p>
                    <div class="payment-grid">
                        <dl class="totals" aria-label="Estimated totals">
                            <dt>Subtotal <span class="tag">estimate</span></dt>
                            <dd id="est-subtotal">₹0.00</dd>
                            <dt>Tax <span class="tag">estimate</span></dt>
                            <dd id="est-tax">₹0.00</dd>
                            <dt class="strong">Grand total <span class="tag">estimate</span></dt>
                            <dd id="est-grand" class="strong">₹0.00</dd>
                        </dl>
                        <div class="cash">
                            <div>
                                <label for="amount-given">Amount given by customer (₹)</label>
                                <input id="amount-given" type="text" inputmode="decimal" autocomplete="off"
                                       placeholder="e.g. 500" aria-describedby="amount-hint">
                                <span id="amount-hint" class="hint" aria-live="polite"></span>
                            </div>
                            <dl class="totals">
                                <dt id="balance-label">Balance to return</dt>
                                <dd id="balance-value" class="strong">—</dd>
                            </dl>
                            <ul id="denominations" class="denoms" aria-label="Change breakdown"></ul>
                        </div>
                    </div>

                    <div id="messages" class="messages" role="alert"></div>

                    <div class="submit-row">
                        <button type="submit" id="generate-bill" class="primary">Generate Bill</button>
                    </div>
                </section>
            </form>
        </main>

        <aside class="side-col" aria-labelledby="low-stock-heading">
            <section class="card">
                <h2 id="low-stock-heading">⚠ Low Stock Alert</h2>
                <p id="low-stock-status" role="status" aria-live="polite">Loading…</p>
                <ul id="low-stock-list" class="stock-list"></ul>
            </section>
        </aside>
    </div>

@verbatim
    <script>
    (function () {
        'use strict';

        // ---------------------------------------------------------------
        // Constants and page references
        // ---------------------------------------------------------------
        const API_BASE = '/api';
        const RUPEE = '₹';
        const DENOMINATIONS = [500, 200, 100, 50, 20, 10, 5, 2, 1];
        const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const LOOKUP_DELAY_MS = 500;
        const PRODUCTS_PER_PAGE = 100;
        const MAX_PRODUCT_PAGES = 20;

        const app = document.getElementById('app');
        const config = {
            maxQuantity: Number(app.dataset.maxQuantity) || 1000,
            maxLines: Number(app.dataset.maxLines) || 50,
        };

        const els = {
            form: document.getElementById('order-form'),
            email: document.getElementById('customer-email'),
            customerStatus: document.getElementById('customer-status'),
            name: document.getElementById('customer-name'),
            lines: document.getElementById('lines'),
            linesEmpty: document.getElementById('lines-empty'),
            addLine: document.getElementById('add-line'),
            productsStatus: document.getElementById('products-status'),
            retryProducts: document.getElementById('retry-products'),
            estSubtotal: document.getElementById('est-subtotal'),
            estTax: document.getElementById('est-tax'),
            estGrand: document.getElementById('est-grand'),
            amount: document.getElementById('amount-given'),
            amountHint: document.getElementById('amount-hint'),
            balanceLabel: document.getElementById('balance-label'),
            balanceValue: document.getElementById('balance-value'),
            denominations: document.getElementById('denominations'),
            messages: document.getElementById('messages'),
            submit: document.getElementById('generate-bill'),
            bill: document.getElementById('bill'),
            lowStockHeading: document.getElementById('low-stock-heading'),
            lowStockStatus: document.getElementById('low-stock-status'),
            lowStockList: document.getElementById('low-stock-list'),
        };

        // ---------------------------------------------------------------
        // State
        // ---------------------------------------------------------------
        const state = {
            products: [],
            productsById: new Map(),
            lines: [],            // { key, productId, row, select, qty, hint, price, total, remove }
            lineSeq: 0,
            submitting: false,
            productsLoadSeq: 0,
            lowStockLoadSeq: 0,
        };

        // What we know about the typed email. `seq` invalidates lookups that are still in flight.
        const customer = {
            email: '',
            status: 'idle',       // idle | checking | existing | new | unknown
            autoFilled: false,    // the name box currently holds a looked-up name
            seq: 0,
            timer: null,
            pending: null,
        };

        // ---------------------------------------------------------------
        // Small helpers
        // ---------------------------------------------------------------

        // Builds an element; string children become text nodes, so data is never parsed as HTML.
        function h(tag, attrs, ...children) {
            const node = document.createElement(tag);
            Object.entries(attrs || {}).forEach(([name, value]) => {
                if (value === undefined || value === null || value === false) {
                    return;
                }
                if (name === 'className') {
                    node.className = value;
                } else {
                    node.setAttribute(name, value === true ? '' : String(value));
                }
            });
            children.flat().forEach((child) => {
                if (child === undefined || child === null || child === false) {
                    return;
                }
                node.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
            });
            return node;
        }

        async function api(path, { method = 'GET', body } = {}) {
            const headers = { Accept: 'application/json' };
            if (body !== undefined) {
                headers['Content-Type'] = 'application/json';
            }
            const response = await fetch(API_BASE + path, { method, headers, body });
            let json = null;
            try {
                json = await response.json();
            } catch (parseError) {
                json = null;
            }
            return { status: response.status, ok: response.ok, body: json };
        }

        function normalizeEmail(value) {
            return value.trim().toLowerCase();
        }

        // Money is handled in integer paise, exactly like the server.
        function toMinor(value) {
            const number = Number(value);
            return Number.isFinite(number) ? Math.round(number * 100) : 0;
        }

        function formatMinor(minor) {
            const sign = minor < 0 ? '-' : '';
            const abs = Math.abs(minor);
            return sign + RUPEE + Math.floor(abs / 100) + '.' + String(abs % 100).padStart(2, '0');
        }

        // Server money strings ("150.00") shown with the rupee sign.
        function money(serverValue) {
            return formatMinor(toMinor(serverValue));
        }

        function formatPercent(value) {
            const number = Number(value);
            return String(Number.isFinite(number) ? number : 0);
        }

        function formatDate(iso) {
            const date = new Date(iso);
            return Number.isNaN(date.getTime()) ? String(iso || '') : date.toLocaleString();
        }

        // Same rule as OrderCalculator: half-up(subtotal * taxBasisPoints / 10000) via
        // intdiv(subtotal * bp * 2 + 10000, 20000). BigInt keeps it exact for very large amounts.
        function halfUpTax(subtotalMinor, taxBasisPoints) {
            return Number((BigInt(subtotalMinor) * BigInt(taxBasisPoints) * 2n + 10000n) / 20000n);
        }

        // Whole number 1..max, or null when the text is not acceptable.
        function parseQuantity(text) {
            const trimmed = String(text).trim();
            if (!/^\d+$/.test(trimmed)) {
                return null;
            }
            const quantity = Number(trimmed);
            return quantity >= 1 && quantity <= config.maxQuantity ? quantity : null;
        }

        // "500", "500.5", "1,250.75" -> paise. Never uses floating point.
        function parseAmount(text) {
            let cleaned = text.replace(/[\s,]/g, '');
            if (cleaned.startsWith(RUPEE)) {
                cleaned = cleaned.slice(1);
            }
            if (cleaned === '') {
                return { status: 'empty', minor: null };
            }
            const match = /^(\d{1,9})(?:\.(\d{1,2}))?$/.exec(cleaned);
            if (!match) {
                return { status: 'invalid', minor: null };
            }
            const paise = Number((match[2] || '').padEnd(2, '0'));
            return { status: 'ok', minor: Number(match[1]) * 100 + paise };
        }

        // Greedy change-making: largest note/coin first, whatever is left under one rupee is paise.
        function fillDenominations(list, balanceMinor) {
            list.textContent = '';
            let rupees = Math.floor(balanceMinor / 100);
            const paise = balanceMinor % 100;
            DENOMINATIONS.forEach((value) => {
                const count = Math.floor(rupees / value);
                if (count > 0) {
                    list.appendChild(h('li', {}, RUPEE + value + ' × ' + count));
                    rupees -= count * value;
                }
            });
            if (paise > 0) {
                list.appendChild(h('li', {}, paise + ' paise'));
            }
            if (list.childElementCount === 0) {
                list.appendChild(h('li', {}, 'Exact amount, no change'));
            }
        }

        function showMessages(title, items) {
            els.messages.textContent = '';
            els.messages.setAttribute('data-kind', 'error');
            els.messages.appendChild(h('strong', {}, title));
            if (items.length > 0) {
                els.messages.appendChild(h('ul', {}, items.map((item) => h('li', {}, item))));
            }
            els.messages.scrollIntoView({ block: 'nearest' });
        }

        function clearMessages() {
            els.messages.textContent = '';
            els.messages.removeAttribute('data-kind');
        }

        // ---------------------------------------------------------------
        // Product lines
        // ---------------------------------------------------------------
        function productOf(line) {
            return line.productId === null ? null : (state.productsById.get(line.productId) || null);
        }

        function productLabel(product) {
            return product.code + ' — ' + product.name + ' (' + product.stock_quantity + ' in stock)';
        }

        function canAddLine() {
            // Every line needs a distinct product, so there can never be more lines than products.
            const limit = state.products.length > 0
                ? Math.min(config.maxLines, state.products.length)
                : config.maxLines;
            return state.lines.length < limit;
        }

        function lineAmounts(product, quantity) {
            if (!product || quantity === null) {
                return null;
            }
            const subtotal = quantity * toMinor(product.price);
            const tax = halfUpTax(subtotal, toMinor(product.tax_percent));
            return { subtotal, tax, total: subtotal + tax };
        }

        function quantityHint(product, quantity) {
            if (quantity === null) {
                return 'Enter a whole number from 1 to ' + config.maxQuantity + '.';
            }
            if (product && quantity > product.stock_quantity) {
                return 'Only ' + product.stock_quantity + ' in stock.';
            }
            return '';
        }

        function fillSelect(line) {
            line.select.textContent = '';
            line.select.appendChild(h('option', { value: '' }, 'Select a product…'));
            state.products.forEach((product) => {
                line.select.appendChild(h('option', { value: product.id }, productLabel(product)));
            });
            line.select.value = line.productId === null ? '' : String(line.productId);
        }

        // Disables products already used by another line, and sold-out ones (unless this line already holds it).
        function syncOptionStates() {
            const usedBy = new Map();
            state.lines.forEach((line) => {
                if (line.productId !== null) {
                    usedBy.set(line.productId, line);
                }
            });
            state.lines.forEach((line) => {
                Array.from(line.select.options).forEach((option) => {
                    if (option.value === '') {
                        return;
                    }
                    const id = Number(option.value);
                    const product = state.productsById.get(id);
                    const usedElsewhere = usedBy.has(id) && usedBy.get(id) !== line;
                    const soldOut = !product || product.stock_quantity < 1;
                    option.disabled = usedElsewhere || (soldOut && line.productId !== id);
                });
            });
        }

        function refreshLine(line) {
            const product = productOf(line);
            const quantity = parseQuantity(line.qty.value);
            const amounts = lineAmounts(product, quantity);

            line.price.textContent = product
                ? formatMinor(toMinor(product.price)) + ' + ' + formatPercent(product.tax_percent) + '% tax'
                : '—';
            line.total.textContent = amounts ? formatMinor(amounts.total) : '—';

            const hint = quantityHint(product, quantity);
            line.hint.textContent = hint;
            if (hint) {
                line.qty.setAttribute('aria-invalid', 'true');
            } else {
                line.qty.removeAttribute('aria-invalid');
            }
        }

        function labelLines() {
            state.lines.forEach((line, index) => {
                const number = index + 1;
                line.select.setAttribute('aria-label', 'Product for line ' + number);
                line.qty.setAttribute('aria-label', 'Quantity for line ' + number);
                line.remove.setAttribute('aria-label', 'Remove line ' + number);
            });
        }

        function afterLinesChanged() {
            els.addLine.disabled = !canAddLine();
            els.linesEmpty.hidden = state.lines.length > 0;
            updateTotals();
        }

        function addLine() {
            if (!canAddLine()) {
                return null;
            }
            state.lineSeq += 1;
            const key = state.lineSeq;
            const line = { key, productId: null };

            line.select = h('select', { id: 'line-product-' + key, className: 'product-select' });
            line.qty = h('input', {
                id: 'line-qty-' + key,
                className: 'qty',
                type: 'number',
                min: 1,
                max: config.maxQuantity,
                step: 1,
                value: 1,
                inputmode: 'numeric',
                'aria-describedby': 'line-hint-' + key,
            });
            line.hint = h('span', { id: 'line-hint-' + key, className: 'hint' });
            line.price = h('td', { className: 'num' }, '—');
            line.total = h('td', { className: 'num' }, '—');
            line.remove = h('button', { type: 'button', className: 'icon-button', title: 'Remove line' }, '✕');

            line.row = h('tr', {},
                h('td', {}, line.select),
                h('td', {}, line.qty, line.hint),
                line.price,
                line.total,
                h('td', {}, line.remove));

            line.select.addEventListener('change', () => onProductChange(line));
            line.qty.addEventListener('input', () => {
                refreshLine(line);
                updateTotals();
            });
            line.remove.addEventListener('click', () => removeLine(line));

            state.lines.push(line);
            els.lines.appendChild(line.row);
            fillSelect(line);
            labelLines();
            syncOptionStates();
            refreshLine(line);
            afterLinesChanged();
            return line;
        }

        function removeLine(line) {
            const index = state.lines.indexOf(line);
            if (index === -1) {
                return;
            }
            state.lines.splice(index, 1);
            line.row.remove();
            labelLines();
            syncOptionStates();
            afterLinesChanged();

            const neighbour = state.lines[Math.min(index, state.lines.length - 1)];
            (neighbour ? neighbour.select : els.addLine).focus();
        }

        function onProductChange(line) {
            line.productId = line.select.value === '' ? null : Number(line.select.value);
            syncOptionStates();
            refreshLine(line);
            updateTotals();
        }

        // ---------------------------------------------------------------
        // Totals, cash and change
        // ---------------------------------------------------------------
        function estimateTotals() {
            let subtotal = 0;
            let tax = 0;
            state.lines.forEach((line) => {
                const amounts = lineAmounts(productOf(line), parseQuantity(line.qty.value));
                if (amounts) {
                    subtotal += amounts.subtotal;
                    tax += amounts.tax;
                }
            });
            return { subtotal, tax, grand: subtotal + tax };
        }

        function updateTotals() {
            const totals = estimateTotals();
            els.estSubtotal.textContent = formatMinor(totals.subtotal);
            els.estTax.textContent = formatMinor(totals.tax);
            els.estGrand.textContent = formatMinor(totals.grand);
            updatePayment(totals);
        }

        function updatePayment(totals) {
            const amount = parseAmount(els.amount.value);
            els.denominations.textContent = '';
            els.amountHint.textContent = '';
            els.balanceLabel.textContent = 'Balance to return';
            els.balanceValue.textContent = '—';

            if (amount.status === 'invalid') {
                els.amountHint.textContent = 'Enter an amount like 500 or 500.50.';
                return;
            }
            if (amount.status === 'empty') {
                return;
            }
            if (amount.minor < totals.grand) {
                els.balanceLabel.textContent = 'Still to pay';
                els.balanceValue.textContent = formatMinor(totals.grand - amount.minor);
                return;
            }
            const balance = amount.minor - totals.grand;
            els.balanceValue.textContent = formatMinor(balance);
            fillDenominations(els.denominations, balance);
        }

        // ---------------------------------------------------------------
        // Customer lookup
        // ---------------------------------------------------------------
        function setCustomerStatus(text, kind) {
            els.customerStatus.textContent = text;
            if (kind) {
                els.customerStatus.setAttribute('data-kind', kind);
            } else {
                els.customerStatus.removeAttribute('data-kind');
            }
        }

        // Forget everything about the previous email and unlock the name box.
        function resetCustomer() {
            window.clearTimeout(customer.timer);
            customer.seq += 1;
            customer.status = 'idle';
            customer.pending = null;
            if (customer.autoFilled) {
                els.name.value = '';
                customer.autoFilled = false;
            }
            els.name.readOnly = false;
            setCustomerStatus('', '');
        }

        async function lookupCustomer(email, seq) {
            let result;
            try {
                result = await api('/customers/orders?per_page=1&email=' + encodeURIComponent(email));
            } catch (networkError) {
                if (seq === customer.seq) {
                    customer.status = 'unknown';
                    setCustomerStatus('Could not check whether this customer exists.', 'warn');
                }
                return;
            }
            if (seq !== customer.seq) {
                return; // the email changed while we were waiting
            }

            if (result.status === 200 && result.body && result.body.customer) {
                customer.status = 'existing';
                customer.autoFilled = true;
                els.name.value = result.body.customer.name;
                els.name.readOnly = true;
                setCustomerStatus('Existing customer, name filled in.', 'ok');
            } else if (result.status === 404 && result.body && result.body.code === 'customer_not_found') {
                customer.status = 'new';
                setCustomerStatus('New customer. Please enter a name.', 'info');
            } else {
                customer.status = 'unknown';
                setCustomerStatus('Could not check whether this customer exists.', 'warn');
            }
        }

        // Always resolves; reuses the answer when the email has not changed.
        function checkCustomer() {
            const email = normalizeEmail(els.email.value);

            if (!EMAIL_PATTERN.test(email)) {
                resetCustomer();
                customer.email = email;
                if (email !== '') {
                    setCustomerStatus('Enter a valid email address.', 'warn');
                }
                return Promise.resolve();
            }

            const unchanged = customer.email === email;
            if (unchanged && customer.status === 'checking') {
                return customer.pending;
            }
            if (unchanged && (customer.status === 'existing' || customer.status === 'new')) {
                return Promise.resolve();
            }

            window.clearTimeout(customer.timer);
            customer.seq += 1;
            customer.email = email;
            customer.status = 'checking';
            setCustomerStatus('Checking customer…', 'info');
            customer.pending = lookupCustomer(email, customer.seq);
            return customer.pending;
        }

        function onEmailInput() {
            const email = normalizeEmail(els.email.value);
            if (email === customer.email && customer.status !== 'idle') {
                return;
            }
            resetCustomer();
            customer.email = email;
            if (EMAIL_PATTERN.test(email)) {
                customer.timer = window.setTimeout(checkCustomer, LOOKUP_DELAY_MS);
            }
        }

        // ---------------------------------------------------------------
        // Products and low stock
        // ---------------------------------------------------------------
        function setProductsStatus(text, canRetry) {
            els.productsStatus.textContent = text;
            els.retryProducts.hidden = !canRetry;
        }

        async function fetchAllProducts() {
            const products = [];
            let page = 1;
            let lastPage = 1;
            do {
                const result = await api('/products?per_page=' + PRODUCTS_PER_PAGE + '&page=' + page);
                if (!result.ok || !result.body || !Array.isArray(result.body.data)) {
                    throw new Error('Unexpected products response');
                }
                products.push(...result.body.data);
                lastPage = result.body.meta ? Number(result.body.meta.last_page) || 1 : 1;
                page += 1;
            } while (page <= lastPage && page <= MAX_PRODUCT_PAGES);
            return products;
        }

        async function loadProducts() {
            state.productsLoadSeq += 1;
            const seq = state.productsLoadSeq;
            setProductsStatus('Loading products…', false);

            let products;
            try {
                products = await fetchAllProducts();
            } catch (error) {
                if (seq === state.productsLoadSeq) {
                    setProductsStatus('Could not load products.', true);
                }
                return;
            }
            if (seq !== state.productsLoadSeq) {
                return; // a newer load is already running
            }

            state.products = products;
            state.productsById = new Map(products.map((product) => [product.id, product]));
            state.lines.forEach((line) => {
                if (line.productId !== null && !state.productsById.has(line.productId)) {
                    line.productId = null;
                }
                fillSelect(line);
            });
            syncOptionStates();
            state.lines.forEach(refreshLine);
            afterLinesChanged();
            setProductsStatus(products.length === 0 ? 'No products found. Run php artisan db:seed.' : '', false);
        }

        function renderLowStock(body) {
            const products = body.data;
            els.lowStockHeading.textContent = '⚠ Low Stock Alert (below ' + body.threshold + ' units)';
            els.lowStockList.textContent = '';

            products.forEach((product) => {
                const soldOut = product.stock_quantity < 1;
                els.lowStockList.appendChild(h('li', { 'data-level': soldOut ? 'out' : 'low' },
                    h('span', {},
                        h('strong', {}, product.name),
                        h('br'),
                        h('small', { className: 'muted' }, product.code)),
                    h('span', { className: 'badge' }, soldOut ? 'Out of stock' : product.stock_quantity + ' left')));
            });

            const total = body.meta && Number.isFinite(body.meta.total) ? body.meta.total : products.length;
            if (products.length === 0) {
                els.lowStockStatus.textContent = 'All products have enough stock.';
            } else if (total > products.length) {
                els.lowStockStatus.textContent = 'Showing ' + products.length + ' of ' + total + ' low-stock products.';
            } else {
                els.lowStockStatus.textContent = '';
            }
        }

        async function loadLowStock() {
            state.lowStockLoadSeq += 1;
            const seq = state.lowStockLoadSeq;
            try {
                const result = await api('/products/low-stock?per_page=100');
                if (seq !== state.lowStockLoadSeq) {
                    return;
                }
                if (!result.ok || !result.body || !Array.isArray(result.body.data)) {
                    throw new Error('Unexpected low-stock response');
                }
                renderLowStock(result.body);
            } catch (error) {
                if (seq === state.lowStockLoadSeq) {
                    els.lowStockList.textContent = '';
                    els.lowStockStatus.textContent = 'Could not load the low-stock list.';
                }
            }
        }

        // ---------------------------------------------------------------
        // Bill panel (built from the server response, not from the estimate)
        // ---------------------------------------------------------------
        function definitionList(rows) {
            const list = h('dl', { className: 'totals' });
            rows.forEach(([label, value, className]) => {
                list.appendChild(h('dt', { className }, label));
                list.appendChild(h('dd', { className }, value));
            });
            return list;
        }

        function renderBill(order, givenMinor) {
            const items = Array.isArray(order.items) ? order.items : [];
            const buyer = order.customer || {};
            const grandMinor = toMinor(order.grand_total);

            const heading = h('h2', { id: 'bill-heading' }, 'Bill #' + order.id);
            const meta = h('p', { className: 'muted' },
                formatDate(order.created_at) + ' · ' + (buyer.name || '') + ' (' + (buyer.email || '') + ')');

            const table = h('table', {},
                h('thead', {}, h('tr', {},
                    h('th', { scope: 'col' }, 'Product'),
                    h('th', { scope: 'col', className: 'num' }, 'Qty'),
                    h('th', { scope: 'col', className: 'num' }, 'Unit price'),
                    h('th', { scope: 'col', className: 'num' }, 'Tax'),
                    h('th', { scope: 'col', className: 'num' }, 'Line total'))),
                h('tbody', {}, items.map((item) => h('tr', {},
                    h('td', {}, item.product_name, h('br'), h('small', { className: 'muted' }, item.product_code)),
                    h('td', { className: 'num' }, item.quantity),
                    h('td', { className: 'num' }, money(item.unit_price)),
                    h('td', { className: 'num' }, formatPercent(item.tax_percent) + '%'),
                    h('td', { className: 'num' }, money(item.line_total))))));

            const totals = definitionList([
                ['Subtotal', money(order.subtotal)],
                ['Tax', money(order.tax)],
                ['Grand total', money(order.grand_total), 'strong'],
            ]);

            const parts = [heading, meta, h('div', { className: 'table-wrap' }, table), totals];

            if (givenMinor !== null) {
                const cashRows = [['Amount given', formatMinor(givenMinor)]];
                if (givenMinor >= grandMinor) {
                    const balance = givenMinor - grandMinor;
                    cashRows.push(['Balance to return', formatMinor(balance), 'strong']);
                    parts.push(definitionList(cashRows));
                    const change = h('ul', { className: 'denoms', 'aria-label': 'Change breakdown' });
                    fillDenominations(change, balance);
                    parts.push(change);
                } else {
                    cashRows.push(['Still to pay', formatMinor(grandMinor - givenMinor), 'strong']);
                    parts.push(definitionList(cashRows));
                }
            }

            parts.push(h('p', { className: 'note' },
                'Confirmation email queued. See storage/logs/laravel.log after ',
                h('code', {}, 'php artisan queue:work'),
                ' runs.'));

            const printButton = h('button', { type: 'button', className: 'primary' }, 'Print');
            printButton.addEventListener('click', () => window.print());
            const dismissButton = h('button', { type: 'button' }, 'New order');
            dismissButton.addEventListener('click', () => {
                els.bill.hidden = true;
                els.bill.textContent = '';
                els.email.focus();
            });
            parts.push(h('div', { className: 'actions no-print' }, printButton, dismissButton));

            els.bill.textContent = '';
            parts.forEach((part) => els.bill.appendChild(part));
            els.bill.hidden = false;
            els.bill.scrollIntoView({ block: 'start' });
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }

        // ---------------------------------------------------------------
        // Submit
        // ---------------------------------------------------------------
        function setSubmitting(isSubmitting) {
            state.submitting = isSubmitting;
            els.submit.disabled = isSubmitting;
            els.submit.textContent = isSubmitting ? 'Generating…' : 'Generate Bill';
            els.form.setAttribute('aria-busy', String(isSubmitting));
        }

        // Client-side checks mirror the server rules so the common mistakes get a quick, friendly answer.
        // The server still validates everything.
        function collectOrder() {
            const problems = [];
            const email = normalizeEmail(els.email.value);
            const name = els.name.value.trim();

            if (!EMAIL_PATTERN.test(email)) {
                problems.push('Enter a valid customer email address.');
            } else if (customer.status === 'new' && name === '') {
                problems.push('Enter the name of the new customer.');
            }

            const items = [];
            let chosenLines = 0;
            state.lines.forEach((line, index) => {
                if (line.productId === null) {
                    return; // empty rows are ignored
                }
                chosenLines += 1;
                const quantity = parseQuantity(line.qty.value);
                if (quantity === null) {
                    problems.push('Line ' + (index + 1) + ': quantity must be a whole number from 1 to ' + config.maxQuantity + '.');
                } else {
                    items.push({ product_id: line.productId, quantity });
                }
            });
            if (chosenLines === 0) {
                problems.push('Add at least one product to the order.');
            }

            const amount = parseAmount(els.amount.value);
            if (amount.status === 'invalid') {
                problems.push('Amount given must be a number like 500 or 500.50.');
            }

            return {
                problems,
                amountMinor: amount.minor,
                payload: {
                    customer: { name: name === '' ? null : name, email },
                    items,
                },
            };
        }

        function describeShortage(shortage) {
            const name = shortage.name || ('Product #' + shortage.product_id);
            const code = shortage.code || 'unknown code';
            return name + ' (' + code + '): requested ' + shortage.requested + ', only ' + shortage.available + ' available';
        }

        function flattenErrors(errors) {
            const messages = [];
            Object.values(errors).forEach((fieldErrors) => {
                [].concat(fieldErrors).forEach((message) => {
                    if (!messages.includes(message)) {
                        messages.push(message);
                    }
                });
            });
            return messages;
        }

        function resetForm() {
            resetCustomer();
            customer.email = '';
            els.email.value = '';
            els.name.value = '';
            els.amount.value = '';
            state.lines.forEach((line) => line.row.remove());
            state.lines = [];
            addLine(); // also refreshes the totals
        }

        function handleOrderResult(result, amountMinor) {
            const body = result.body || {};

            if (result.status === 201 && body.data) {
                renderBill(body.data, amountMinor);
                resetForm();
                loadProducts();
                loadLowStock();
            } else if (result.status === 409 && Array.isArray(body.shortages)) {
                showMessages(body.message || 'Not enough stock.', body.shortages.map(describeShortage));
                loadProducts();
                loadLowStock();
            } else if (result.status === 422 && body.errors) {
                showMessages(body.message || 'The order could not be validated.', flattenErrors(body.errors));
            } else {
                showMessages(body.message || 'Unexpected response from the server (HTTP ' + result.status + ').', []);
            }
        }

        async function handleSubmit(event) {
            event.preventDefault();
            if (state.submitting) {
                return;
            }
            setSubmitting(true);
            clearMessages();

            try {
                await checkCustomer();
                const order = collectOrder();
                if (order.problems.length > 0) {
                    showMessages('Please fix the following before generating the bill:', order.problems);
                    return;
                }

                let result;
                try {
                    result = await api('/orders', { method: 'POST', body: JSON.stringify(order.payload) });
                } catch (networkError) {
                    showMessages('Could not reach the server, so the bill was not confirmed.',
                        ['Check the customer\'s order history before trying again, to avoid a duplicate bill.']);
                    return;
                }
                handleOrderResult(result, order.amountMinor);
            } catch (error) {
                showMessages('Something went wrong on this page.', [
                    error && error.message ? error.message : String(error),
                    'If you just pressed Generate Bill, check the customer\'s order history before trying again.',
                ]);
            } finally {
                setSubmitting(false);
            }
        }

        // ---------------------------------------------------------------
        // Wiring
        // ---------------------------------------------------------------
        els.form.addEventListener('submit', handleSubmit);
        els.addLine.addEventListener('click', () => {
            const line = addLine();
            if (line) {
                line.select.focus();
            }
        });
        els.retryProducts.addEventListener('click', loadProducts);
        els.amount.addEventListener('input', updateTotals);
        els.email.addEventListener('input', onEmailInput);
        els.email.addEventListener('change', checkCustomer);

        // Enter inside a text box should not submit the bill by accident. Only the amount box (last step) may.
        els.form.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.isComposing || !(event.target instanceof HTMLInputElement)) {
                return;
            }
            if (event.target === els.amount) {
                return;
            }
            event.preventDefault();
            if (event.target === els.email) {
                checkCustomer();
            }
        });

        addLine();
        loadProducts();
        loadLowStock();
    })();
    </script>
@endverbatim
</body>
</html>
