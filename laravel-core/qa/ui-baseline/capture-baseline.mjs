import { spawn, spawnSync } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const qaDirectory = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(.:)/, '$1')));
const artifactDirectory = process.env.QA_OUTPUT_DIR ? path.resolve(process.env.QA_OUTPUT_DIR) : qaDirectory;
if (artifactDirectory !== qaDirectory && !artifactDirectory.startsWith(`${qaDirectory}${path.sep}`)) {
    throw new Error(`Artefact klasörü QA alanı dışında olamaz: ${artifactDirectory}`);
}
const screenshotDirectory = path.join(artifactDirectory, 'screenshots');
const temporaryDirectory = path.join(qaDirectory, 'tmp');
const reportPath = path.join(artifactDirectory, 'report.json');
const baseUrl = (process.env.QA_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const chromePath = process.env.QA_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const mysqlPath = process.env.QA_MYSQL_PATH || 'C:\\xampp\\mysql\\bin\\mysql.exe';
const cdpPort = Number(process.env.QA_CDP_PORT || 9334);
const manager = {
    type: 'manager',
    loginPath: '/yonetici-giris',
    user: requiredEnvironment('QA_MANAGER_USER'),
    password: requiredEnvironment('QA_MANAGER_PASSWORD'),
};
const tenant = {
    type: 'tenant',
    loginPath: '/giris',
    code: requiredEnvironment('QA_TENANT_CODE'),
    user: requiredEnvironment('QA_TENANT_USER'),
    password: requiredEnvironment('QA_TENANT_PASSWORD'),
};

const layouts = ['modern-vertical', 'compact-vertical', 'horizontal'];
const expectedNavigation = {
    manager: { count: 16, present: ['Sistem yönetimi'], absent: [] },
    tenant: { count: 12, present: [], absent: ['Masraf Takibi', 'Ajanda ve Görevler'] },
};

const capturePlan = [
    // Yönetici — Modern Vertical + light + form/table/system ekranları.
    shot('admin-modern-dashboard-1440.png', manager, 'modern-vertical', 'light', '/admin', 1440, 900),
    shot('admin-modern-users-1440.png', manager, 'modern-vertical', 'light', '/admin/sistem-kullanicilar', 1440, 900),
    shot('admin-modern-users-modal-1440.png', manager, 'modern-vertical', 'light', '/admin/sistem-kullanicilar', 1440, 900, null, 'aktif_pasif'),
    shot('admin-modern-companies-1366.png', manager, 'modern-vertical', 'light', '/admin/sistem-firmalar', 1366, 768),
    shot('admin-modern-settings-1024.png', manager, 'modern-vertical', 'light', '/admin/sistem-ayarlari', 1024, 768),
    shot('admin-modern-dashboard-390.png', manager, 'modern-vertical', 'light', '/admin', 390, 844),

    // Yönetici — Compact + dark + submenu ve mobil fallback.
    shot('admin-compact-dashboard-1440.png', manager, 'compact-vertical', 'dark', '/admin', 1440, 900),
    shot('admin-compact-users-1440.png', manager, 'compact-vertical', 'dark', '/admin/sistem-kullanicilar', 1440, 900),
    shot('admin-compact-submenu-open-1366.png', manager, 'compact-vertical', 'dark', '/admin', 1366, 768, 'Sistem yönetimi'),
    shot('admin-compact-dashboard-390.png', manager, 'compact-vertical', 'dark', '/admin', 390, 844),

    // Yönetici — Horizontal + dark + dropdown ve mobil fallback.
    shot('admin-horizontal-dashboard-1440.png', manager, 'horizontal', 'dark', '/admin', 1440, 900),
    shot('admin-horizontal-users-1366.png', manager, 'horizontal', 'dark', '/admin/sistem-kullanicilar', 1366, 768),
    shot('admin-horizontal-dropdown-open-1440.png', manager, 'horizontal', 'dark', '/admin', 1440, 900, 'Sistem yönetimi'),
    shot('admin-horizontal-dashboard-390.png', manager, 'horizontal', 'dark', '/admin', 390, 844),

    // Firma — Modern + kritik modül ekranları ve tüm küçük viewport örnekleri.
    shot('tenant-modern-dashboard-1440.png', tenant, 'modern-vertical', 'light', '/admin', 1440, 900),
    shot('tenant-modern-accounting-1440.png', tenant, 'modern-vertical', 'light', '/admin/muhasebe/raporlar/gelir-gider', 1440, 900),
    shot('tenant-modern-personnel-1024.png', tenant, 'modern-vertical', 'light', '/admin/personel-takip/raporlar/personel-ozeti', 1024, 768),
    shot('tenant-modern-restaurant-768.png', tenant, 'modern-vertical', 'light', '/admin/restoran/masa-ekrani', 768, 1024),
    shot('tenant-modern-web-blog-430.png', tenant, 'modern-vertical', 'light', '/admin/web/bloglar/blog-listesi', 430, 844),
    shot('tenant-modern-offers-390.png', tenant, 'modern-vertical', 'light', '/admin/teklif-yonetimi/teklifler', 390, 844),
    shot('tenant-modern-barcode-375.png', tenant, 'modern-vertical', 'light', '/admin/muhasebe/satis/barkodlu-satis', 375, 812),
    shot('tenant-modern-dashboard-320.png', tenant, 'modern-vertical', 'light', '/admin', 320, 800),

    // Firma — Compact + dark + teknik servis + submenu + mobil fallback.
    shot('tenant-compact-dashboard-1440.png', tenant, 'compact-vertical', 'dark', '/admin', 1440, 900),
    shot('tenant-compact-technical-service-1366.png', tenant, 'compact-vertical', 'dark', '/admin/teknik-servis/servis-kayitlari', 1366, 768),
    shot('tenant-compact-submenu-open-1440.png', tenant, 'compact-vertical', 'dark', '/admin', 1440, 900, 'Muhasebe'),
    shot('tenant-compact-dashboard-430.png', tenant, 'compact-vertical', 'dark', '/admin', 430, 844),
    shot('tenant-compact-dashboard-375.png', tenant, 'compact-vertical', 'dark', '/admin', 375, 812),

    // Firma — Horizontal + light/dark + dropdown + tablet/mobile fallback.
    shot('tenant-horizontal-dashboard-1440-light.png', tenant, 'horizontal', 'light', '/admin', 1440, 900),
    shot('tenant-horizontal-accounting-1366-dark.png', tenant, 'horizontal', 'dark', '/admin/muhasebe/raporlar/gelir-gider', 1366, 768),
    shot('tenant-horizontal-dropdown-open-1440.png', tenant, 'horizontal', 'dark', '/admin', 1440, 900, 'Muhasebe'),
    shot('tenant-horizontal-dashboard-1024.png', tenant, 'horizontal', 'light', '/admin', 1024, 768),
    shot('tenant-horizontal-dashboard-768.png', tenant, 'horizontal', 'light', '/admin', 768, 1024),
    shot('tenant-horizontal-dashboard-390.png', tenant, 'horizontal', 'light', '/admin', 390, 844),
    shot('tenant-horizontal-dashboard-320.png', tenant, 'horizontal', 'light', '/admin', 320, 800),
];

const report = {
    schemaVersion: 1,
    phase: '4C',
    runRole: process.env.QA_RUN_ROLE || 'baseline',
    generatedAt: new Date().toISOString(),
    baseUrl,
    tooling: {
        browser: 'Google Chrome headless (Chrome DevTools Protocol)',
        externalBrowserPackageInstalled: false,
        screenshotDirectory: 'screenshots',
    },
    users: {},
    snapshots: [],
    spa: [],
    persistence: [],
    publicFrontend: [],
    assertions: {},
    runtime: {},
    performance: {},
    restore: {},
};

let chromeProcess;
let client;
let initialLayouts = [];
let activeAccountType = null;
let activeLayout = null;
let activeTheme = null;
let navigationStatus = null;
let eventBuffer = createEventBuffer();

try {
    await prepareDirectories();
    initialLayouts = readInitialLayouts();
    report.users = Object.fromEntries(initialLayouts.map((row) => [row.type, {
        id: row.id,
        user: row.user,
        initialLayout: row.layout,
    }]));

    chromeProcess = launchChrome();
    const webSocketUrl = await waitForChrome();
    client = await connectCdp(webSocketUrl);
    await Promise.all([
        client.send('Page.enable'),
        client.send('Runtime.enable'),
        client.send('Network.enable'),
        client.send('Log.enable'),
    ]);
    registerRuntimeEvents(client);

    for (const entry of capturePlan) {
        await ensureAccount(entry.account);
        await setViewport(entry.width, entry.height);
        await ensureLayout(entry.layout);
        await ensureTheme(entry.theme);
        await navigate(entry.route);
        await normalizeSidebarState(entry.width);

        if (entry.openMenu) {
            await openNavigationGroup(entry.openMenu);
        }
        if (entry.openModalAction) {
            await openTableActionModal(entry.openModalAction);
        }

        const metadata = await collectMetadata(entry);
        await takeScreenshot(entry.file);
        metadata.screenshot = `screenshots/${entry.file}`;
        report.snapshots.push(metadata);
    }

    await runSpaBaselines();
    await runPersistenceBaselines();
    await runPublicFrontendChecks();
    finalizeReport();
} catch (error) {
    report.failed = true;
    report.failure = sanitizeError(error);
    finalizeReport();
    process.exitCode = 1;
} finally {
    try {
        report.restore = restoreInitialLayouts(initialLayouts);
    } catch (restoreError) {
        report.restore = { ok: false, error: sanitizeError(restoreError) };
        process.exitCode = 1;
    }

    report.completedAt = new Date().toISOString();
    await writeFile(reportPath, `${JSON.stringify(report, null, 2)}\n`, 'utf8');

    if (client) {
        client.close();
    }
    if (chromeProcess && !chromeProcess.killed) {
        chromeProcess.kill();
    }

    if (isSafeTemporaryPath(temporaryDirectory)) {
        await delay(500);
        await rm(temporaryDirectory, { recursive: true, force: true }).catch(() => undefined);
    }
}

function shot(file, account, layout, theme, route, width, height, openMenu = null, openModalAction = null) {
    return { file, account, layout, theme, route, width, height, openMenu, openModalAction };
}

function requiredEnvironment(name) {
    const value = process.env[name];
    if (!value || !value.trim()) {
        throw new Error(`Eksik ortam değişkeni: ${name}`);
    }
    return value;
}

async function prepareDirectories() {
    await mkdir(artifactDirectory, { recursive: true });
    await mkdir(screenshotDirectory, { recursive: true });
    await mkdir(temporaryDirectory, { recursive: true });
}

function launchChrome() {
    const args = [
        '--headless=new',
        '--disable-gpu',
        '--disable-extensions',
        '--disable-background-networking',
        '--no-first-run',
        '--no-default-browser-check',
        '--hide-scrollbars',
        `--remote-debugging-port=${cdpPort}`,
        `--user-data-dir=${path.join(temporaryDirectory, 'chrome-profile')}`,
        '--window-size=1440,900',
        'about:blank',
    ];

    return spawn(chromePath, args, { stdio: 'ignore', windowsHide: true });
}

async function waitForChrome() {
    for (let attempt = 0; attempt < 40; attempt += 1) {
        try {
            const list = await fetch(`http://127.0.0.1:${cdpPort}/json/list`).then((response) => response.json());
            const page = list.find((target) => target.type === 'page');
            if (page?.webSocketDebuggerUrl) {
                return page.webSocketDebuggerUrl;
            }
        } catch {
            // Chrome henüz hazır değil.
        }
        await delay(250);
    }
    throw new Error('Chrome DevTools endpoint zamanında hazır olmadı.');
}

async function connectCdp(webSocketUrl) {
    const socket = new WebSocket(webSocketUrl);
    const pending = new Map();
    const handlers = new Map();
    let sequence = 0;

    await new Promise((resolve, reject) => {
        socket.addEventListener('open', resolve, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });

    socket.addEventListener('message', (event) => {
        const message = JSON.parse(event.data);
        if (message.id) {
            const deferred = pending.get(message.id);
            if (!deferred) return;
            pending.delete(message.id);
            if (message.error) deferred.reject(new Error(message.error.message));
            else deferred.resolve(message.result || {});
            return;
        }

        for (const handler of handlers.get(message.method) || []) {
            handler(message.params || {});
        }
    });

    return {
        send(method, params = {}) {
            sequence += 1;
            return new Promise((resolve, reject) => {
                pending.set(sequence, { resolve, reject });
                socket.send(JSON.stringify({ id: sequence, method, params }));
            });
        },
        on(method, handler) {
            if (!handlers.has(method)) handlers.set(method, []);
            handlers.get(method).push(handler);
        },
        close() {
            socket.close();
        },
    };
}

function registerRuntimeEvents(cdp) {
    cdp.on('Runtime.exceptionThrown', ({ exceptionDetails }) => {
        eventBuffer.errors.push({
            type: 'exception',
            text: exceptionDetails?.exception?.description || exceptionDetails?.text || 'Runtime exception',
            url: exceptionDetails?.url || null,
        });
    });

    cdp.on('Runtime.consoleAPICalled', ({ type, args }) => {
        if (!['error', 'warning'].includes(type)) return;
        const text = args.map((arg) => arg.value ?? arg.description ?? '').join(' ');
        eventBuffer[type === 'error' ? 'errors' : 'warnings'].push({ type: `console-${type}`, text });
    });

    cdp.on('Log.entryAdded', ({ entry }) => {
        if (!['error', 'warning'].includes(entry?.level)) return;
        eventBuffer[entry.level === 'error' ? 'errors' : 'warnings'].push({
            type: `log-${entry.level}`,
            text: entry.text,
            url: entry.url || null,
        });
    });

    cdp.on('Network.responseReceived', ({ requestId, type, response }) => {
        eventBuffer.responses.set(requestId, {
            requestId,
            type,
            url: response.url,
            status: response.status,
            mimeType: response.mimeType,
            encodedDataLength: 0,
        });
        if (type === 'Document' && response.url.startsWith(baseUrl)) {
            navigationStatus = { url: response.url, status: response.status };
        }
    });

    cdp.on('Network.loadingFinished', ({ requestId, encodedDataLength }) => {
        const response = eventBuffer.responses.get(requestId);
        if (response) response.encodedDataLength = encodedDataLength;
    });

    cdp.on('Network.loadingFailed', ({ requestId, errorText }) => {
        const response = eventBuffer.responses.get(requestId);
        eventBuffer.failedRequests.push({
            url: response?.url || null,
            errorText,
        });
    });
}

function createEventBuffer() {
    return { errors: [], warnings: [], responses: new Map(), failedRequests: [] };
}

async function ensureAccount(account) {
    if (activeAccountType === account.type && (await currentUrl()).startsWith(`${baseUrl}/admin`)) return;

    await client.send('Network.clearBrowserCookies');
    activeAccountType = null;
    activeLayout = null;
    await navigate(account.loginPath, false);

    const selector = account.type === 'manager' ? 'form[data-auth-form]' : '#firma-giris-formu';
    const submitted = await evaluate(`(() => {
        const form = document.querySelector(${JSON.stringify(selector)});
        if (!form) return { ok: false, reason: 'form-not-found' };
        const set = (name, value) => {
            const input = form.querySelector('[name="' + name + '"]');
            if (!input) return false;
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };
        ${account.type === 'tenant' ? `set('firma_kodu', ${JSON.stringify(account.code)});` : ''}
        set('kullanici_adi_veya_eposta', ${JSON.stringify(account.user)});
        set('sifre', ${JSON.stringify(account.password)});
        form.requestSubmit();
        return { ok: true };
    })()`);
    if (!submitted?.ok) throw new Error(`${account.type} giriş formu gönderilemedi.`);

    await waitFor(() => currentUrl().then((url) => url.startsWith(`${baseUrl}/admin`)), 15000, `${account.type} giriş yönlendirmesi`);
    await waitForAdminReady();
    activeAccountType = account.type;
    activeLayout = await currentLayout();
}

async function ensureLayout(layout) {
    if (!layouts.includes(layout)) throw new Error(`Geçersiz layout: ${layout}`);
    const current = await currentLayout();
    if (current === layout) {
        activeLayout = layout;
        return;
    }

    const result = await evaluate(`(() => {
        const trigger = document.querySelector('.fi-user-menu > button, .fi-user-menu button');
        if (!trigger) return { ok: false, reason: 'user-menu-trigger-not-found' };
        trigger.click();
        return { ok: true };
    })()`);
    if (!result?.ok) throw new Error('Kullanıcı menüsü açılamadı.');
    await delay(350);

    const clicked = await evaluate(`(() => {
        const buttons = Array.from(document.querySelectorAll('.saas-layout-switcher button'));
        const target = buttons.find((button) => {
            const wire = button.getAttribute('wire:click') || '';
            return wire.includes(${JSON.stringify(layout)});
        });
        if (!target) return { ok: false, options: buttons.map((button) => button.textContent.trim()) };
        target.click();
        return { ok: true };
    })()`);
    if (!clicked?.ok) throw new Error(`Layout seçeneği bulunamadı: ${layout}`);

    await waitFor(() => currentLayout().then((value) => value === layout), 15000, `${layout} root class`);
    await waitForAdminReady();
    activeLayout = layout;
}

async function ensureTheme(theme) {
    const desiredDark = theme === 'dark';
    const currentDark = await evaluate("document.documentElement.classList.contains('dark')");
    if (currentDark === desiredDark && activeTheme === theme) return;

    await evaluate(`(() => {
        localStorage.setItem('theme', ${JSON.stringify(theme)});
        document.documentElement.classList.toggle('dark', ${desiredDark});
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: ${JSON.stringify(theme)} }));
        return document.documentElement.className;
    })()`);
    activeTheme = theme;
    await waitFor(
        () => evaluate("document.documentElement.classList.contains('dark')").then((isDark) => isDark === desiredDark),
        3000,
        `${theme} tema class`,
    );
    await delay(200);
}

async function setViewport(width, height) {
    await client.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: false,
        screenWidth: width,
        screenHeight: height,
    });
}

