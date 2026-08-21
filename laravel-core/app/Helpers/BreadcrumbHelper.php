<?php

namespace App\Helpers;

class BreadcrumbHelper
{
    private static $breadcrumbs = [];

    public static function add($title, $url = null)
    {
        self::$breadcrumbs[] = [
            'title' => $title,
            'url' => $url,
        ];
    }

    public static function set(array $breadcrumbs)
    {
        self::$breadcrumbs = $breadcrumbs;
    }

    public static function get()
    {
        return self::$breadcrumbs;
    }

    public static function render()
    {
        if (empty(self::$breadcrumbs)) {
            return '';
        }

        $html = '<nav class="wow fadeInUp" data-wow-delay="0.2s"><ol class="breadcrumb">';

        foreach (self::$breadcrumbs as $index => $crumb) {
            if ($index === count(self::$breadcrumbs) - 1) {
                // Son öğe, aktif
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . e($crumb['title']) . '</li>';
            } else {
                // Link: göreli path'leri (/, /foo) uygulama base URL ile birleştir
                $href = $crumb['url'] ?? '#';
                if ($href !== '#' && $href !== '' && !str_starts_with($href, 'http://') && !str_starts_with($href, 'https://')) {
                    $href = url($href);
                }
                $html .= '<li class="breadcrumb-item"><a href="' . e($href) . '">' . e($crumb['title']) . '</a></li>';
            }
        }

        $html .= '</ol></nav>';

        return $html;
    }

    public static function clear()
    {
        self::$breadcrumbs = [];
    }
}