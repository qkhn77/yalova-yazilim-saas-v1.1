<?php

namespace App\Filament\Clusters\Web\Pages;

use App\Filament\Clusters\Web;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

abstract class BaseModulSectionEditor extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static ?string $cluster = Web::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.clusters.web.pages.modul-editor';

    public ?array $data = [];

    abstract protected static function getModuleKey(): string;

    abstract protected function getDefaultData(): array;

    abstract protected function getEditorSchema(): array;

    /**
     * @return array<string, string>
     */
    protected function getDefaultImageMap(): array
    {
        return [];
    }

    public function mount(): void
    {
        $defaults = array_replace($this->getDefaultData(), $this->ensureDefaultImages());
        $state = [];

        foreach ($defaults as $key => $defaultValue) {
            $storedValue = Setting::get($this->settingKey($key), null);
            $state[$key] = ($storedValue === null || $storedValue === '') ? $defaultValue : $storedValue;
        }

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        FileUpload::configureUsing(function (FileUpload $component): void {
            $component
                ->panelLayout('compact')
                ->imagePreviewHeight('120')
                ->extraAttributes([
                    'style' => 'max-width: 340px;',
                ]);
        });

        return $form
            ->statePath('data')
            ->schema($this->getEditorSchema())
            ->columns(1);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($this->settingKey($key), $value ?? '', 'web_moduller');
        }

        Notification::make()
            ->title('Modül içeriği kaydedildi.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    protected function settingKey(string $field): string
    {
        return 'modul.' . static::getModuleKey() . '.' . $field;
    }

    /**
     * @return array<string, string>
     */
    protected function ensureDefaultImages(): array
    {
        return Cache::remember('web_modul_default_images:'.static::getModuleKey(), now()->addMinutes(10), function (): array {
            return $this->resolveDefaultImages();
        });
    }

    /**
     * @return array<string, string>
     */
    private function resolveDefaultImages(): array
    {
        $resolved = [];

        foreach ($this->getDefaultImageMap() as $field => $relativeSourcePath) {
            $sourcePath = public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeSourcePath));

            if (! File::exists($sourcePath)) {
                continue;
            }

            $filename = basename($sourcePath);
            $targetRelativePath = 'defaults/moduller/' . static::getModuleKey() . '/' . $filename;
            $targetAbsolutePath = Storage::disk('public')->path($targetRelativePath);

            if (! File::exists($targetAbsolutePath)) {
                File::ensureDirectoryExists(dirname($targetAbsolutePath));
                File::copy($sourcePath, $targetAbsolutePath);
            }

            $resolved[$field] = $targetRelativePath;
        }

        return $resolved;
    }
}

