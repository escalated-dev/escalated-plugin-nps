<?php

namespace Escalated\Plugins\Nps\Support;

class Config
{
    const VERSION = '0.1.0';
    const SLUG = 'nps';
    const CONFIG_DIR = __DIR__ . '/../config';
    const CONFIG_FILE = self::CONFIG_DIR . '/settings.json';
    const RESPONSES_FILE = self::CONFIG_DIR . '/responses.json';
    const PENDING_SURVEYS_FILE = self::CONFIG_DIR . '/pending_surveys.json';

    /**
     * Return the default survey configuration.
     *
     * Structure:
     *   question             - The NPS question text
     *   follow_up_question   - Follow-up question shown after score selection
     *   trigger_delay_hours  - Hours to wait after ticket resolution before sending
     *   frequency_limit_days - Minimum days between surveys for the same contact
     *   branding             - { primary_color, logo_url }
     *   enabled              - Whether the NPS system is active
     */
    public static function defaults(): array
    {
        return [
            'question'             => 'How likely are you to recommend us to a friend or colleague?',
            'follow_up_question'   => 'What is the main reason for your score?',
            'trigger_delay_hours'  => 24,
            'frequency_limit_days' => 90,
            'branding'             => [
                'primary_color' => '#3b82f6',
                'logo_url'      => '',
            ],
            'enabled'              => true,
        ];
    }

    /**
     * Read the current NPS configuration.
     */
    public static function all(): array
    {
        if (!file_exists(self::CONFIG_FILE)) {
            return self::defaults();
        }

        $json = file_get_contents(self::CONFIG_FILE);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return self::defaults();
        }

        return array_replace_recursive(self::defaults(), $data);
    }

    /**
     * Retrieve a single configuration value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    /**
     * Persist NPS configuration.
     */
    public static function save(array $config): bool
    {
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0755, true);
        }

        $config = array_replace_recursive(self::defaults(), $config);
        $json   = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents(self::CONFIG_FILE, $json, LOCK_EX) !== false;
    }

    /**
     * Activation hook: ensure config directory and default files exist.
     */
    public static function onActivate(): void
    {
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0755, true);
        }

        if (!file_exists(self::CONFIG_FILE)) {
            self::save(self::defaults());
        }

        if (!file_exists(self::RESPONSES_FILE)) {
            self::writeJsonFile(self::RESPONSES_FILE, []);
        }

        if (!file_exists(self::PENDING_SURVEYS_FILE)) {
            self::writeJsonFile(self::PENDING_SURVEYS_FILE, []);
        }

        if (function_exists('escalated_update_option')) {
            escalated_update_option('nps_plugin_version', self::VERSION);
        }
    }

    /**
     * Deactivation hook: preserve data, broadcast event.
     */
    public static function onDeactivate(): void
    {
        if (function_exists('escalated_broadcast')) {
            escalated_broadcast('admin', 'nps.deactivated', [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        }
    }

    /**
     * Write a JSON array to a file inside the config directory.
     */
    private static function writeJsonFile(string $path, array $data): bool
    {
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0755, true);
        }

        $json = json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return file_put_contents($path, $json, LOCK_EX) !== false;
    }
}
