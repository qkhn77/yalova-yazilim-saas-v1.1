@props([
    'user' => filament()->auth()->user(),
])

@php($avatarUrl = filament()->getUserAvatarUrl($user) ?: asset('images/default-avatar.png'))

<x-filament::avatar
    :src="$avatarUrl"
    :alt="__('filament-panels::layout.avatar.alt', ['name' => filament()->getUserName($user)])"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-avatar'])
    "
/>
