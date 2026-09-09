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
     * @param int $lookbackDays Días hacia atrás para buscar (0 = sin filtro de fecha, trae recientes)
     */
    public function process(int $lookbackDays = 0): array
    {
        $stats = ['processed' => 0, 'errors' => 0, 'skipped' => 0];

        $token = $this->getAccessToken();

        if (! $token) {
            Log::error("MailIngest: No se pudo obtener token para {$this->config->email}");
            return $stats;
        }

        // Obtener mails con adjuntos
        $messages = $this->getUnreadMessages($token, $lookbackDays);

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
     * Obtiene mensajes con adjuntos.
     * Si lookbackDays > 0, filtra por fecha y pagina hasta traer todos.
     */
    protected function getUnreadMessages(string $token, int $lookbackDays = 0): array
    {
        $params = [
            '$select' => 'id,subject,receivedDateTime,from,isRead,hasAttachments',
            '$orderby' => 'receivedDateTime desc',
        ];

        if ($lookbackDays > 0) {
            // Histórico: traer muchos, filtrar por fecha
            $params['$top'] = 50;
            $since = now()->subDays($lookbackDays)->format('Y-m-d\TH:i:s\Z');
            $params['$filter'] = "receivedDateTime ge {$since}";
        } else {
            // Normal: solo los últimos 20
            $params['$top'] = 20;
        }

        $allMessages = [];
        $url = "https://graph.microsoft.com/v1.0/users/{$this->config->email}/messages";
        $maxPages = $lookbackDays > 0 ? 20 : 1; // Histórico: hasta 20 páginas (1000 mails)
        $page = 0;

        while ($url && $page < $maxPages) {
            $response = Http::withToken($token)->get($url, $page === 0 ? $params : []);

            if (! $response->successful()) {
                // Si falla el filtro, intentar sin él (filtrar localmente)
                if ($page === 0 && $lookbackDays > 0 && str_contains($response->body(), 'InefficientFilter')) {
                    Log::warning("MailIngest: $filter no soportado, trayendo sin filtro y filtrando localmente");
                    unset($params['$filter']);
                    $params['$top'] = 50;
                    $response = Http::withToken($token)->get(
                        "https://graph.microsoft.com/v1.0/users/{$this->config->email}/messages",
                        $params
                    );
                    if (! $response->successful()) {
                        Log::error("MailIngest: Error al obtener mails: " . $response->body());
                        return [];
                    }
                } else {
                    Log::error("MailIngest: Error al obtener mails: " . $response->body());
                    return [];
                }
            }

            $messages = $response->json('value') ?? [];
            $allMessages = array_merge($allMessages, $messages);

            // Siguiente página
            $url = $response->json('@odata.nextLink');
            $page++;
        }

        // Filtrar: solo los que tienen adjuntos
        $filtered = array_filter($allMessages, fn ($msg) => ($msg['hasAttachments'] ?? false) === true);

        // Si es histórico y no se pudo usar $filter, filtrar por fecha localmente
        if ($lookbackDays > 0) {
            $since = now()->subDays($lookbackDays);
            $filtered = array_filter($filtered, function ($msg) use ($since) {
                $received = $msg['receivedDateTime'] ?? null;
                if (! $received) return false;
                return \Carbon\Carbon::parse($received)->gte($since);
            });
        }

        return array_values($filtered);
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

        // ─── DEDUPLICACIÓN ANTES DE LA IA ───
        // Calcular el hash del contenido y, si este adjunto ya se procesó antes,
        // descartarlo SIN llamar a la IA (ahorra tokens en correos repetidos).
        // EXCEPCIÓN: si la factura vinculada perdió su archivo, permitimos
        // reprocesar para recuperarlo.
        $hash = hash('sha256', $content);

        $ingested = \App\Models\IngestedAttachment::where('hash', $hash)->first();

        if ($ingested) {
            $facturaTieneArchivo = false;
            if ($ingested->invoice_id) {
                $factInv = Invoice::find($ingested->invoice_id);
                $facturaTieneArchivo = $factInv
                    && $factInv->file_path
                    && Storage::disk('public')->exists($factInv->file_path);
            }

            if ($facturaTieneArchivo) {
                ActivityLogger::facturacion("⏭️ Mail Ingest: adjunto ya procesado, omitido sin IA ({$filename})");
                return null;
            }
            // Si no tiene archivo, seguimos para recuperarlo (no retornamos).
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

        $invoiceNumber = $parsed['invoice_number'] ?? null;
        $provider = InvoiceParserService::normalizeProvider($parsed['provider'] ?? null);
        $period = $parsed['period'] ?? now()->format('Y-m');
        $invoiceDate = $parsed['invoice_date'] ?? null;
        [$year, $month] = Invoice::deriveMonthYear($period, $invoiceDate);

        // Verificar duplicado por número + proveedor
        $existing = $invoiceNumber
            ? Invoice::where('invoice_number', $invoiceNumber)->where('provider', $provider)->first()
            : null;

        if ($existing) {
            $tieneArchivo = $existing->file_path && Storage::disk('public')->exists($existing->file_path);

            if ($tieneArchivo) {
                // Duplicado real: ya está cargada y con archivo. Omitir.
                ActivityLogger::facturacion("⚠️ Mail Ingest: duplicada omitida {$provider} Nº {$invoiceNumber}");
                \App\Models\IngestedAttachment::firstOrCreate(
                    ['hash' => $hash],
                    ['filename' => $filename, 'provider' => $provider, 'invoice_number' => $invoiceNumber, 'invoice_id' => $existing->id]
                );
                Storage::disk('public')->delete($tempPath);
                return null;
            }

            // La factura existe pero SIN archivo: recuperar el archivo perdido.
            $finalPath = InvoiceParserService::organizeFile($tempPath, $parsed, $provider, $year, $month);
            if (! $finalPath) {
                ActivityLogger::facturacion("❌ Mail Ingest: no se pudo guardar el archivo de recuperación de {$provider} Nº {$invoiceNumber}");
                Storage::disk('public')->delete($tempPath);
                return false;
            }

            $existing->update(['file_path' => $finalPath]);
            \App\Models\IngestedAttachment::updateOrCreate(
                ['hash' => $hash],
                ['filename' => $filename, 'provider' => $provider, 'invoice_number' => $invoiceNumber, 'invoice_id' => $existing->id]
            );
            ActivityLogger::facturacion("♻️ Mail Ingest: archivo recuperado para {$provider} Nº {$invoiceNumber} (factura #{$existing->id})");
            return true;
        }

        // Organizar archivo (con provider/year/month reales; devuelve null si falla)
        $finalPath = InvoiceParserService::organizeFile($tempPath, $parsed, $provider, $year, $month);

        if (! $finalPath) {
            ActivityLogger::facturacion("❌ Mail Ingest: no se pudo guardar el archivo de {$filename}, no se crea la factura");
            Storage::disk('public')->delete($tempPath);
            return false;
        }

        // Crear factura
        $invoice = Invoice::create([
            'provider' => $provider,
            'company' => $parsed['company'] ?? null,
            'service' => $parsed['service'] ?? null,
            'reference' => $parsed['reference'] ?? null,
            'amount' => $parsed['amount'] ?? 0,
            'currency' => $parsed['currency'] ?? 'ARS',
            'invoice_date' => $invoiceDate ?? now()->toDateString(),
            'period' => $period,
            'month' => $month,
            'year' => $year,
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

        // Registrar el hash del adjunto para no reprocesarlo con IA en el futuro.
        \App\Models\IngestedAttachment::updateOrCreate(
            ['hash' => $hash],
            [
                'filename' => $filename,
                'provider' => $provider,
                'invoice_number' => $invoiceNumber,
                'invoice_id' => $invoice->id,
            ]
        );

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
