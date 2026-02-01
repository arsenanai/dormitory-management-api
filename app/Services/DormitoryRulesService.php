<?php

declare(strict_types=1);

namespace App\Services;

final class DormitoryRulesService
{
    private const CONFIG_KEY = 'dormitory_rules';

    private const SUPPORTED_LOCALES = [ 'en', 'kk', 'ru' ];

    public function __construct(
        private readonly ConfigurationService $configuration,
    ) {
    }

    /**
     * Get dormitory rules for all locales.
     *
     * @return array<string, string>
     */
    public function getLocales(): array
    {
        $data = $this->configuration->getConfiguration(self::CONFIG_KEY);

        // Backward compatibility: if stored as plain string, treat as English
        if (is_string($data)) {
            return [
                'en' => $data ?: '',
                'kk' => '',
                'ru' => '',
            ];
        }

        if (! is_array($data)) {
            return $this->defaultLocales();
        }

        $out = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $out[$locale] = isset($data[$locale]) && is_string($data[$locale])
                ? $data[$locale]
                : '';
        }

        return $out;
    }

    /**
     * Get rules for a single locale (with fallback to en).
     */
    public function getForLocale(string $locale): string
    {
        $locales = $this->getLocales();
        $value = $locales[$locale] ?? $locales['en'] ?? '';

        return $value !== '' ? $value : ($locales['en'] ?? '');
    }

    /**
     * Update dormitory rules for all locales.
     *
     * @param  array<string, string>  $locales
     */
    public function updateLocales(array $locales): void
    {
        $existing = $this->getLocales();
        foreach (self::SUPPORTED_LOCALES as $locale) {
            if (isset($locales[$locale]) && is_string($locales[$locale])) {
                $existing[$locale] = $locales[$locale];
            }
        }
        $this->configuration->setConfiguration(
            self::CONFIG_KEY,
            $existing,
            'json',
            'Dormitory Rules and Regulations'
        );
    }

    /**
     * @return array<string, string>
     */
    private function defaultLocales(): array
    {
        return [
            'en' => '',
            'kk' => '',
            'ru' => '',
        ];
    }
}
