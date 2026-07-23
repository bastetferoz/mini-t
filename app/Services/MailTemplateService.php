<?php

namespace App\Services;

use App\Models\MailTemplate;
use App\Models\SmtpProfile;
use Illuminate\Support\Facades\Mail;

class MailTemplateService
{
    public static function send(string $code, array $variables = [], ?string $toEmail = null): bool
    {
        try {
            $template = MailTemplate::where('code', $code)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return false;
            }

            // Buscar perfil SMTP de la plantilla o el predeterminado
            $smtp = null;

            if ($template->smtp_profile_id) {
                $smtp = SmtpProfile::find($template->smtp_profile_id);
            }

            if (! $smtp) {
                $smtp = SmtpProfile::where('is_default', true)
                    ->where('is_active', true)
                    ->first();
            }

            if (! $smtp) {
                return false;
            }

            // Destinatario principal (prioridad: argumento > plantilla > variable email > SMTP default)
            $email = $toEmail
                ?? $template->schedule_to
                ?? ($variables['email'] ?? null)
                ?? $smtp->default_to;

            if (! $email) {
                return false;
            }

            // Reemplazo de variables
            $search = array_map(
                fn ($k) => '{{ ' . $k . ' }}',
                array_keys($variables)
            );

            $replace = array_values($variables);

            $subject = str_replace($search, $replace, $template->subject);
            $body = str_replace($search, $replace, $template->body);

            // Configurar SMTP dinámicamente
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host'       => $smtp->host,
                'mail.mailers.smtp.port'       => $smtp->port,
                'mail.mailers.smtp.username'   => $smtp->username,
                'mail.mailers.smtp.password'   => $smtp->password,
                'mail.mailers.smtp.encryption' => $smtp->encryption,
                'mail.from.address'            => $smtp->from_address,
                'mail.from.name'               => $smtp->from_name,
            ]);

            Mail::html($body, function ($message) use ($email, $subject, $smtp, $template) {
                $message->to($email)
                    ->subject($subject);

                // CC: combinar los de la plantilla + los del perfil SMTP
                $ccSources = array_filter([
                    $template->schedule_cc ?? null,
                    $smtp->cc_addresses ?? null,
                ]);

                $ccList = [];
                foreach ($ccSources as $source) {
                    $ccList = array_merge($ccList, array_map('trim', explode(';', $source)));
                }

                $ccList = array_filter(array_unique($ccList));

                if (! empty($ccList)) {
                    $message->cc($ccList);
                }
            });

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}