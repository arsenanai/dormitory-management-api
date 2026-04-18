<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dormitory;
use App\Models\User;

final class MailTemplateService
{
    private const KEY_PREFIX = 'mail_template_';

    public function __construct(
        private readonly ConfigurationService $configuration,
    ) {
    }

    /**
     * Get template for a mail type and locale. Returns null if not set (caller should fallback).
     *
     * @return array{subject: string, body: string}|null
     */
    public function getTemplate(string $type, string $locale): ?array
    {
        $key = $this->configKey($type);
        $data = $this->configuration->getConfiguration($key);
        if (! is_array($data) || ! isset($data[$locale]) || ! is_array($data[$locale])) {
            return null;
        }
        $loc = $data[$locale];
        if (! isset($loc['subject'], $loc['body'])) {
            return null;
        }
        return [
            'subject' => (string) $loc['subject'],
            'body'    => (string) $loc['body'],
        ];
    }

    /**
     * Get all templates for a mail type (all locales).
     *
     * @return array<string, array{subject: string, body: string}>
     */
    public function getTemplateByType(string $type): array
    {
        $key = $this->configKey($type);
        $data = $this->configuration->getConfiguration($key);
        if (! is_array($data)) {
            return [];
        }
        $locales = config('mail_templates.supported_locales', [ 'en', 'kk', 'ru' ]);
        $out = [];
        foreach ($locales as $locale) {
            if (isset($data[$locale]) && is_array($data[$locale]) && isset($data[$locale]['subject'], $data[$locale]['body'])) {
                $out[$locale] = [
                    'subject' => (string) $data[$locale]['subject'],
                    'body'    => (string) $data[$locale]['body'],
                ];
            }
        }
        return $out;
    }

    /**
     * List all mail types with their templates (for index page).
     *
     * @return array<int, array{type: string, name: string, locales: array<string, array{subject: string, body: string}>}>
     */
    public function listAll(): array
    {
        $types = array_keys(config('mail_templates.types', []));
        $result = [];
        foreach ($types as $type) {
            $result[] = [
                'type'   => $type,
                'name'   => (string) (config("mail_templates.types.{$type}") ?? $type),
                'locales' => $this->getTemplateByType($type),
            ];
        }
        return $result;
    }

    /**
     * Update template for a mail type (all locales).
     *
     * @param  array<string, array{subject: string, body: string}>  $locales
     */
    public function updateTemplate(string $type, array $locales): void
    {
        $key = $this->configKey($type);
        $existing = $this->configuration->getConfiguration($key);
        if (! is_array($existing)) {
            $existing = [];
        }
        foreach ($locales as $locale => $data) {
            if (isset($data['subject'], $data['body'])) {
                $existing[$locale] = [
                    'subject' => (string) $data['subject'],
                    'body'    => (string) $data['body'],
                ];
            }
        }
        $this->configuration->setConfiguration($key, $existing, 'json', 'Mail template: ' . $type);
    }

    /**
     * Resolve placeholders in subject or body.
     *
     * @param  array<string, string|int|null>  $context
     */
    public function resolvePlaceholders(string $text, array $context): string
    {
        foreach ($context as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $text);
        }
        return $text;
    }

    /**
     * Build context for user.registered (for placeholder resolution).
     *
     * @return array<string, string|null>
     */
    public function contextForUserRegistered(User $user, ?string $locale = null): array
    {
        $name = $user->first_name
            ? trim($user->first_name . ' ' . ($user->last_name ?? ''))
            : $user->name;
        $name = $name ?: $user->email;

        return [
            'app_name'    => config('app.name'),
            'admin_email' => $this->resolveAdminEmail($user),
            'user_name'   => (string) $name,
            'user_email'  => $user->email ?? '',
            'year'        => (string) date('Y'),
        ];
    }

    /**
     * Build context for payment.status_changed.
     *
     * @param  \App\Models\Payment  $payment
     * @return array<string, string|null>
     */
    public function contextForPaymentStatusChanged(User $user, $payment, string $amountFormatted, string $currentStatusLabel, ?string $adminEmail = null): array
    {
        $name = $user->first_name
            ? trim($user->first_name . ' ' . ($user->last_name ?? ''))
            : $user->name;
        $name = $name ?: $user->email;

        $periodFrom = '';
        $periodTo   = '';
        if (isset($payment->date_from) && $payment->date_from) {
            $periodFrom = $payment->date_from->format('M d, Y');
        }
        if (isset($payment->date_to) && $payment->date_to) {
            $periodTo = $payment->date_to->format('M d, Y');
        }

        return [
            'app_name'          => config('app.name'),
            'admin_email'       => $adminEmail ?? $this->resolveAdminEmail($user),
            'user_name'         => (string) $name,
            'user_email'        => $user->email ?? '',
            'amount_formatted'  => $amountFormatted,
            'deal_number'       => $payment->deal_number ?? '—',
            'current_status'    => $currentStatusLabel,
            'period_from'       => $periodFrom,
            'period_to'         => $periodTo,
            'year'              => (string) date('Y'),
        ];
    }

    /**
     * Build context for user.status_changed.
     *
     * @return array<string, string|null>
     */
    public function contextForUserStatusChanged(User $user, string $currentStatus): array
    {
        $name = $user->first_name
            ? trim($user->first_name . ' ' . ($user->last_name ?? ''))
            : $user->name;
        $name = $name ?: $user->email;

        return [
            'app_name'      => config('app.name'),
            'admin_email'   => $this->resolveAdminEmail($user),
            'user_name'     => (string) $name,
            'user_email'    => $user->email ?? '',
            'current_status' => $currentStatus,
            'year'          => (string) date('Y'),
        ];
    }

    /**
     * Build context for message.sent.
     *
     * @param  \App\Models\Message  $message
     * @return array<string, string|null>
     */
    public function contextForMessageSent(User $recipient, \App\Models\Message $message, ?string $adminEmail = null): array
    {
        $name = $recipient->first_name
            ? trim($recipient->first_name . ' ' . ($recipient->last_name ?? ''))
            : $recipient->name;
        $name = $name ?: $recipient->email;

        $contentPreview = '';
        if (isset($message->content)) {
            $contentPreview = \Illuminate\Support\Str::limit(strip_tags((string) $message->content), 500);
        }

        return [
            'app_name'                 => config('app.name'),
            'admin_email'              => $adminEmail ?? $this->resolveAdminEmail($recipient),
            'user_name'                => (string) $name,
            'user_email'               => $recipient->email ?? '',
            'message_title'            => $message->title ?? 'Notification',
            'message_content_preview'  => $contentPreview,
            'year'                     => (string) date('Y'),
        ];
    }

    /**
     * Get placeholder definitions for a type (for API/frontend).
     *
     * @return array<string, string>
     */
    public function getPlaceholdersForType(string $type): array
    {
        return (array) (config("mail_templates.placeholders.{$type}") ?? []);
    }

    private function configKey(string $type): string
    {
        return self::KEY_PREFIX . $type;
    }

    private function resolveAdminEmail(User $user): string
    {
        if ($user->dormitory_id) {
            $dorm = $user->dormitory ?? Dormitory::with('admin')->find($user->dormitory_id);
            if ($dorm && $dorm->relationLoaded('admin') && $dorm->admin) {
                return $dorm->admin->email ?? '';
            }
        }
        $admin = User::whereHas('role', fn ($q) => $q->where('name', 'sudo'))->first();
        return $admin?->email ?? '';
    }
}
