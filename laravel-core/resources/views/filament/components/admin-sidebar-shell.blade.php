<div
    x-cloak
    x-data="{}"
    x-on:click="$store.sidebar.close()"
    x-show="$store.sidebar.isOpen"
    x-transition.opacity.300ms
    class="fi-sidebar-close-overlay fixed inset-0 z-30 bg-gray-950/50 transition duration-500 dark:bg-gray-950/75 lg:hidden"
></div>

<x-filament-panels::sidebar
    :navigation="$navigation"
    class="fi-main-sidebar shrink-0 self-stretch"
/>

<script>
    (() => {
        const scrollActiveAdminNavigationItem = () => {
            if (document.body.classList.contains('saas-layout-horizontal')) {
                return
            }

            let activeSidebarItem = document.querySelector(
                '.fi-main-sidebar .custom-sidebar > nav > .nav-item.is-active',
            )

            if (!activeSidebarItem || activeSidebarItem.offsetParent === null) {
                activeSidebarItem = document.querySelector(
                    '.fi-main-sidebar .custom-sidebar .nav-group .nav-item.is-active',
                )
            }

            if (!activeSidebarItem || activeSidebarItem.offsetParent === null) {
                return
            }

            const sidebarWrapper = document.querySelector(
                '.fi-main-sidebar .fi-sidebar-nav',
            )

            if (!sidebarWrapper) {
                return
            }

            sidebarWrapper.scrollTo(
                0,
                activeSidebarItem.offsetTop - window.innerHeight / 2,
            )
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scrollActiveAdminNavigationItem, { once: true })
        } else {
            scrollActiveAdminNavigationItem()
        }

        document.addEventListener('livewire:navigated', scrollActiveAdminNavigationItem)
    })()
</script>
