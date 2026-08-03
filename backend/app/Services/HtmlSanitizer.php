<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans rich-text HTML coming from the TipTap editor (article content,
 * villa/homestay/gathering-venue/transport descriptions) before it's stored.
 * The editor UI only ever produces safe markup, but the API itself must not
 * trust that — a request can always be crafted by hand — so everything not
 * on this allow-list is stripped server-side regardless of what the client sent.
 */
class HtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return static::purifier()->purify($html);
    }

    private static function purifier(): HTMLPurifier
    {
        if (static::$purifier !== null) {
            return static::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,h2,h3,h4,ul,ol,li,blockquote,a[href|target]');
        $config->set('HTML.TargetBlank', true);
        // Skip disk-backed definition caching — avoids depending on a writable
        // storage path in production; the allow-list is small enough that
        // rebuilding it per-request has no meaningful cost.
        $config->set('Cache.DefinitionImpl', null);

        return static::$purifier = new HTMLPurifier($config);
    }
}
