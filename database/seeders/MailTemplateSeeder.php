<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ConfigurationService;
use Illuminate\Database\Seeder;

final class MailTemplateSeeder extends Seeder
{
    private const COMMON_CSS = '
        @import url(\'https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;700&display=swap&subset=cyrillic-ext\');
        body { font-family: \'Noto Sans\', Arial, sans-serif; background: #f3f4f9; color: #232743; margin: 0; padding: 0; line-height: 1.6; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(47,52,89,0.08); overflow: hidden; }
        .header { background: #2f3459; color: #fff; padding: 1.25rem 2rem; text-align: center; }
        .header h1 { font-size: 1.25rem; font-weight: 700; letter-spacing: -0.5px; margin: 0 0 0.25rem 0; color: #fff; }
        .header p { margin: 0; font-size: 0.9rem; opacity: 0.9; }
        .content { padding: 2rem; }
        .content p { margin: 0 0 1rem 0; }
        .mail-footer { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f3f4f9; font-size: 0.95em; color: #888; text-align: center; }
        .mail-footer p { margin: 0.25rem 0; }
        .info-box { background: #e1e3f0; padding: 1rem 1.25rem; border-radius: 0.5rem; margin: 1.25rem 0; }
        .info-box p { margin: 0.4rem 0; }
        .info-box strong { color: #2f3459; }
        .message-box { background: #f9e9e3; padding: 1rem 1.25rem; border-radius: 0.5rem; margin: 1.25rem 0; border-left: 4px solid #d69979; }
        .message-box p { margin: 0.4rem 0; }
        .message-box strong { color: #2f3459; }
    ';

    public function run(): void
    {
        $configuration = app(ConfigurationService::class);
        $templates = array_merge(
            $this->userRegisteredTemplates(),
            $this->paymentStatusChangedTemplates(),
            $this->userStatusChangedTemplates(),
            $this->messageSentTemplates(),
        );

        foreach ($templates as $key => $data) {
            $configuration->setConfiguration($key, $data, 'json', 'Mail template');
        }
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private function userRegisteredTemplates(): array
    {
        $locales = [
            'en' => [
                'subject' => 'Registration Complete – {{app_name}}',
                'title'   => 'Registration Complete',
                'system_name' => '{{app_name}}',
                'greeting' => 'Hello {{user_name}},',
                'body_text' => 'Your account has been successfully registered. You can log in using your email and password.',
                'contact_hint' => 'If you have any questions, please contact your dormitory administration.',
                'footer_automated' => 'This is an automated message. Please do not reply to this email.',
                'footer_copyright' => '© {{year}} {{app_name}}.',
            ],
            'kk' => [
                'subject' => 'Тіркеу аяқталды – {{app_name}}',
                'title'   => 'Тіркеу аяқталды',
                'system_name' => '{{app_name}}',
                'greeting' => 'Сәлеметсіз бе, {{user_name}},',
                'body_text' => 'Тіркелгіңіз сәтті тіркелді. Электрондық пошта мен құпия сөзді пайдаланып жүйеге кіре аласыз.',
                'contact_hint' => 'Сұрақтарыңыз болса, жатақхана әкімшілігіне хабарласыңыз.',
                'footer_automated' => 'Бұл автоматты хабарлама. Осы хатқа жауап бермеңіз.',
                'footer_copyright' => '© {{year}} {{app_name}}.',
            ],
            'ru' => [
                'subject' => 'Регистрация завершена – {{app_name}}',
                'title'   => 'Регистрация завершена',
                'system_name' => '{{app_name}}',
                'greeting' => 'Здравствуйте, {{user_name}},',
                'body_text' => 'Ваша учётная запись успешно зарегистрирована. Вы можете войти, используя email и пароль.',
                'contact_hint' => 'Если у вас есть вопросы, обратитесь в администрацию общежития.',
                'footer_automated' => 'Это автоматическое сообщение. Пожалуйста, не отвечайте на это письмо.',
                'footer_copyright' => '© {{year}} {{app_name}}.',
            ],
        ];

        $out = [];
        foreach ($locales as $locale => $t) {
            $out[$locale] = [
                'subject' => $t['subject'],
                'body'    => $this->wrapHtml(
                    $t['title'],
                    $t['system_name'],
                    $t['greeting'] . "\n            <p>" . $t['body_text'] . "</p>\n            <p>" . $t['contact_hint'] . "</p>",
                    $t['footer_automated'],
                    $t['footer_copyright'],
                ),
            ];
        }
        return [ 'mail_template_user_registered' => $out ];
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private function paymentStatusChangedTemplates(): array
    {
        $subjects = [
            'en' => 'Payment Status Update – {{app_name}}',
            'kk' => 'Payment Status Update – {{app_name}}',
            'ru' => 'Payment Status Update – {{app_name}}',
        ];
        $bodyEn = '<p>Hello {{user_name}},</p>
            <p>Your payment status has been updated.</p>
            <div class="info-box">
                <p><strong>Deal number:</strong> {{deal_number}}</p>
                <p><strong>Amount:</strong> {{amount_formatted}}</p>
                <p><strong>Current status:</strong> {{current_status}}</p>
                <p><strong>Period:</strong> {{period_from}} – {{period_to}}</p>
            </div>
            <p>If you have questions, please <a href="mailto:{{admin_email}}" style="color: #2f3459; font-weight: 600;">contact your dormitory administration</a>.</p>';
        $footer = '<p>This is an automated message. Please do not reply to this email.</p><p>&copy; {{year}} {{app_name}}.</p>';

        $out = [];
        foreach (array_keys($subjects) as $locale) {
            $out[$locale] = [
                'subject' => $subjects[$locale],
                'body'    => $this->wrapHtml('Payment Status Update', '{{app_name}}', $bodyEn, 'This is an automated message. Please do not reply to this email.', '&copy; {{year}} {{app_name}}.'),
            ];
        }
        return [ 'mail_template_payment_status_changed' => $out ];
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private function userStatusChangedTemplates(): array
    {
        $subjects = [
            'en' => 'Account Status Update – {{app_name}}',
            'kk' => 'Account Status Update – {{app_name}}',
            'ru' => 'Account Status Update – {{app_name}}',
        ];
        $bodyEn = '<p>Hello {{user_name}},</p>
            <p>Your account status has been updated.</p>
            <div class="info-box">
                <p><strong>Current status:</strong> {{current_status}}</p>
            </div>
            <p>If you have questions or believe this change is in error, please contact your dormitory administration.</p>';

        $out = [];
        foreach (array_keys($subjects) as $locale) {
            $out[$locale] = [
                'subject' => $subjects[$locale],
                'body'    => $this->wrapHtml('Account Status Update', '{{app_name}}', $bodyEn, 'This is an automated message. Please do not reply to this email.', '&copy; {{year}} {{app_name}}.'),
            ];
        }
        return [ 'mail_template_user_status_changed' => $out ];
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private function messageSentTemplates(): array
    {
        $subjects = [
            'en' => 'New Message: {{message_title}}',
            'kk' => 'New Message: {{message_title}}',
            'ru' => 'New Message: {{message_title}}',
        ];
        $bodyEn = '<p>Hello {{user_name}},</p>
            <p>You have received a new message from the administration.</p>
            <div class="message-box">
                <p><strong>{{message_title}}</strong></p>
                <p>{{message_content_preview}}</p>
            </div>
            <p>Please log in to the system to view the full message and any further details.</p>';

        $out = [];
        foreach (array_keys($subjects) as $locale) {
            $out[$locale] = [
                'subject' => $subjects[$locale],
                'body'    => $this->wrapHtml('New Message', '{{app_name}}', $bodyEn, 'This is an automated message. Please do not reply to this email.', '&copy; {{year}} {{app_name}}.'),
            ];
        }
        return [ 'mail_template_message_sent' => $out ];
    }

    private function wrapHtml(
        string $title,
        string $systemName,
        string $contentInner,
        string $footerAutomated,
        string $footerCopyright,
    ): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>' . self::COMMON_CSS . '</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($title) . '</h1>
            <p>' . htmlspecialchars($systemName) . '</p>
        </div>
        <div class="content">
            ' . $contentInner . '
        </div>
        <div class="mail-footer">
            <p>' . $footerAutomated . '</p>
            <p>' . $footerCopyright . '</p>
        </div>
    </div>
</body>
</html>';
    }
}
