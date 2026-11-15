<?php
interface AIProviderInterface {
    public function generateText(string $prompt, array $params = []): string;
    public function analyzeSentiment(string $text): array;
    public function generateVisionText(string $prompt, string $imageUrl, array $params = []): string;
}
?>