/* source: admin-panel-overrides.js */
(() => {
        const markLivewireReady = () => {
            document.documentElement.dataset.livewireReady = '1'
        }

        if (window.Livewire) {
            markLivewireReady()
        }

        document.addEventListener('livewire:init', markLivewireReady)
        document.addEventListener('livewire:initialized', markLivewireReady)

        const allowedOverlaySelector = [
            '.fi-main-sidebar',
            '.fi-sidebar',
            '.fi-topbar',
            '.fi-modal',
            '.fi-modal-window',
            '.fi-dropdown-panel',
            '.fi-global-search',
            '[role="dialog"]',
            '[data-filament-modal]',
        ].join(', ')

        const disableUnexpectedViewportBlockers = () => {
            if (window.innerWidth < 1024) {
                return
            }

            const visibleDialog = document.querySelector(
                '.fi-modal:not([style*="display: none"]), [role="dialog"][open]',
            )

            if (visibleDialog) {
                return
            }

            if (document.body) {
                document.body.style.pointerEvents = 'auto'
            }

            document.querySelectorAll('body *').forEach((element) => {
                if (!(element instanceof HTMLElement)) {
                    return
                }

                if (element.matches(allowedOverlaySelector) || element.closest(allowedOverlaySelector)) {
                    return
                }

                const style = window.getComputedStyle(element)
                const rect = element.getBoundingClientRect()
                const coversViewport =
                    rect.width >= (window.innerWidth - 8) &&
                    rect.height >= (window.innerHeight - 8) &&
                    rect.top <= 2 &&
                    rect.left <= 2

                const isBlockingLayer =
                    ['fixed', 'absolute', 'sticky'].includes(style.position) &&
                    coversViewport &&
                    style.pointerEvents !== 'none'

                if (!isBlockingLayer) {
                    return
                }

                element.style.pointerEvents = 'none'
                element.dataset.codexDesktopOverlayFix = '1'
            })
        }

        const disableUnexpectedElementAtPoint = (x, y) => {
            if (window.innerWidth < 1024) {
                return
            }

            const element = document.elementFromPoint(x, y)

            if (!(element instanceof HTMLElement)) {
                return
            }

            if (element.matches(allowedOverlaySelector) || element.closest(allowedOverlaySelector)) {
                return
            }

            const style = window.getComputedStyle(element)
            const rect = element.getBoundingClientRect()
            const likelyBlocker =
                ['fixed', 'absolute', 'sticky'].includes(style.position) &&
                style.pointerEvents !== 'none' &&
                rect.width >= 120 &&
                rect.height >= 120

            if (!likelyBlocker) {
                return
            }

            element.style.pointerEvents = 'none'
            element.dataset.codexDesktopOverlayFix = '1'
        }

        const normalizeFilamentSidebarState = () => {
            try {
                const alpine = window.Alpine

                if (! alpine?.store) {
                    return
                }

                const sidebarStore = alpine.store('sidebar')

                if (! sidebarStore) {
                    return
                }

                if (! Array.isArray(sidebarStore.collapsedGroups)) {
                    sidebarStore.collapsedGroups = []
                }

                if (window.innerWidth >= 1024) {
                    document
                        .querySelectorAll('.fi-sidebar-close-overlay, .fi-modal-close-overlay')
                        .forEach((element) => {
                            element.style.display = 'none'
                            element.style.opacity = '0'
                            element.style.pointerEvents = 'none'
                        })

                    document
                        .querySelectorAll('.fi-main-ctn, .fi-main, .fi-topbar, .fi-main-sidebar')
                        .forEach((element) => {
                            element.style.opacity = '1'
                            element.style.pointerEvents = 'auto'
                        })

                    disableUnexpectedViewportBlockers()
                    disableUnexpectedElementAtPoint(window.innerWidth / 2, 120)
                    disableUnexpectedElementAtPoint(window.innerWidth / 2, window.innerHeight / 2)
                    disableUnexpectedElementAtPoint(window.innerWidth - 80, 120)
                }
            } catch (error) {
                console.warn('Filament sidebar state normalize edilemedi.', error)
            }
        }

        const translateAdminControlLabels = () => {
            document
                .querySelectorAll('[aria-label^="Remove item"], .choices__button')
                .forEach((element) => {
                    const ariaLabel = element.getAttribute('aria-label') || ''
                    const value = ariaLabel.match(/'(.+)'/)?.[1]
                    const translated = value ? `Öğeyi kaldır: '${value}'` : 'Öğeyi kaldır'

                    element.setAttribute('aria-label', translated)

                    if (element.textContent?.trim() === 'Remove item') {
                        element.textContent = 'Öğeyi kaldır'
                    }
                })
        }

        const movePageHeadingToTopbar = () => {
            const topbarNav = document.querySelector('.fi-topbar > nav')
            const topbarEnd = topbarNav?.querySelector('[x-persist^="topbar.end"], .ms-auto')
            const header = document.querySelector('.fi-page > .fi-header, .fi-main .fi-header')

            if (! topbarNav || ! topbarEnd || ! header) {
                return
            }

            const titleBlock = Array.from(header.children).find((element) => {
                return element instanceof HTMLElement && element.querySelector('.fi-header-heading')
            })

            if (! titleBlock) {
                return
            }

            document.querySelectorAll('.yk-topbar-page-title').forEach((element) => {
                if (element !== titleBlock) {
                    element.remove()
                }
            })

            titleBlock.classList.add('yk-topbar-page-title')
            header.classList.add('yk-heading-in-topbar')

            if (titleBlock.parentElement !== topbarNav) {
                topbarNav.insertBefore(titleBlock, topbarEnd)
            }

            header.classList.toggle(
                'yk-header-actions-empty',
                (header.innerText || '').trim() === '',
            )
        }

        let sidebarWidthUpdateFrame = null
        let sidebarMeasureCanvas = null

        const measureSidebarLabelWidth = (element) => {
            const scrollWidth = element.scrollWidth || 0

            if (scrollWidth > 0) {
                return scrollWidth
            }

            const text = element.textContent?.trim() || ''

            if (text === '') {
                return 0
            }

            try {
                sidebarMeasureCanvas ||= document.createElement('canvas')

                const context = sidebarMeasureCanvas.getContext('2d')
                const style = window.getComputedStyle(element)
                context.font = style.font || `${style.fontWeight || 500} ${style.fontSize || '13px'} ${style.fontFamily || 'sans-serif'}`

                return Math.ceil(context.measureText(text).width)
            } catch (error) {
                return Math.ceil(text.length * 7.5)
            }
        }

        const updateSidebarWidth = () => {
            const labels = Array.from(
                document.querySelectorAll('.custom-sidebar .nav-item span:not(.nav-item-start)'),
            ).filter((element) => element instanceof HTMLElement && element.textContent?.trim())

            if (! labels.length) {
                return
            }

            const longestLabelWidth = labels.reduce((width, element) => {
                return Math.max(width, measureSidebarLabelWidth(element))
            }, 0)

            const logoWidth = document.querySelector('.fi-sidebar-header .fi-logo')?.scrollWidth || 0
            const viewportWidth = window.visualViewport?.width || window.innerWidth
            const isMobileSidebar = viewportWidth < 1024
            const minWidth = isMobileSidebar ? Math.max(208, logoWidth + 72) : Math.max(216, logoWidth + 84)
            const maxWidth = isMobileSidebar ? Math.min(280, viewportWidth - 16) : 288
            const targetWidth = Math.ceil(Math.min(maxWidth, Math.max(minWidth, longestLabelWidth + (isMobileSidebar ? 76 : 84))))

            if (document.documentElement.style.getPropertyValue('--yk-admin-sidebar-width') === `${targetWidth}px`) {
                return
            }

            document.documentElement.style.setProperty(
                '--yk-admin-sidebar-width',
                `${targetWidth}px`,
            )
        }

        const scheduleSidebarWidthUpdate = () => {
            if (sidebarWidthUpdateFrame !== null) {
                return
            }

            sidebarWidthUpdateFrame = window.requestAnimationFrame(() => {
                sidebarWidthUpdateFrame = null
                updateSidebarWidth()
            })
        }

        const clearSidebarLogoInlineVisibility = () => {
            document
                .querySelectorAll('.fi-sidebar-header .fi-logo, .fi-sidebar-header a:has(.fi-logo)')
                .forEach((element) => {
                    element.style.removeProperty('display')
                    element.style.removeProperty('opacity')
                    element.style.removeProperty('visibility')
                    element.style.removeProperty('pointer-events')
                })
        }

        const enhanceGlobalSearch = () => {
            const search = document.querySelector('.fi-global-search')
            const input = search?.querySelector('input[type="search"]')

            if (! (search instanceof HTMLElement) || ! (input instanceof HTMLInputElement)) {
                return
            }

            search.classList.toggle('yk-global-search-has-value', input.value.trim() !== '')

            if (search.dataset.ykEnhanced === '1') {
                return
            }

            search.dataset.ykEnhanced = '1'

            search.addEventListener('pointerdown', (event) => {
                search.classList.add('yk-global-search-open')
                requestAnimationFrame(() => input.focus())
            })

            input.addEventListener('focus', () => {
                search.classList.add('yk-global-search-open')
            })

            input.addEventListener('input', () => {
                search.classList.toggle('yk-global-search-has-value', input.value.trim() !== '')
            })

            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return
                }

                search.classList.remove('yk-global-search-open')

                if (input.value.trim() === '') {
                    input.blur()
                }
            })

            document.addEventListener('pointerdown', (event) => {
                if (search.contains(event.target)) {
                    return
                }

                search.classList.remove('yk-global-search-open')
            })
        }

        const bindGlobalSearchDelegates = () => {
            if (window.__ykGlobalSearchDelegatesBound) {
                return
            }

            window.__ykGlobalSearchDelegatesBound = true

            document.addEventListener('pointerdown', (event) => {
                const search = event.target?.closest?.('.fi-global-search')

                if (! search) {
                    return
                }

                const input = search.querySelector('input[type="search"]')

                search.classList.add('yk-global-search-open')
                requestAnimationFrame(() => input?.focus())
            }, true)

            document.addEventListener('focusin', (event) => {
                const search = event.target?.closest?.('.fi-global-search')

                if (! search) {
                    return
                }

                search.classList.add('yk-global-search-open')
            })
        }

        const teknikServisLazySelectStatePaths = new Set([
            'data.cari_id',
            'data.cihaz_id',
            'data.marka_id',
            'data.aksesuarlar',
            'data.arizalar',
        ])

        const isTeknikServisLazySelectStatePath = (statePath) => {
            return teknikServisLazySelectStatePaths.has(statePath)
                || /^data\.kalemler\.[^.]+\.stok_id$/.test(statePath || '')
        }

        const bindTeknikServisLazySelects = () => {
            // Bu ön yükleme yalnız Teknik Servis formları içindir. Fatura kalemi
            // seçicileri de `data.kalemler.*.stok_id` kullandığından sayfa kapsamı
            // olmadan çalışan dinleyici, kullanıcının arama sonucunu ilk seçeneklerle
            // ezebiliyordu.
            if (! window.location.pathname.includes('/admin/teknik-servis/')) {
                return
            }

            if (window.__ykTeknikServisLazySelectsBound) {
                return
            }

            window.__ykTeknikServisLazySelectsBound = true

            document.addEventListener('showDropdown', async (event) => {
                const root = event.target?.closest?.('[x-data*="selectFormComponent"]')
                const statePath = root
                    ?.getAttribute('x-data')
                    ?.match(/statePath: '([^']+)'/)?.[1]

                if (! root || ! isTeknikServisLazySelectStatePath(statePath)) {
                    return
                }

                if (root.dataset.ykLazyOptionsLoaded === '1' || root.dataset.ykLazyOptionsLoading === '1') {
                    return
                }

                const alpineData = window.Alpine?.$data?.(root)

                if (! alpineData?.refreshChoices || ! alpineData?.select) {
                    return
                }

                root.dataset.ykLazyOptionsLoading = '1'

                try {
                    alpineData.select.clearChoices()
                    await alpineData.select.setChoices([
                        {
                            label: 'Yükleniyor...',
                            value: '',
                            disabled: true,
                        },
                    ], 'value', 'label', true)

                    await alpineData.refreshChoices({ search: ' ' })

                    root.dataset.ykLazyOptionsLoaded = '1'
                } catch (error) {
                    console.warn('Teknik servis seçenekleri açılışta yüklenemedi.', error)
                } finally {
                    delete root.dataset.ykLazyOptionsLoading
                }
            }, true)
        }

        const setStyleIfChanged = (element, styles) => {
            if (! element) {
                return
            }

            Object.entries(styles).forEach(([property, value]) => {
                if (element.style[property] !== value) {
                    element.style[property] = value
                }
            })
        }

        const compactTeknikServisCihazGorselleri = () => {
            if (! window.location.pathname.includes('teknik-servis/servis-kayitlari')) {
                return
            }

            const size = '5.6rem'

            document.querySelectorAll('.teknik-servis-cihaz-gorselleri-alani').forEach((area) => {
                area.querySelectorAll('.filepond-uploader, .filepond--root').forEach((element) => {
                    setStyleIfChanged(element, {
                        height: 'auto',
                        minHeight: '6.1rem',
                        maxHeight: 'none',
                        overflow: 'visible',
                    })
                })

                area.querySelectorAll('.filepond--list-scroller').forEach((element) => {
                    setStyleIfChanged(element, {
                        position: 'relative',
                        inset: 'auto',
                        transform: 'none',
                        height: 'auto',
                        minHeight: '5.7rem',
                        overflow: 'visible',
                    })
                })

                area.querySelectorAll('.filepond--list').forEach((element) => {
                    setStyleIfChanged(element, {
                        position: 'relative',
                        inset: 'auto',
                        display: 'flex',
                        flexWrap: 'wrap',
                        alignItems: 'flex-start',
                        justifyContent: 'flex-start',
                        gap: '0.4rem',
                        minHeight: '5.7rem',
                        transform: 'none',
                    })
                })

                area.querySelectorAll('li.filepond--item, .filepond--item').forEach((element) => {
                    setStyleIfChanged(element, {
                        position: 'relative',
                        inset: 'auto',
                        left: 'auto',
                        right: 'auto',
                        top: 'auto',
                        bottom: 'auto',
                        transform: 'none',
                        flex: `0 0 ${size}`,
                        width: size,
                        minWidth: size,
                        maxWidth: size,
                        height: size,
                        minHeight: size,
                        maxHeight: size,
                        margin: '0px',
                        overflow: 'hidden',
                        borderRadius: '0.55rem',
                    })
                })

                area.querySelectorAll('fieldset.filepond--file-wrapper, .filepond--file-wrapper, .filepond--file, .filepond--item-panel, .filepond--panel, .filepond--panel-root, .filepond--panel-top, .filepond--panel-center, .filepond--panel-bottom, .filepond--image-preview-wrapper, .filepond--image-preview').forEach((element) => {
                    setStyleIfChanged(element, {
                        width: size,
                        minWidth: size,
                        maxWidth: size,
                        height: size,
                        minHeight: size,
                        maxHeight: size,
                        overflow: 'hidden',
                        borderRadius: '0.55rem',
                        transform: 'none',
                    })
                })

                area.querySelectorAll('.filepond--item-panel').forEach((element) => {
                    setStyleIfChanged(element, {
                        display: 'none',
                    })
                })

                area.querySelectorAll('.filepond--file-info, .filepond--file-status, .filepond--file-info-main, .filepond--file-info-sub, .filepond--file-status-main, .filepond--file-status-sub').forEach((element) => {
                    setStyleIfChanged(element, {
                        display: 'none',
                    })
                })
            })
        }

        const closeFilamentNotification = (source) => {
            const notification = source?.closest?.('.fi-no-notification')

            if (! notification) {
                return
            }

            const alpineData = window.Alpine?.$data?.(notification)

            if (typeof alpineData?.close === 'function') {
                alpineData.close()

                return
            }

            notification.style.display = 'none'
            notification.remove()
        }

        const bindFilamentNotificationCloseFallbacks = () => {
            if (window.__ykFilamentNotificationCloseFallbacksBound) {
                return
            }

            window.__ykFilamentNotificationCloseFallbacksBound = true

            window.addEventListener('yk-close-filament-notification', (event) => {
                closeFilamentNotification(event.detail?.source)
            })

            document.addEventListener('click', (event) => {
                const target = event.target

                if (! target?.closest) {
                    return
                }

                const closeButton = target.closest('.fi-no-notification-close-btn')

                if (! closeButton) {
                    return
                }

                setTimeout(() => closeFilamentNotification(closeButton), 0)
            }, true)
        }

        const refreshAdminOverrides = () => {
            bindGlobalSearchDelegates()
            bindTeknikServisLazySelects()
            bindFilamentNotificationCloseFallbacks()
            compactTeknikServisCihazGorselleri()
            normalizeFilamentSidebarState()
            translateAdminControlLabels()
            movePageHeadingToTopbar()
            scheduleSidebarWidthUpdate()
            clearSidebarLogoInlineVisibility()
            enhanceGlobalSearch()
        }

        let refreshAdminOverridesTimer = null

        const scheduleRefreshAdminOverrides = () => {
            if (refreshAdminOverridesTimer !== null) {
                return
            }

            refreshAdminOverridesTimer = window.setTimeout(() => {
                refreshAdminOverridesTimer = null
                refreshAdminOverrides()
            }, 80)
        }

        bindTeknikServisLazySelects()

        document.addEventListener('alpine:init', scheduleRefreshAdminOverrides)
        document.addEventListener('livewire:navigated', scheduleRefreshAdminOverrides)
        window.addEventListener('load', scheduleRefreshAdminOverrides)
        window.addEventListener('resize', scheduleRefreshAdminOverrides)

        const observer = new MutationObserver(() => {
            scheduleRefreshAdminOverrides()
        })

        window.addEventListener('load', () => {
            observer.observe(document.body, {
                childList: true,
                subtree: true,
            })
        })

        setTimeout(scheduleRefreshAdminOverrides, 300)
        setTimeout(scheduleRefreshAdminOverrides, 1200)
    })()