async function navigate(route, resetEvents = true) {
    if (resetEvents) eventBuffer = createEventBuffer();
    navigationStatus = null;
    const url = route.startsWith('http') ? route : `${baseUrl}${route}`;
    await client.send('Page.navigate', { url });
    await waitFor(() => currentUrl().then((current) => current.split('#')[0] === url.split('#')[0]), 15000, `URL ${route}`);
    await waitFor(() => evaluate("document.readyState === 'complete'"), 15000, `document ready ${route}`);
    await delay(route.startsWith('/admin') ? 1200 : 400);
}

async function waitForAdminReady() {
    await waitFor(() => evaluate("Boolean(document.body?.classList.contains('fi-panel-admin') && document.querySelector('.custom-sidebar'))"), 15000, 'admin shell');
    await delay(700);
}

async function openNavigationGroup(label) {
    if (activeLayout === 'compact-vertical') {
        await client.send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 38, y: 150 });
        await delay(350);
    }

    const result = await evaluate(`(() => {
        const items = Array.from(document.querySelectorAll('.custom-sidebar > nav > .nav-item'));
        const target = items.find((item) => {
            const labelNode = item.querySelector('.nav-item-start');
            return (labelNode?.textContent || item.textContent || '').replace(/\\s+/g, ' ').trim() === ${JSON.stringify(label)};
        });
        if (!target) return { ok: false, labels: items.map((item) => (item.textContent || '').replace(/\\s+/g, ' ').trim()) };
        target.focus({ preventScroll: true });
        target.click();
        return { ok: true, tag: target.tagName, focused: document.activeElement === target };
    })()`);
    if (!result?.ok) throw new Error(`Navigation grubu açılamadı: ${label}`);
    await delay(400);
}

