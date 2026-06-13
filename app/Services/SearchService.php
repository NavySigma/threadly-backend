<?php
// app/Services/SearchService.php

namespace App\Services;

class SearchService
{
    // Ubah query jadi pattern untuk berbagai mode search
    public function buildSearchTerms(string $query): array
    {
        $query = trim($query);
        $words = array_filter(explode(' ', $query));

        return [
            // 1. Exact phrase — "malik udin"
            'exact'    => $query,

            // 2. Wildcard tiap kata — "%malik%" OR "%udin%"
            'words'    => $words,

            // 3. Inisial tiap kata — "mu" → cari "m%" AND "u%"
            'initials' => $this->extractInitials($words),

            // 4. Fulltext boolean — "+malik* +udin*"
            'fulltext' => $this->buildFulltextQuery($words),

            // 5. Typo tolerance — soundex/metaphone
            'soundex'  => array_map('soundex', $words),
        ];
    }

    private function extractInitials(array $words): array
    {
        return array_map(fn($w) => $w[0] ?? '', $words);
    }

    private function buildFulltextQuery(array $words): string
    {
        // Boolean mode: setiap kata harus ada, dengan wildcard
        return implode(' ', array_map(fn($w) => "+{$w}*", $words));
    }
}