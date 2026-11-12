/**
 * Harness untuk menguji JavaScript halaman Detail Ruangan (`aset-ruangan/show`)
 * di luar browser.
 *
 * Meniru lingkungan DOM secukupnya, menjalankan SEMUA blok <script> dari file
 * blade (persis seperti browser: script terpisah, cakupan `const` terpisah),
 * lalu mensimulasikan:
 *
 *   1. klik ikon Mutasi Ruangan pada tabel Barang Melekat,
 *   2. submit form modal Mutasi Ruangan.
 *
 * Hasil (alert, pemanggilan fetch, error) dikembalikan sebagai JSON di stdout
 * supaya bisa diperiksa PHPUnit.
 *
 * Dipakai oleh tests/Feature/MutasiRuanganJsTest.php.
 *
 * Jalankan manual:
 *   node tests/Support/js/mutasi-ruangan-harness.js resources/views/aset-ruangan/show.blade.php
 */
'use strict';

import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const bladePath = process.argv[2];

if (!bladePath) {
    console.error('Usage: node mutasi-ruangan-harness.js <path-blade>');
    process.exit(2);
}

/**
 * Ganti ekspresi Blade `@json(...)` (dengan kurung bersarang) menjadi string
 * literal, agar script hasil ekstraksi bisa langsung dieksekusi.
 */
function gantiJsonBlade(src) {
    let out = '';
    let i = 0;

    while (i < src.length) {
        const mulai = src.indexOf('@json(', i);

        if (mulai === -1) {
            out += src.slice(i);
            break;
        }

        out += src.slice(i, mulai);

        let depth = 0;
        let j = mulai + '@json'.length;

        for (; j < src.length; j++) {
            if (src[j] === '(') depth++;
            else if (src[j] === ')') {
                depth--;
                if (depth === 0) break;
            }
        }

        out += '"http://stub.test/route"';
        i = j + 1;
    }

    return out;
}

function ekstrakScriptBlocks(src) {
    const blocks = [];
    const re = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
    let m;

    while ((m = re.exec(src)) !== null) {
        blocks.push(m[1]);
    }

    return blocks;
}

function buatElemen(id) {
    const listeners = {};

    return {
        id: id,
        value: '',
        textContent: '',
        innerHTML: '',
        disabled: false,
        hidden: false,
        action: '',
        method: '',
        style: {},
        dataset: {},
        options: [],
        selectedIndex: 0,
        classList: {
            add() {},
            remove() {},
            toggle() {},
            contains() { return false; },
        },
        addEventListener(type, handler) {
            (listeners[type] = listeners[type] || []).push(handler);
        },
        removeEventListener() {},
        dispatch(type, evt) {
            (listeners[type] || []).forEach((h) => h(evt || { preventDefault() {} }));
        },
        querySelectorAll() { return []; },
        querySelector() { return null; },
        reset() {},
        appendChild() {},
        remove() {},
        focus() {},
        click() { this.dispatch('click', { preventDefault() {} }); },
        getAttribute(name) {
            if (name === 'value') return this.value;
            return null;
        },
        setAttribute(name, val) {
            if (name === 'action') this.action = val;
        },
    };
}

function main() {
    const src = fs.readFileSync(bladePath, 'utf8');
    const blocks = ekstrakScriptBlocks(src).map(gantiJsonBlade);

    const elements = new Map();
    const alerts = [];
    const fetchCalls = [];
    const errors = [];

    function element(id) {
        if (!elements.has(id)) {
            elements.set(id, buatElemen(id));
        }
        return elements.get(id);
    }

    const tombolMutasi = buatElemen('tombol-mutasi-ruangan');
    tombolMutasi.getAttribute = function (name) {
        if (name === 'data-mutasi-ruangan') return '704';
        if (name === 'data-aset-nama') return 'KURSI GURU - TYPE HARMONI';
        if (name === 'data-aset-kib') return '11.01.35.00.020301.00000.00000.2025-1.3.2.05.02.01.000.000030';
        return null;
    };

    let reloaded = false;
    let loginRedirect = null;

    const metaCsrf = {
        getAttribute(name) {
            return name === 'content' ? 'TOKEN-CSRF-ABC123' : null;
        },
    };

    const documentStub = {
        body: { classList: { add() {}, remove() {} } },
        getElementById(id) {
            return element(id);
        },
        querySelector(sel) {
            if (sel === 'meta[name="csrf-token"]') return metaCsrf;
            return element(sel);
        },
        querySelectorAll(sel) {
            if (sel === '[data-mutasi-ruangan]') return [tombolMutasi];
            return [];
        },
        createElement(tag) {
            return buatElemen(tag);
        },
        addEventListener() {},
    };

    const sandbox = {
        document: documentStub,
        window: {
            location: {
                reload() { reloaded = true; },
                get href() { return loginRedirect; },
                set href(v) { loginRedirect = v; },
            },
        },
        location: { reload() { reloaded = true; } },
        alert(msg) { alerts.push(String(msg)); },
        console: {
            log() {},
            warn() {},
            error(...args) {
                errors.push(args.map((a) => (a && a.stack) ? a.stack : String(a)).join(' '));
            },
        },
        fetch(url, options) {
            fetchCalls.push({ url: String(url), options: options || {} });
            return Promise.resolve({
                status: 200,
                ok: true,
                json() {
                    return Promise.resolve({ success: true, message: 'Aset berhasil dipindah.' });
                },
            });
        },
        FormData: class {
            constructor(form) { this.form = form; }
        },
        setTimeout,
        clearTimeout,
        Promise,
        JSON,
        Object,
        Array,
        String,
        Number,
        Boolean,
        Math,
        Date,
        Error,
        TypeError,
        ReferenceError,
        RegExp,
        parseInt,
        parseFloat,
        isNaN,
    };
    sandbox.window.document = documentStub;
    sandbox.globalThis = sandbox;

    const context = vm.createContext(sandbox);

    for (const block of blocks) {
        vm.runInContext(block, context, { filename: path.basename(bladePath) });
    }

    // 1. Admin menekan ikon Mutasi Ruangan.
    tombolMutasi.dispatch('click', { preventDefault() {} });

    // 2. Admin menekan tombol "Pindahkan" pada modal.
    element('mutasi-ruangan-form').dispatch('submit', { preventDefault() {} });

    // Tunggu promise async di dalam handler selesai.
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({
                alerts,
                errors,
                reloaded,
                loginRedirect,
                fetchCalls: fetchCalls.map((c) => ({
                    url: c.url,
                    headers: c.options.headers || {},
                    method: c.options.method || null,
                })),
            });
        }, 50);
    });
}

main()
    .then((result) => {
        process.stdout.write(JSON.stringify(result));
    })
    .catch((err) => {
        process.stderr.write('HARNESS ERROR: ' + (err && err.stack ? err.stack : String(err)));
        process.exit(1);
    });