async function openTableActionModal(actionName) {
    const result = await evaluate(`(() => {
        const candidates = Array.from(document.querySelectorAll('button')).filter((button) => {
            const wireClick = button.getAttribute('wire:click') || '';
            return wireClick.includes('mountTableAction') && wireClick.includes(${JSON.stringify(actionName)});
        });
        if (candidates.length === 0) return { ok: false, count: 0 };
        candidates[0].click();
        return { ok: true, count: candidates.length };
    })()`);
    if (!result?.ok) throw new Error(`Table action modalı açılamadı: ${actionName}`);
    await waitFor(
        () => evaluate("Boolean(document.querySelector('.fi-modal.fi-modal-open .fi-modal-window'))"),
        10000,
        `${actionName} modal`,
    );
    await delay(350);
}

async function normalizeSidebarState(width) {
    await evaluate(`(() => {
        const store = window.Alpine?.store?.('sidebar');
        if (!store) return false;
        if (${width} >= 1024) store.open();
        else store.close();
        return store.isOpen;
    })()`);
    await delay(250);
}

async function collectMetadata(entry) {
    const dom = await evaluate(`(() => {
        const clean = (value) => (value || '').replace(/\\s+/g, ' ').trim();
        const rect = (selector) => {
            const element = document.querySelector(selector);
            if (!element) return null;
            const box = element.getBoundingClientRect();
            const style = getComputedStyle(element);
            return { x: box.x, y: box.y, width: box.width, height: box.height, display: style.display, position: style.position };
        };
        const topItems = Array.from(document.querySelectorAll('.custom-sidebar > nav > .nav-item'));
        const labels = topItems.map((item) => clean(item.querySelector('.nav-item-start')?.textContent || item.textContent));
        const activeItems = Array.from(document.querySelectorAll('.custom-sidebar .nav-item.is-active')).map((item) => clean(item.textContent));
        const resources = performance.getEntriesByType('resource').map((resource) => ({
            name: resource.name,
            initiatorType: resource.initiatorType,
            transferSize: resource.transferSize || 0,
            encodedBodySize: resource.encodedBodySize || 0,
        }));
        const bodyClass = document.body.className;
        const layoutClasses = Array.from(document.body.classList).filter((name) => name.startsWith('saas-layout-'));
        const sidebar = rect('.fi-sidebar');
        const mobile = innerWidth < 1024;
        const renderer = mobile
            ? 'mobile-vertical-drawer'
            : (bodyClass.includes('saas-layout-horizontal') ? 'horizontal-navigation' : (bodyClass.includes('saas-layout-compact-vertical') ? 'compact-sidebar' : 'modern-sidebar'));

        return {
            title: document.title,
            url: location.href,
            bodyClass,
            layoutClasses,
            rootLayoutClass: layoutClasses[0] || null,
            htmlDark: document.documentElement.classList.contains('dark'),
            navigation: {
                count: labels.length,
                labels,
                activeItems,
                activeCount: activeItems.length,
            },
            secondaryNavigation: {
                sidebar: document.querySelectorAll('.fi-page-sub-navigation-sidebar').length,
                select: document.querySelectorAll('.fi-page-sub-navigation-select').length,
                tabs: document.querySelectorAll('.fi-page-sub-navigation-tabs').length,
            },
            layout: {
                renderer,
                sidebar,
                sidebarHeader: rect('.fi-sidebar-header'),
                navigation: rect('.custom-sidebar'),
                topbar: rect('.fi-topbar'),
                main: rect('.fi-main'),
                mobileDrawerExists: mobile ? Boolean(document.querySelector('.fi-sidebar')) : null,
                horizontalNavigationVisible: !mobile && bodyClass.includes('saas-layout-horizontal') && sidebar?.position === 'sticky',
            },
            overflow: {
                horizontal: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: document.documentElement.clientWidth,
            },
            serverErrorInDom: /Server Error|Internal Server Error|HTTP 500/i.test(document.body.innerText),
            modal: {
                count: document.querySelectorAll('.fi-modal').length,
                visibleCount: document.querySelectorAll('.fi-modal.fi-modal-open .fi-modal-window').length,
            },
            resources,
        };
    })()`);

    const responses = Array.from(eventBuffer.responses.values());
    const css = dom.resources.filter((resource) => resource.name.includes('.css'));
    const js = dom.resources.filter((resource) => resource.name.includes('.js'));
    const duplicates = duplicateUrls(dom.resources.map((resource) => resource.name));
    const asset404s = responses.filter((response) => response.status === 404 && response.type !== 'Document');
    const runtimeErrors = uniqueRuntimeEvents(eventBuffer.errors);
    const runtimeWarnings = uniqueRuntimeEvents(eventBuffer.warnings);

    return {
        id: path.basename(entry.file, '.png'),
        route: entry.route,
        url: dom.url,
        userContext: entry.account.type,
        activeLayout: entry.layout,
        colorTheme: entry.theme,
        viewport: { width: entry.width, height: entry.height },
        openNavigationGroup: entry.openMenu,
        openModalAction: entry.openModalAction,
        modalCount: dom.modal.count,
        visibleModalCount: dom.modal.visibleCount,
        title: dom.title,
        bodyClass: dom.bodyClass,
        rootLayoutClass: dom.rootLayoutClass,
        rootLayoutClassCount: dom.layoutClasses.length,
        darkMode: dom.htmlDark,
        renderer: dom.layout.renderer,
        layoutGeometry: dom.layout,
        activeNavigationItem: dom.navigation.activeItems,
        navigationCount: dom.navigation.count,
        navigationLabels: dom.navigation.labels,
        secondaryNavigationCount: dom.secondaryNavigation,
        horizontalOverflow: dom.overflow.horizontal,
        overflowGeometry: dom.overflow,
        consoleErrorCount: runtimeErrors.length,
        consoleWarningCount: runtimeWarnings.length,
        consoleErrors: runtimeErrors,
        consoleWarnings: runtimeWarnings,
        serverError: dom.serverErrorInDom || (navigationStatus?.status >= 500),
        httpStatus: navigationStatus?.status || null,
        asset404Count: asset404s.length,
        asset404s: asset404s.map((asset) => asset.url),
        failedRequestCount: eventBuffer.failedRequests.length,
        performance: {
            cssAssetCount: new Set(css.map((resource) => resource.name)).size,
            approximateCssBytes: sumBytes(css),
            jsAssetCount: new Set(js.map((resource) => resource.name)).size,
            approximateJsBytes: sumBytes(js),
            duplicateRequestUrls: duplicates,
        },
    };
}

