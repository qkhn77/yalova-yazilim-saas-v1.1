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
                const isHomeLink = linkPath === '/admin'
                const isActive = isHomeLink
                    ? currentPath === linkPath
                    : currentPath === linkPath || currentPath.startsWith(`${linkPath}/`)

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
