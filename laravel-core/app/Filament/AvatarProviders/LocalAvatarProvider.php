<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class LocalAvatarProvider implements AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        $initialler = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $parca): string => filled($parca) ? mb_substr($parca, 0, 1) : '')
            ->join(' ');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">'
            .'<rect width="40" height="40" rx="20" fill="#09090b"/>'
            .'<text x="20" y="21" fill="#ffffff" font-family="Arial, sans-serif" font-size="13" font-weight="600" text-anchor="middle" dominant-baseline="middle">'
            .htmlspecialchars($initialler, ENT_QUOTES | ENT_XML1, 'UTF-8')
            .'</text></svg>';

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }
}