async function takeScreenshot(file) {
    const { data } = await client.send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: false,
        fromSurface: true,
    });
    await writeFile(path.join(screenshotDirectory, file), Buffer.from(data, 'base64'));
}

async function runSpaBaselines() {
    const chains = [
        { layout: 'modern-vertical', first: '/admin/sistem-kullanicilar', second: '/admin/sistem-firmalar' },
        { layout: 'compact-vertical', first: '/admin/sistem-kullanicilar', second: '/admin/sistem-ayarlari' },
        { layout: 'horizontal', first: '/admin/sistem-kullanicilar', second: '/admin/sistem-firmalar' },
    ];

    await ensureAccount(manager);
    await setViewport(1440, 900);
    for (const chain of chains) {
        await ensureLayout(chain.layout);
        await navigate('/admin');
        const marker = `faz4c-${chain.layout}-${Date.now()}`;
        await evaluate(`window.__faz4cSpaMarker = ${JSON.stringify(marker)}`);
        const timeOrigin = await evaluate('performance.timeOrigin');
        const first = await spaClick(chain.first);
        const firstMarker = await evaluate('window.__faz4cSpaMarker || null');
        const firstOrigin = await evaluate('performance.timeOrigin');
        const second = await spaClick(chain.second);
        const secondMarker = await evaluate('window.__faz4cSpaMarker || null');
        const secondOrigin = await evaluate('performance.timeOrigin');
        report.spa.push({
            userContext: 'manager',
            layout: chain.layout,
            chain: ['/admin', chain.first, chain.second],
            firstClick: first,
            secondClick: second,
            markerPreserved: firstMarker === marker && secondMarker === marker,
            timeOriginPreserved: timeOrigin === firstOrigin && firstOrigin === secondOrigin,
            rootClassPreserved: (await currentLayout()) === chain.layout,
        });
    }
}

