<x-filament-panels::header
    :actions="[]"
    :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
    :heading="$this->getHeading()"
    :subheading="$this->getSubheading()"
/> 
