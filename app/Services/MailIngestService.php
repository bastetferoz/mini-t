<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MailIngestConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MailIngestService
{
    protected MailIngestConfig $config;
    protected ?string $accessToken = null;

    public function __construct(MailIngestConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Procesa los mails no leídos del buzón configurado.
     */
    public function process(): array
    {
        $stats = ['processed' => 0, 'errors' => 0, 'skipped' => 0];

        $token = $this->getAccessToken();

        if (! $token) {
            Log::error("MailIngest: No se pudo obtener token para {$this->config->email}");
            return $stats;
        }

        // Obtener mails no leídos con adjuntos
        $messages = $this->getUnreadMessages($token);

        foreach ($messages as $message) {
            try {
                $attachments = $this->getAttachments($token, $message['id']);

                foreach ($attachments as $attachment) {
                    // Solo procesar PDF e imágenes
                    $mime = $attachment['contentType'] ?? '';
                    if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])) {
                        continue;
                    }

                    $result = $this->processAttachment($attachment);

                    if ($result === true) {
                        $stats['processed']++;
                    } elseif ($result === false) {
                        $stats['errors']++;
                    } else {
                        $stats['skipped']++; // duplicado
                    }
                }

                // Marcar como leído
                $this->markAsRead($token, $message['id']);

                // Delay para evitar rate limit de IA
                sleep(3);

            } catch (\Throwable $e) {
                Log::error("MailIngest: Error procesando mail {$message['id']}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        // Actualizar stats
        $this->config->update([
            'last_checked_at' => now(),
            'total_processed' => $this->config->total_processed + $stats['processed'],
            'total_errors' => $this->config->total_errors + $stats['errors'],
        ]);

        return $stats;
    }

    /**
     * Obtiene access token via OAuth Client Credentials (Microsoft Graph).
     */
    protected function getAccessToken(): ?string
    {
        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->config->tenant_id}/oauth2/v2.0/token",
                [
                    'client_id' => $this->config->client_id,
                    'client_secret' => $this->config->client_secret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->successful()) {
                $this->accessToken = $response->json('access_token');
                return $this->accessToken;
            }

            Log::error("MailIngest: Auth failed: " . $response->body());
        } catch (\Throwable $e) {
            Log::error("MailIngest: Auth exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtiene mensajes no leídos con adjuntos.
     */
    protected function getUnreadMessages(string $token): array
    {
        $response = Http::withToken($token)->get(
            "https://graph.microsoft.com/v1.0/users/{$this->config->email}/messages",
            [
                '$top' => 20,
                '$select' => 'id,subject,receivedDateTime,from,isRead,hasAttachments',
            ]
        );

        if (! $response->successful()) {
            Log::error("MailIngest: Error al obtener mails: " . $response->body());
            return [];
        }

        // Filtrar localmente los que tienen adjuntos
        $messages = $response->json('value') ?? [];

        return array_filter($messages, fn ($msg) => ($msg['hasAttachments'] ?? false) === true);
    }

    /**
     * Obtiene adjuntos de un mensaje.
     */
    protected function getAttachments(string $token, string $messageId): array
    {
        $response = Http::withToken($token)->get(
            "https://graph.microsoft.com/v1.0/users/{$this->config->email}/messages/{$messageId}/attachments"
        );

        if (! $response->successful()) {
            return [];
        }

        return $response->json('value') ?? [];
    }

    /**
     * Procesa un adjunto: lo guarda, lo analiza con IA y crea la factura.
     * Retorna: true (ok), false (error), null (skipped/duplicado)
     */
    protected function processAttachment(array $attachment): ?bool
    {
        $filename = $attachment['name'] ?? 'invoice.' . ($attachment['contentType'] === 'application/pdf' ? 'pdf' : 'jpg');
        $content = base64_decode($attachment['contentBytes'] ?? '');

        if (empty($content)) {
            return false;
        }

        // Guardar en temp
        $tempPath = 'invoices/temp/' . uniqid('mail_') . '_' . $filename;
        Storage::disk('public')->put($tempPath, $content);

        // Analizar con IA
        $parsed = InvoiceParserService::parse($tempPath);

        if (! $parsed) {
            ActivityLogger::facturacion("❌ Mail Ingest: error al analizar {$filename} - " . (InvoiceParserService::$lastError ?? 'desconocido'));
            return false;
        }

        // Verificar duplicado
        $invoiceNumber = $parsed['invoice_number'] ?? null;
        $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);

        if ($invoiceNumber) {
            $exists = Invoice::where('invoice_number', $invoiceNumber)
                ->where('provider', $provider)
                ->exists();

            if ($exists) {
                ActivityLogger::facturacion("⚠️ Mail Ingest: duplicada omitida {$provider} Nº {$invoiceNumber}");
                Storage::disk('public')->delete($tempPath);
                return null;
            }
        }

        // Organizar archivo
        $finalPath = InvoiceParserService::organizeFile($tempPath, $parsed);
        $period = $parsed['period'] ?? now()->format('Y-m');
        $parts = explode('-', $period);

        // Crear factura
        $invoice = Invoice::create([
            'provider' => $provider,
            'company' => $parsed['company'] ?? null,
            'service' => $parsed['service'] ?? null,
            'reference' => $parsed['reference'] ?? null,
            'amount' => $parsed['amount'] ?? 0,
            'currency' => $parsed['currency'] ?? 'ARS',
            'invoice_date' => $parsed['invoice_date'] ?? now()->toDateString(),
            'period' => $period,
            'month' => (int) ($parts[1] ?? now()->month),
            'year' => (int) ($parts[0] ?? now()->year),
            'invoice_number' => $invoiceNumber,
            'file_path' => $finalPath,
            'notes' => 'Cargada automáticamente desde email',
        ]);

        // Tipo de cambio
        if ($invoice->currency === 'ARS') {
            $rate = ExchangeRateService::getBnaRate($invoice->invoice_date->format('Y-m-d'));
            if ($rate) {
                $invoice->update(['exchange_rate' => $rate, 'amount_usd' => round($invoice->amount / $rate, 2)]);
            }
        } elseif ($invoice->currency === 'USD') {
            $invoice->update(['exchange_rate' => 1, 'amount_usd' => $invoice->amount]);
        }

        ActivityLogger::facturacion("✓ Mail Ingest: {$provider} | \${$parsed['amount']} | {$period} (desde email)", $invoice);

        return true;
    }

    /**
     * Marca un mensaje como leído.
     */
    protected function markAsRead(string $token, string $messageId): void
    {
        Http::withToken($token)->patch(
            "https://graph.microsoft.com/v1.0/users/{$this->config->email}/messages/{$messageId}",
            ['isRead' => true]
        );
    }
}