async function spaClick(route) {
    const targetUrl = `${baseUrl}${route}`;
    const clicked = await evaluate(`(() => {
        const links = Array.from(document.querySelectorAll('.custom-sidebar a[href]'));
        const matches = links.filter((link) => new URL(link.href).pathname === ${JSON.stringify(route)});
        if (matches.length !== 1) return { ok: false, count: matches.length };
        matches[0].click();
        return { ok: true, count: 1 };
    })()`);
    if (!clicked?.ok) return clicked;
    await waitFor(() => currentUrl().then((url) => new URL(url).pathname === route), 15000, `SPA ${route}`);
    await delay(900);
    return { ...clicked, url: targetUrl };
}

async function runPersistenceBaselines() {
    const cases = [
        { account: manager, layout: 'horizontal', spaRoute: '/admin/sistem-kullanicilar' },
        { account: tenant, layout: 'compact-vertical', spaRoute: '/admin/teknik-servis/servis-kayitlari' },
    ];

    for (const testCase of cases) {
        await ensureAccount(testCase.account);
        await setViewport(1440, 900);
        await ensureLayout(testCase.layout);
        await navigate('/admin');
        const hardReloadLayout = await currentLayout();
        const spa = await spaClick(testCase.spaRoute);
        const spaLayout = await currentLayout();

        const otherUserBeforeRelogin = queryLayoutRows().find((row) => row.type !== testCase.account.type);
        await client.send('Network.clearBrowserCookies');
        activeAccountType = null;
        activeLayout = null;
        await ensureAccount(testCase.account);
        const reloginLayout = await currentLayout();
        const ownDatabaseLayout = queryLayoutRows().find((row) => row.type === testCase.account.type)?.layout;
        const otherUserAfterRelogin = queryLayoutRows().find((row) => row.type !== testCase.account.type);

        report.persistence.push({
            userContext: testCase.account.type,
            selectedLayout: testCase.layout,
            hardReloadPreserved: hardReloadLayout === testCase.layout,
            spaNavigation: spa,
            spaPreserved: spaLayout === testCase.layout,
            reloginMethod: 'browser cookie reset + real login form',
            reloginPreserved: reloginLayout === testCase.layout,
            databasePreserved: ownDatabaseLayout === testCase.layout,
            otherUserUnaffected: otherUserBeforeRelogin?.layout === otherUserAfterRelogin?.layout,
        });
    }
}

