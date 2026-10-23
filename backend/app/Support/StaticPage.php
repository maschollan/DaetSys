<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Penyaji halaman HTML statis dari resources/ (menggantikan Blade).
 *
 * Nilai dinamis — token CSRF dan URL frontend — disuntikkan lewat
 * penanda `[[PLACEHOLDER]]` saat berkas dibaca, sehingga halaman tetap
 * berupa berkas statis murni di repositori.
 */
final class StaticPage
{
    /**
     * @param  array<string, string>  $replacements
     */
    public static function render(string $file, array $replacements = []): Response
    {
        $contents = file_get_contents(resource_path($file));

        return new Response(strtr($contents, $replacements), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
