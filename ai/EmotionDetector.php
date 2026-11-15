<?php
class EmotionDetector {
    public static function detect(string $text): string {
        $map = [
            'happy' => ['happy','joy','glad','wonderful','great'],
            'sad' => ['sad','depressed','lonely','grief','cry','tears'],
            'angry' => ['angry','frustrated','annoyed','rage'],
            'stressed' => ['stress','anxious','overwhelmed','pressure'],
            'hopeful' => ['hope','better','improve','optimistic'],
            'calm' => ['calm','peace','relax','serene']
        ];
        $text = strtolower($text);
        foreach ($map as $emotion => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false) return $emotion;
            }
        }
        return 'neutral';
    }
}
?>