async function runPublicFrontendChecks() {
    await client.send('Network.clearBrowserCookies');
    activeAccountType = null;
    for (const route of ['/', '/giris', '/yonetici-giris']) {
        await setViewport(1366, 768);
        await navigate(route);
        const data = await evaluate(`(() => ({
            adminLayoutClassCount: Array.from(document.body.classList).filter((name) => name.startsWith('saas-layout-')).length,
            adminLayoutCssLinks: Array.from(document.querySelectorAll('link[rel="stylesheet"]')).filter((link) => /cork-admin-layouts|filament\\/admin\\/theme/i.test(link.href)).map((link) => link.href),
            deepAssetPresent: Array.from(document.querySelectorAll('link, script')).some((element) => /themes\\/deep/i.test(element.src || element.href || '')),
        }))()`);
        report.publicFrontend.push({
            route,
            httpStatus: navigationStatus?.status || null,
            adminLayoutClassCount: data.adminLayoutClassCount,
            adminLayoutCssLinks: data.adminLayoutCssLinks,
            deepAssetPresent: data.deepAssetPresent,
        });
    }
}

function finalizeReport() {
    const snapshots = report.snapshots;
    const navigationAssertions = snapshots.map((entry) => {
        const expectation = expectedNavigation[entry.userContext];
        return {
            id: entry.id,
            countMatches: entry.navigationCount === expectation.count,
            requiredPresent: expectation.present.every((label) => entry.navigationLabels.includes(label)),
            requiredAbsent: expectation.absent.every((label) => !entry.navigationLabels.includes(label)),
        };
    });
    const secondaryOk = snapshots.every((entry) => Object.values(entry.secondaryNavigationCount).every((count) => count === 0));
    const rootClassOk = snapshots.every((entry) => entry.rootLayoutClass === `saas-layout-${entry.activeLayout}` && entry.rootLayoutClassCount === 1);
    const mobileFallbackSnapshots = snapshots.filter((entry) => entry.viewport.width < 1024 && ['compact-vertical', 'horizontal'].includes(entry.activeLayout));
    const desktopGeometryPassed = snapshots.filter((entry) => entry.viewport.width >= 1024).every((entry) => {
        const sidebarWidth = entry.layoutGeometry.sidebar?.width || 0;
        if (entry.activeLayout === 'modern-vertical') return sidebarWidth >= 240 && sidebarWidth <= 270;
        if (entry.activeLayout === 'compact-vertical' && entry.openNavigationGroup) return sidebarWidth >= 240 && sidebarWidth <= 270;
        if (entry.activeLayout === 'compact-vertical') return sidebarWidth >= 60 && sidebarWidth <= 100;
        return sidebarWidth >= entry.viewport.width - 2 && (entry.layoutGeometry.main?.x || 0) === 0;
    });
    const modalSnapshots = snapshots.filter((entry) => entry.openModalAction);

    report.assertions = {
        snapshotCount: snapshots.length,
        screenshotCount: snapshots.filter((entry) => entry.screenshot).length,
        navigation: {
            managerExpected: 16,
            tenantExpected: 12,
            tenantHiddenModules: ['Masraf Takibi', 'Ajanda ve Görevler'],
            passed: navigationAssertions.every((entry) => entry.countMatches && entry.requiredPresent && entry.requiredAbsent),
            details: navigationAssertions,
        },
        secondaryNavigationPassed: secondaryOk,
        rootLayoutClassPassed: rootClassOk,
        desktopGeometryPassed,
        horizontalOverflowPassed: snapshots.every((entry) => !entry.horizontalOverflow),
        modalBaseline: {
            checked: modalSnapshots.length,
            passed: modalSnapshots.length > 0 && modalSnapshots.every((entry) => entry.visibleModalCount > 0),
        },
        mobileFallback: {
            checked: mobileFallbackSnapshots.length,
            preferenceClassPreserved: mobileFallbackSnapshots.every((entry) => entry.rootLayoutClass === `saas-layout-${entry.activeLayout}`),
            verticalDrawerRenderer: mobileFallbackSnapshots.every((entry) => entry.renderer === 'mobile-vertical-drawer' && entry.layoutGeometry.mobileDrawerExists),
            horizontalNavigationHidden: mobileFallbackSnapshots.every((entry) => !entry.layoutGeometry.horizontalNavigationVisible),
        },
        darkLightMatrix: {
            modernLight: hasMatrix('modern-vertical', 'light'),
            compactDark: hasMatrix('compact-vertical', 'dark'),
            horizontalDark: hasMatrix('horizontal', 'dark'),
            horizontalLight: hasMatrix('horizontal', 'light'),
        },
        spaPassed: report.spa.every((entry) => entry.firstClick.ok && entry.secondClick.ok && entry.markerPreserved && entry.timeOriginPreserved && entry.rootClassPreserved),
        persistencePassed: report.persistence.every((entry) => entry.hardReloadPreserved && entry.spaPreserved && entry.reloginPreserved && entry.databasePreserved && entry.otherUserUnaffected),
        publicFrontendPassed: report.publicFrontend.every((entry) => entry.httpStatus === 200 && entry.adminLayoutClassCount === 0 && entry.adminLayoutCssLinks.length === 0),
    };

    const errors = snapshots.flatMap((entry) => entry.consoleErrors.map((error) => ({ snapshot: entry.id, ...error })));
    const warnings = snapshots.flatMap((entry) => entry.consoleWarnings.map((warning) => ({ snapshot: entry.id, ...warning })));
    report.runtime = {
        consoleErrorCount: errors.length,
        consoleWarningCount: warnings.length,
        serverErrorCount: snapshots.filter((entry) => entry.serverError).length,
        http500Count: snapshots.filter((entry) => entry.httpStatus >= 500).length,
        asset404Count: snapshots.reduce((total, entry) => total + entry.asset404Count, 0),
        knownAlpineRuntimeErrors: errors.filter((entry) => /table is not defined|selectFormComponent is not defined|textareaFormComponent is not defined/i.test(entry.text)).length,
        errors,
        warnings,
    };

    const representative = snapshots.filter((entry) => entry.viewport.width >= 1366 && entry.route === '/admin');
    report.performance = {
        method: 'performance resource timing; bytes are transferSize/encodedBodySize approximations',
        representativeSnapshots: representative.map((entry) => ({
            id: entry.id,
            userContext: entry.userContext,
            layout: entry.activeLayout,
            cssAssetCount: entry.performance.cssAssetCount,
            approximateCssBytes: entry.performance.approximateCssBytes,
            jsAssetCount: entry.performance.jsAssetCount,
            approximateJsBytes: entry.performance.approximateJsBytes,
            duplicateRequestUrls: entry.performance.duplicateRequestUrls,
            asset404Count: entry.asset404Count,
        })),
    };
}

