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
            if (stripos($text, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    public function clean(string $text): string
    {
        foreach ($this->badWords as $word) {
            $pattern = '/' . preg_quote($word, '/') . '/iu'; // 'u' flag for Arabic UTF-8
            $replacement = str_repeat('*', mb_strlen($word));
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }
}