/* source: admin-custom-sidebar.js */
if (! window.__ykCustomSidebarNavigateBound) {
        window.__ykCustomSidebarNavigateBound = true

        const compactSidebarQuery = window.matchMedia('(max-width: 1023px)')

        const isCompactSidebarViewport = () => {
            const viewportWidth = window.visualViewport?.width || window.innerWidth

            return compactSidebarQuery.matches || viewportWidth <= 1023
        }

        const getSidebarStore = () => {
            try {
                return window.Alpine?.store?.('sidebar') || null
            } catch (error) {
                return null
            }
        }

        const isNonSpaMuhasebeUrl = (url) => /\/muhasebe\/satis\/barkodlu-satis(?:-iade)?-fisi(?:\/|$)/.test(url.pathname)

        const normalizePath = (path) => path.replace(/\/+$/, '') || '/'

        const refreshActiveSidebarLinks = () => {
            const sidebar = document.querySelector('.custom-sidebar')

            if (! sidebar) {
                return
            }

            const currentPath = normalizePath(window.location.pathname)

            sidebar.querySelectorAll('a.nav-item[href]').forEach((link) => {
                const linkPath = normalizePath(new URL(link.href, window.location.href).pathname)
                const isActive = currentPath === linkPath || currentPath.startsWith(`${linkPath}/`)

                link.classList.toggle('is-active', isActive)
            })

            sidebar.querySelectorAll('button.nav-item').forEach((button) => {
                const submenu = button.nextElementSibling
                const hasActiveChild = Boolean(submenu?.querySelector?.('a.nav-item.is-active'))

                button.classList.toggle('is-active', hasActiveChild)
            })
        }

        const closeSidebarOnCompactViewport = () => {
            if (! isCompactSidebarViewport()) {
                return
            }

            const sidebarStore = getSidebarStore()

            if (! sidebarStore?.isOpen) {
                return
            }

            if (typeof sidebarStore.close === 'function') {
                sidebarStore.close()

                return
            }

            sidebarStore.isOpen = false
        }

        const scheduleCompactSidebarClose = () => {
            requestAnimationFrame(closeSidebarOnCompactViewport)
            setTimeout(closeSidebarOnCompactViewport, 120)
        }

        const refreshSidebarState = () => {
            refreshActiveSidebarLinks()
            scheduleCompactSidebarClose()
        }

        document.addEventListener('alpine:init', refreshSidebarState)
        document.addEventListener('livewire:navigated', refreshSidebarState)
        window.addEventListener('load', refreshSidebarState)
        window.addEventListener('resize', scheduleCompactSidebarClose)
        window.visualViewport?.addEventListener('resize', scheduleCompactSidebarClose)

        document.addEventListener('click', (event) => {
            const link = event.target?.closest?.('.custom-sidebar a[href]')

            if (! link) {
                return
            }

            closeSidebarOnCompactViewport()

            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                link.target === '_blank' ||
                link.hasAttribute('download') ||
                link.hasAttribute('wire:navigate') ||
                link.hasAttribute('data-navigate')
            ) {
                return
            }

            const url = new URL(link.href, window.location.href)

            if (
                url.origin !== window.location.origin ||
                isNonSpaMuhasebeUrl(url) ||
                typeof window.Livewire?.navigate !== 'function'
            ) {
                return
            }

            event.preventDefault()
            window.Livewire.navigate(link.href)
        })
    }
