<?php
declare(strict_types=1);

namespace CouncilLibrary\Service;

/**
 * HTTP client for the Python embedding microservice.
 * Falls back gracefully when the service is unavailable.
 */
class EmbeddingClient
{
    private string $endpoint;
    private bool $available;
    private int $dimensions;

    public function __construct(
        string $endpoint = 'http://127.0.0.1:8900',
    ) {
        $this->endpoint = $endpoint;
        $this->available = false;
        $this->dimensions = 384;
        $this->checkHealth();
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Convert text strings to normalized embedding vectors.
     * Returns array of binary strings (raw float32 blobs) or empty array on failure.
     */
    public function embed(array $texts): array
    {
        if (!$this->available || empty($texts)) {
            return [];
        }

        try {
            $ch = curl_init($this->endpoint . '/embed');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['texts' => $texts]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                return [];
            }

            $data = json_decode($response, true);
            if (!isset($data['embeddings'])) {
                return [];
            }

            // Convert hex-encoded vectors back to binary
            return array_map(fn(string $hex) => hex2bin($hex), $data['embeddings']);

        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Embed a single text and return the binary vector.
     */
    public function embedOne(string $text): ?string
    {
        $results = $this->embed([$text]);
        return $results[0] ?? null;
    }

    private function checkHealth(): void
    {
        try {
            $ch = curl_init($this->endpoint . '/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $data = json_decode($response, true);
                $this->available = ($data['status'] ?? '') === 'ok';
            }
        } catch (\Throwable $e) {
            $this->available = false;
        }
    }
}
