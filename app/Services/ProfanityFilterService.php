<?php

namespace App\Services;

class ProfanityFilterService
{
    protected array $badWords = [];

    public function __construct()
    {
        $this->loadBadWords();
    }

    protected function loadBadWords(): void
    {
        $english = file(resource_path('badwords/en.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $arabic = file(resource_path('badwords/ar.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        $this->badWords = array_merge($english, $arabic);
    }

    public function hasProfanity(string $text): bool
    {
        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu'; // \b ensures whole word match
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    public function clean(string $text): string
    {
        foreach ($this->badWords as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            $replacement = str_repeat('*', mb_strlen($word));
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }
}