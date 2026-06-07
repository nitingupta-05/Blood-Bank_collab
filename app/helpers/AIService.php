<?php
/**
 * AI insights service.
 *
 * Two sources:
 *   1. Gemini API  (if GEMINI_API_KEY is set in env)
 *   2. Static fallback data clearly labeled as "estimated"
 *
 * Result is cached in storage/cache/ai_insights.json for 24 hours.
 */
class AIService {

    private static function cacheFile(): string {
        $dir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../../storage/cache/';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        return $dir . 'ai_insights.json';
    }

    public static function getIndiaBloodInsights(): array {
        $file = self::cacheFile();
        if (is_file($file) && (time() - filemtime($file)) < 86400) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && isset($cached['donation_trends'], $cached['blood_group_distribution'])) {
                return $cached;
            }
        }

        $data = GEMINI_API_KEY ? self::callGemini() : null;
        if (!$data) $data = self::fallback();

        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    private static function callGemini(): ?array {
        $prompt = "You are an Indian blood-bank data analyst. Provide estimates of blood donation trends across India for the current year. Return ONLY valid JSON in this exact shape (no markdown, no code fences): " .
            '{"donation_trends":{"labels":["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],"donations":[12 numbers for monthly estimated donations across India]},' .
            '"blood_group_distribution":{"labels":["O+","O-","A+","A-","B+","B-","AB+","AB-"],"percentages":[8 numbers summing to 100 representing % distribution]}}';

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode(GEMINI_MODEL) . ':generateContent?key=' . rawurlencode(GEMINI_API_KEY);
        $body = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1024],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) return null;

        $j = json_decode($response, true);
        $text = trim($j['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (!$text) return null;

        // Strip accidental code fences
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $parsed = json_decode($text, true);
        if (!is_array($parsed)
            || !isset($parsed['donation_trends'], $parsed['blood_group_distribution'])) {
            return null;
        }
        $parsed['source'] = 'Gemini (' . GEMINI_MODEL . ')';
        return $parsed;
    }

    private static function fallback(): array {
        return [
            'donation_trends' => [
                'labels'    => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
                'donations' => [28450, 26120, 30980, 29540, 31200, 28760, 27340, 29810, 30450, 32180, 33500, 28900],
            ],
            'blood_group_distribution' => [
                'labels'      => BLOOD_GROUPS,
                'percentages' => [35.0, 4.5, 22.0, 3.5, 28.0, 3.0, 3.5, 0.5],
            ],
            'source' => 'Estimated (national averages)',
        ];
    }
}
