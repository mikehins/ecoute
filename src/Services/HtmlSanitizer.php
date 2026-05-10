<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

final class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();

        // Disable caching in environments that restrict filesystem writes.
        // When caching is disabled, do NOT set HTML.DefinitionID or HTML.DefinitionRev.
        $config->set('Cache.DefinitionImpl', null);

        // Restrict to safe element subset.
        // 'button' is not in HTMLPurifier's built-in modules; we register it via
        // getHTMLDefinition(true) below.
        $config->set('HTML.AllowedElements', 'div,span,p,ul,ol,li,button,img,a');

        // Allowlist only safe structural attributes; element-specific attributes for img/a.
        // data-* attributes are NOT included — they are stripped because they are not in
        // this allowlist, and are stored separately in the `attributes` column.
        $config->set('HTML.AllowedAttributes', '*.id,*.class,img.src,img.alt,a.href');

        // Explicitly enable the id attribute; it can get reset when using custom definitions.
        $config->set('Attr.EnableID', true);

        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        // IMPORTANT: getHTMLDefinition(true) must be called AFTER all set() calls.
        // Register 'button' as a custom inline element — HTMLPurifier's built-in modules do not
        // include it. 'Common' provides the id and class attribute collection.
        $def = $config->getHTMLDefinition(true);
        $def->addElement('button', 'Inline', 'Inline', 'Common', []);

        $this->purifier = new HTMLPurifier($config);
    }

    /**
     * Sanitize an HTML string, stripping unsafe elements, event handlers, and data-* attributes.
     *
     * @param  string  $html  Raw HTML to sanitize.
     * @return string Sanitized HTML safe for storage and AI processing.
     */
    public function sanitize(string $html): string
    {
        return $this->purifier->purify($html);
    }
}
