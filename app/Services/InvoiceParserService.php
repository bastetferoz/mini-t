<?php

namespace App\Services;

use App\Models\AiProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InvoiceParserService
{
    /**
     * Analiza un archivo de factura (PDF o imagen) usando el perfil de IA predeterminado.
     */
    public static function parse(string $filePath): ?array
    {
        $profile = AiProfile::getDefault();

        if (! $profile) {
            \Log::error('InvoiceParser: No hay perfil de IA predeterminado configurado.');
            return null;
        }

        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            \Log::error("InvoiceParser: Archivo no encontrado en: {$fullPath}");
            return null;
        }

        $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
        $base64 = base64_encode(file_get_contents($fullPath));

        \Log::info("InvoiceParser: Usando perfil '{$profile->name}' ({$profile->provider}/{$profile->model})");
        \Log::info("InvoiceParser: Archivo: {$fullPath} ({$mimeType}, " . strlen($base64) . " bytes base64)");

        $prompt = self::getPrompt();

        $result = match ($profile->provider) {
            'openai' => self::callOpenAI($profile, $base64, $mimeType, $prompt),
            'google' => self::callGemini($profile, $base64, $mimeType, $prompt),
            'anthropic' => self::callAnthropic($profile, $base64, $mimeType, $prompt),
            default => null,
        };

        if (! $result) {
            \Log::error('InvoiceParser: El proveedor no devolvió resultado válido.');
        }

        return $result;
    }

    /**
     * Prompt estándar para extracción de datos de factura.
     */
    private static function getPrompt(): string
    {
        return <<<PROMPT
Analizá esta factura y extraé los siguientes datos en formato JSON estricto (sin texto adicional, solo el JSON):

{
  "provider": "nombre del proveedor/empresa que emite la factura",
  "service": "tipo de servicio (Internet, Hosting, Licencias, Telefonía, Cloud, etc.)",
  "amount": 12345.67,
  "currency": "ARS o USD",
  "invoice_date": "YYYY-MM-DD",
  "period": "YYYY-MM",
  "invoice_number": "número de factura o comprobante"
}

Reglas:
- "amount" debe ser numérico sin puntos de miles. Usá punto como separador decimal.
- "currency" debe ser "ARS" si es en pesos argentinos o "USD" si es en dólares.
- "period" es el período al que corresponde la factura (mes de servicio).
- "invoice_date" es la fecha de emisión de la factura.
- Si no podés determinar un campo, usá null.
- Para "provider", identificá si es: telecom, metrotel, amazon, microsoft, google, movistar, claro, iplan. Si es otro, poné el nombre tal cual.
PROMPT;
    }

    /**
     * Llamada a OpenAI (GPT-4o Vision).
     */
    private static function callOpenAI(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?array
    {
        try {
            \Log::info("InvoiceParser: Llamando a OpenAI ({$profile->model}) en {$profile->getEndpointUrl()}");

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$profile->api_key}",
            ])->timeout(60)->post($profile->getEndpointUrl(), [
                'model' => $profile->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64}",
                                ],
                            ],
                        ],
                    ],
                ],
                'max_tokens' => 1000,
                'temperature' => 0.1,
            ]);

            if (! $response->successful()) {
                \Log::error("InvoiceParser: OpenAI respondió con error HTTP {$response->status()}: " . $response->body());
                return null;
            }

            $content = $response->json('choices.0.message.content');
            \Log::info("InvoiceParser: OpenAI respondió OK: " . substr($content ?? '', 0, 200));

            return self::extractJson($content);
        } catch (\Throwable $e) {
            \Log::error("InvoiceParser: Excepción al llamar OpenAI: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Llamada a Google Gemini.
     */
    private static function callGemini(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?array
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$profile->model}:generateContent?key={$profile->api_key}";

            \Log::info("InvoiceParser: Llamando a Gemini ({$profile->model})");

            $response = Http::timeout(90)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 1000,
                ],
            ]);

            if (! $response->successful()) {
                \Log::error("InvoiceParser: Gemini respondió con error HTTP {$response->status()}: " . $response->body());
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            \Log::info("InvoiceParser: Gemini respondió OK: " . substr($text ?? '', 0, 200));

            return self::extractJson($text);
        } catch (\Throwable $e) {
            \Log::error("InvoiceParser: Excepción al llamar Gemini: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Llamada a Anthropic (Claude).
     */
    private static function callAnthropic(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?array
    {
        $endpoint = $profile->endpoint ?: 'https://api.anthropic.com/v1/messages';

        $response = Http::withHeaders([
            'x-api-key' => $profile->api_key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post($endpoint, [
            'model' => $profile->model,
            'max_tokens' => 1000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);

        if (! $response->successful()) {
            report(new \Exception('Anthropic API error: ' . $response->body()));
            return null;
        }

        $text = $response->json('content.0.text');

        return self::extractJson($text);
    }

    /**
     * Extrae JSON de una respuesta que puede tener markdown.
     */
   private static function extractJson(?string $content): ?array
{
    if (blank($content)) {
        \Log::error('InvoiceParser: respuesta vacía.');
        return null;
    }

    // Guardar la respuesta completa para depuración
    \Log::info("=========== IA RAW ===========");
    \Log::info($content);
    \Log::info("==============================");

    // Eliminar bloques Markdown
    $content = preg_replace('/```json/i', '', $content);
    $content = str_replace('```', '', $content);

    // Buscar el primer objeto JSON
    $start = strpos($content, '{');
    $end = strrpos($content, '}');

    if ($start !== false && $end !== false) {
        $content = substr($content, $start, $end - $start + 1);
    }

    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {

        \Log::error("InvoiceParser: JSON inválido");
        \Log::error(json_last_error_msg());
        \Log::error($content);

        return null;
    }

    return $data;
}

    /**
     * Mueve el archivo a la carpeta organizada: invoices/{provider}/{year}/{month}/
     */
    public static function organizeFile(string $tempPath, array $parsedData): string
    {
        $provider = $parsedData['provider'] ?? 'sin_proveedor';
        $provider = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $provider));

        $period = $parsedData['period'] ?? now()->format('Y-m');
        $parts = explode('-', $period);
        $year = $parts[0] ?? now()->year;
        $month = $parts[1] ?? now()->month;

        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
        $filename = ($parsedData['invoice_number'] ?? uniqid('inv_')) . '.' . $extension;
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        $destination = "invoices/{$provider}/{$year}/{$month}/{$filename}";

        Storage::disk('public')->move($tempPath, $destination);

        return $destination;
    }

    /**
     * Normaliza el provider para matchear con las opciones del select.
     */
    public static function normalizeProvider(?string $provider): string
    {
        if (! $provider) {
            return 'otro';
        }

        $lower = strtolower($provider);

        $map = [
            'telecom' => 'telecom',
            'metrotel' => 'metrotel',
            'amazon' => 'amazon',
            'aws' => 'amazon',
            'microsoft' => 'microsoft',
            'google' => 'google',
            'movistar' => 'movistar',
            'claro' => 'claro',
            'iplan' => 'iplan',
        ];

        foreach ($map as $keyword => $value) {
            if (str_contains($lower, $keyword)) {
                return $value;
            }
        }

        return 'otro';
    }
}
