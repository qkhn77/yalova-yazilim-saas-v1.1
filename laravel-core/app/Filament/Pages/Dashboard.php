<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\KiraciOzetWidget;
use App\Filament\Widgets\SistemYonetimiOzetWidget;
use App\Services\TenantContextService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * @return array<class-string<Widget>|WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        if (app(TenantContextService::class)->aktifFirmaId() !== null) {
            return [
                KiraciOzetWidget::class,
            ];
        }

        return [
            SistemYonetimiOzetWidget::class,
        ];
    }
}
