<?php

namespace App\Services;

use App\Models\AiProfile;
use App\Models\InvoiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InvoiceParserService
{
    public static ?string $lastError = null;

    /**
     * Analiza una factura en 2 etapas:
     * 1. Identifica el proveedor
     * 2. Extrae datos con prompt específico o genérico
     */
    public static function parse(string $filePath): ?array
    {
        self::$lastError = null;

        $profile = AiProfile::getDefault();

        if (! $profile) {
            self::$lastError = 'No hay perfil de IA predeterminado configurado. Andá a Administración → IA.';
            return null;
        }

        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            self::$lastError = "Archivo no encontrado: {$fullPath}";
            return null;
        }

        $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
        $base64 = base64_encode(file_get_contents($fullPath));

        \Log::info("InvoiceParser: Usando perfil '{$profile->name}' ({$profile->provider}/{$profile->model})");

        // ─── ETAPA 1: Identificar proveedor ───
        $providerSlug = self::identifyProvider($profile, $base64, $mimeType);
        \Log::info("InvoiceParser: Proveedor identificado: " . ($providerSlug ?? 'desconocido'));

        // ─── ETAPA 2: Extraer datos con prompt específico ───
        $invoiceProvider = $providerSlug ? InvoiceProvider::findBySlug($providerSlug) : null;
        $prompt = self::buildExtractionPrompt($invoiceProvider);

        \Log::info("InvoiceParser: Usando prompt " . ($invoiceProvider?->custom_prompt ? 'CUSTOM' : 'GENÉRICO') . " para '{$providerSlug}'");

        $result = self::callAi($profile, $base64, $mimeType, $prompt);

        if ($result && $providerSlug) {
            $result['provider'] = $providerSlug;
        }

        return $result;
    }

    /**
     * ETAPA 1: Identifica el proveedor con un prompt corto.
     */
    private static function identifyProvider(AiProfile $profile, string $base64, string $mimeType): ?string
    {
        // Obtener todos los proveedores configurados para armar el prompt
        $providers = InvoiceProvider::where('is_active', true)->get();

        if ($providers->isEmpty()) {
            // Sin proveedores configurados, saltar identificación
            return null;
        }

        $providerList = $providers->map(function ($p) {
            $keywords = implode(', ', $p->detection_keywords ?? []);
            return "- \"{$p->slug}\" → {$p->name} (palabras clave: {$keywords})";
        })->implode("\n");

        $prompt = <<<PROMPT
Identificá el proveedor de esta factura. Respondé SOLO con el slug en texto plano, sin comillas, sin JSON, sin explicación.

Proveedores conocidos:
{$providerList}

Si no coincide con ninguno, respondé: desconocido
PROMPT;

        $text = self::callAiRaw($profile, $base64, $mimeType, $prompt);

        if (! $text) {
            return null;
        }

        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

        if ($slug === 'desconocido' || $slug === '') {
            return null;
        }

        // Verificar que el slug existe en la DB
        $exists = InvoiceProvider::where('slug', $slug)->where('is_active', true)->exists();

        return $exists ? $slug : null;
    }

    /**
     * Arma el prompt de extracción según el proveedor.
     */
    private static function buildExtractionPrompt(?InvoiceProvider $provider): string
    {
        // Si tiene prompt custom, usarlo
        if ($provider && $provider->custom_prompt) {
            return $provider->custom_prompt;
        }

        // Prompt genérico
        $currency = $provider?->default_currency ?? 'ARS';

        return <<<PROMPT
Extraé los datos de esta factura en formato JSON estricto (sin texto adicional, solo JSON):

{
  "service": "tipo de servicio (Internet, Hosting, Licencias, Telefonía, Cloud, etc.)",
  "amount": 12345.67,
  "currency": "{$currency}",
  "invoice_date": "YYYY-MM-DD",
  "period": "YYYY-MM",
  "invoice_number": "número de factura o comprobante"
}

Reglas:
- "amount": numérico, punto como separador decimal, sin puntos de miles.
- "currency": "ARS" para pesos argentinos, "USD" para dólares.
- "period": el mes al que corresponde el servicio facturado.
- "invoice_date": fecha de emisión.
- Si no podés determinar un campo, usá null.
PROMPT;
    }

    /**
     * Llama a la IA y retorna el JSON parseado.
     */
    private static function callAi(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?array
    {
        $text = self::callAiRaw($profile, $base64, $mimeType, $prompt);

        if (! $text) {
            return null;
        }

        return self::extractJson($text);
    }

    /**
     * Llama a la IA y retorna el texto crudo de la respuesta.
     */
    private static function callAiRaw(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?string
    {
        return match ($profile->provider) {
            'openai' => self::callOpenAI($profile, $base64, $mimeType, $prompt),
            'google' => self::callGemini($profile, $base64, $mimeType, $prompt),
            'anthropic' => self::callAnthropic($profile, $base64, $mimeType, $prompt),
            default => null,
        };
    }

    // ─── PROVEEDORES DE IA ───

    private static function callOpenAI(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?string
    {
        try {
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
                self::$lastError = "OpenAI HTTP {$response->status()}: " . substr($response->body(), 0, 300);
                \Log::error("InvoiceParser: " . self::$lastError);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Throwable $e) {
            self::$lastError = "Excepción OpenAI: " . $e->getMessage();
            \Log::error("InvoiceParser: " . self::$lastError);
            return null;
        }
    }

    private static function callGemini(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?string
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$profile->model}:generateContent?key={$profile->api_key}";

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
                self::$lastError = "Gemini HTTP {$response->status()}: " . substr($response->body(), 0, 300);
                \Log::error("InvoiceParser: " . self::$lastError);
                return null;
            }

            return $response->json('candidates.0.content.parts.0.text');
        } catch (\Throwable $e) {
            self::$lastError = "Excepción Gemini: " . $e->getMessage();
            \Log::error("InvoiceParser: " . self::$lastError);
            return null;
        }
    }

    private static function callAnthropic(AiProfile $profile, string $base64, string $mimeType, string $prompt): ?string
    {
        try {
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
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                self::$lastError = "Anthropic HTTP {$response->status()}: " . substr($response->body(), 0, 300);
                \Log::error("InvoiceParser: " . self::$lastError);
                return null;
            }

            return $response->json('content.0.text');
        } catch (\Throwable $e) {
            self::$lastError = "Excepción Anthropic: " . $e->getMessage();
            \Log::error("InvoiceParser: " . self::$lastError);
            return null;
        }
    }

    // ─── UTILIDADES ───

    private static function extractJson(?string $content): ?array
    {
        if (blank($content)) {
            self::$lastError = "La IA devolvió una respuesta vacía.";
            return null;
        }

        \Log::info("InvoiceParser RAW: " . substr($content, 0, 500));

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
            self::$lastError = "JSON inválido: " . json_last_error_msg() . " | Respuesta: " . substr($content, 0, 200);
            \Log::error("InvoiceParser: " . self::$lastError);
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
     * Normaliza el provider usando la tabla de proveedores o fallback.
     */
    public static function normalizeProvider(?string $provider): string
    {
        if (! $provider) {
            return 'otro';
        }

        // Buscar en la tabla de proveedores
        $exists = InvoiceProvider::where('slug', $provider)->where('is_active', true)->exists();

        if ($exists) {
            return $provider;
        }

        // Fallback: intentar matchear por nombre
        $lower = strtolower($provider);
        $match = InvoiceProvider::where('is_active', true)->get()->first(function ($p) use ($lower) {
            foreach ($p->detection_keywords ?? [] as $keyword) {
                if (str_contains($lower, strtolower($keyword))) {
                    return true;
                }
            }
            return false;
        });

        return $match?->slug ?? 'otro';
    }
}
