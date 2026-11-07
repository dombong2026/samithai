<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SafeHtml
{
    private const ALLOWED = ['p','br','strong','b','em','i','u','s','h2','h3','h4',
        'ul','ol','li','blockquote','pre','code','a','hr'];

    private const REMOVE_CONTENT = ['script','style','iframe','object','embed','svg','math',
        'form','input','button','select','textarea','template','link','meta','base'];

    public static function clean(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<?xml encoding="utf-8"?><div id="samith-content-root">'.$html.'</div>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementById('samith-content-root');
        if (! $root) return '';
        foreach (iterator_to_array($root->childNodes) as $child) self::sanitize($child);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);

        return trim($result);
    }

    private static function sanitize(DOMNode $node): void
    {
        if (! $node instanceof DOMElement) {
            if ($node->nodeType !== XML_TEXT_NODE) $node->parentNode?->removeChild($node);
            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::REMOVE_CONTENT, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) self::sanitize($child);

        if (! in_array($tag, self::ALLOWED, true)) {
            $parent = $node->parentNode;
            if ($parent) {
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node);
            }
            return;
        }

        $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';
        while ($node->attributes->length > 0) $node->removeAttributeNode($node->attributes->item(0));

        if ($tag === 'a' && preg_match('#^https://[^\s<>"\x00-\x1F]+$#iD', $href)) {
            $node->setAttribute('href', $href);
            $node->setAttribute('rel', 'nofollow noopener noreferrer');
            $node->setAttribute('target', '_blank');
        }
    }
}
