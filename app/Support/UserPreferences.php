<?php

namespace App\Support;

use App\Models\User;

/**
 * Options, defaults and resolution for the per-user Settings page.
 * theme / language / timezone are columns on users; everything else lives
 * in the users.preferences JSON column.
 */
class UserPreferences
{
    public const THEMES = [
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ];

    /** Accent presets: each sets the --brand-* variables brand-theme.css is built on. */
    public const ACCENTS = [
        'orange' => ['label' => 'Orange', 'main' => '#f7924a', 'dark' => '#e57a2e', 'end' => '#fdc830', 'rgb' => '247, 146, 74', 'text' => '#b45309', 'soft' => '#fff3d6', 'soft_strong' => '#fde7b8'],
        'blue' => ['label' => 'Blue', 'main' => '#3b82f6', 'dark' => '#2563eb', 'end' => '#60a5fa', 'rgb' => '59, 130, 246', 'text' => '#1d4ed8', 'soft' => '#e8f0fe', 'soft_strong' => '#cfe0fd'],
        'teal' => ['label' => 'Teal', 'main' => '#14b8a6', 'dark' => '#0f9488', 'end' => '#5eead4', 'rgb' => '20, 184, 166', 'text' => '#0f766e', 'soft' => '#e0f7f4', 'soft_strong' => '#bdeee7'],
        'green' => ['label' => 'Green', 'main' => '#22c55e', 'dark' => '#16a34a', 'end' => '#86efac', 'rgb' => '34, 197, 94', 'text' => '#15803d', 'soft' => '#e6f8ec', 'soft_strong' => '#c6efd4'],
        'purple' => ['label' => 'Purple', 'main' => '#8b5cf6', 'dark' => '#7c3aed', 'end' => '#c4b5fd', 'rgb' => '139, 92, 246', 'text' => '#6d28d9', 'soft' => '#f1ebfe', 'soft_strong' => '#e0d4fd'],
        'rose' => ['label' => 'Rose', 'main' => '#f43f5e', 'dark' => '#e11d48', 'end' => '#fb7185', 'rgb' => '244, 63, 94', 'text' => '#be123c', 'soft' => '#fde8ec', 'soft_strong' => '#fbcfd8'],
    ];

    /** Root font size: every theme size is in rem, so this scales the whole UI. */
    public const FONT_SIZES = [
        'small' => ['label' => 'Small', 'root' => '93.75%'],
        'default' => ['label' => 'Default', 'root' => null],
        'large' => ['label' => 'Large', 'root' => '106.25%'],
    ];

    public const DENSITIES = [
        'comfortable' => 'Comfortable',
        'compact' => 'Compact',
    ];

    public const PAGE_SIZES = [10, 25, 50, 100];

    public const DEFAULTS = [
        'theme' => 'light',
        'accent' => 'orange',
        'font_size' => 'default',
        'density' => 'comfortable',
        'reduced_motion' => false,
        'page_size' => 10,
        'timezone' => null,
    ];

    /** Resolved settings for a user (defaults for guests and unset values). */
    public static function for(?User $user): array
    {
        if (!$user) {
            return self::DEFAULTS;
        }

        $stored = is_array($user->preferences) ? $user->preferences : [];
        $prefs = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        $prefs['theme'] = self::normaliseTheme($user->theme);
        $prefs['timezone'] = $user->timezone ?: null;

        // Fall back to defaults for any value no longer offered.
        if (!isset(self::ACCENTS[$prefs['accent']])) $prefs['accent'] = self::DEFAULTS['accent'];
        if (!isset(self::FONT_SIZES[$prefs['font_size']])) $prefs['font_size'] = self::DEFAULTS['font_size'];
        if (!isset(self::DENSITIES[$prefs['density']])) $prefs['density'] = self::DEFAULTS['density'];
        if (!in_array((int) $prefs['page_size'], self::PAGE_SIZES, true)) $prefs['page_size'] = self::DEFAULTS['page_size'];
        $prefs['page_size'] = (int) $prefs['page_size'];
        $prefs['reduced_motion'] = (bool) $prefs['reduced_motion'];

        return $prefs;
    }

    /** The old theme switcher stored light-skin / dark-skin. */
    public static function normaliseTheme(?string $theme): string
    {
        return match ($theme) {
            'dark', 'dark-skin' => 'dark',
            'system' => 'system',
            default => 'light',
        };
    }

    /** Inline CSS variables for the chosen accent (empty for the default orange). */
    public static function accentCss(string $accent): string
    {
        if ($accent === self::DEFAULTS['accent'] || !isset(self::ACCENTS[$accent])) {
            return '';
        }
        $a = self::ACCENTS[$accent];

        return "--brand-orange:{$a['main']};--brand-orange-dark:{$a['dark']};--brand-yellow:{$a['end']};"
            . "--brand-orange-rgb:{$a['rgb']};--brand-text:{$a['text']};--brand-soft:{$a['soft']};"
            . "--brand-soft-strong:{$a['soft_strong']};--brand-gradient:linear-gradient(90deg, {$a['main']} 0%, {$a['end']} 100%);";
    }

    /** Time zones grouped by region for the select box. */
    public static function timezones(): array
    {
        $groups = [];
        foreach (\DateTimeZone::listIdentifiers() as $zone) {
            $region = str_contains($zone, '/') ? strstr($zone, '/', true) : 'Other';
            $offset = (new \DateTime('now', new \DateTimeZone($zone)))->format('P');
            $groups[$region][$zone] = '(UTC' . $offset . ') ' . str_replace('_', ' ', $zone);
        }
        return $groups;
    }
}
