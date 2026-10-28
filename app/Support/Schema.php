<?php

namespace App\Support;

/** schema.org pieces the public pages share, so each page states them the same way. */
final class Schema
{
    /** @param  list<array{0: string, 1: string}>  $faqs  [question, answer] pairs, exactly as shown on the page */
    public static function faq(array $faqs): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
            ], $faqs),
        ];
    }

    /** A free tool that runs in the browser. */
    public static function freeTool(string $name, string $url, string $description, string $category): array
    {
        return [
            '@type' => 'WebApplication',
            'name' => $name,
            'url' => Seo::url($url, app()->getLocale()),
            'description' => $description,
            'inLanguage' => app()->getLocale(),
            'applicationCategory' => $category,
            'operatingSystem' => 'Any',
            'browserRequirements' => 'Requires a modern web browser',
            'isAccessibleForFree' => true,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'BDT'],
            'publisher' => ['@type' => 'Organization', 'name' => 'Sofor', 'url' => url('/')],
        ];
    }
}