function hasMatrix(layout, theme) {
    return report.snapshots.some((entry) => (
        entry.activeLayout === layout
        && entry.colorTheme === theme
        && entry.darkMode === (theme === 'dark')
    ));
}

async function currentLayout() {
    const bodyClass = await evaluate("document.body?.className || ''");
    const match = bodyClass.match(/saas-layout-(modern-vertical|compact-vertical|horizontal)/);
    return match?.[1] || null;
}

async function currentUrl() {
    return evaluate('location.href');
}

async function evaluate(expression) {
    const result = await client.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
        userGesture: true,
    });
    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Runtime evaluate hatası');
    }
    return result.result?.value;
}

async function waitFor(callback, timeoutMs, label) {
    const start = Date.now();
    let lastError;
    while (Date.now() - start < timeoutMs) {
        try {
            if (await callback()) return;
        } catch (error) {
            lastError = error;
        }
        await delay(150);
    }
    throw new Error(`${label} zaman aşımı.${lastError ? ` ${lastError.message}` : ''}`);
}

function delay(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function readInitialLayouts() {
    const rows = queryLayoutRows();
    if (rows.length !== 2) throw new Error('Yönetici ve firma QA kullanıcıları veritabanında bulunamadı.');
    return rows;
}

function queryLayoutRows() {
    const users = [manager, tenant];
    const sql = `SELECT id,kullanici_adi,IFNULL(admin_layout,'__NULL__') FROM users WHERE kullanici_adi IN (${users.map((account) => sqlLiteral(account.user)).join(',')}) ORDER BY id;`;
    const output = runMysql(sql);
    return output.trim().split(/\r?\n/).filter(Boolean).map((line) => {
        const [id, user, layout] = line.split('\t');
        const type = user === manager.user ? 'manager' : 'tenant';
        return { id: Number(id), user, type, layout: layout === '__NULL__' ? null : layout };
    });
}

function restoreInitialLayouts(rows) {
    if (!rows.length) return { ok: false, reason: 'initial-state-unavailable' };
    const statements = rows.map((row) => `UPDATE users SET admin_layout=${row.layout === null ? 'NULL' : sqlLiteral(row.layout)} WHERE id=${Number(row.id)};`).join(' ');
    runMysql(statements);
    const actual = queryLayoutRows();
    const ok = rows.every((row) => actual.some((candidate) => candidate.id === row.id && candidate.layout === row.layout));
    return {
        ok,
        restored: actual.map((row) => ({ id: row.id, type: row.type, layout: row.layout })),
    };
}

function runMysql(sql) {
    const args = [
        '--default-character-set=utf8mb4',
        '-N',
        '-B',
        `-h${process.env.QA_DB_HOST || '127.0.0.1'}`,
        `-P${process.env.QA_DB_PORT || '3306'}`,
        `-u${process.env.QA_DB_USERNAME || 'root'}`,
        process.env.QA_DB_DATABASE || 'yalovayazilimsaas',
        '-e',
        sql,
    ];
    const child = spawnSync(mysqlPath, args, {
        encoding: 'utf8',
        env: { ...process.env, MYSQL_PWD: process.env.QA_DB_PASSWORD || '' },
        windowsHide: true,
    });
    if (child.status !== 0) throw new Error(`MySQL QA işlemi başarısız: ${(child.stderr || '').trim()}`);
    return child.stdout || '';
}

function sqlLiteral(value) {
    return `'${String(value).replaceAll('\\', '\\\\').replaceAll("'", "''")}'`;
}

function duplicateUrls(urls) {
    const counts = new Map();
    urls.forEach((url) => counts.set(url, (counts.get(url) || 0) + 1));
    return Array.from(counts.entries()).filter(([, count]) => count > 1).map(([url, count]) => ({ url, count }));
}

function sumBytes(resources) {
    const unique = new Map();
    for (const resource of resources) {
        unique.set(resource.name, Math.max(unique.get(resource.name) || 0, resource.transferSize || resource.encodedBodySize || 0));
    }
    return Array.from(unique.values()).reduce((total, bytes) => total + bytes, 0);
}

function uniqueRuntimeEvents(events) {
    const unique = new Map();
    for (const event of events) {
        const key = `${event.type}|${event.text}|${event.url || ''}`;
        if (!unique.has(key)) unique.set(key, event);
    }
    return Array.from(unique.values());
}

function sanitizeError(error) {
    return {
        name: error?.name || 'Error',
        message: String(error?.message || error).replace(manager.password, '[REDACTED]').replace(tenant.password, '[REDACTED]'),
        stack: String(error?.stack || '').replaceAll(manager.password, '[REDACTED]').replaceAll(tenant.password, '[REDACTED]'),
    };
}

function isSafeTemporaryPath(target) {
    const resolved = path.resolve(target);
    return resolved.startsWith(`${qaDirectory}${path.sep}`) && path.basename(resolved) === 'tmp';
}
