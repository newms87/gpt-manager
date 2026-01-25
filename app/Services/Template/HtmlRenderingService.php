<?php

namespace App\Services\Template;

use App\Models\Template\TemplateDefinition;
use Newms87\Danx\Traits\HasDebugLogging;
use DOMDocument;
use DOMXPath;

/**
 * Service responsible for rendering HTML templates.
 * Replaces data-var-{name} attribute content with resolved values.
 */
class HtmlRenderingService
{
    use HasDebugLogging;

    /**
     * Render an HTML template with the provided resolved values
     *
     * @param  TemplateDefinition  $template  The template definition
     * @param  array<string, string>  $resolvedValues  Map of variable names to resolved values
     * @return array{html: string, css: string|null}
     */
    public function render(TemplateDefinition $template, array $resolvedValues): array
    {
        static::logDebug('Starting HTML rendering', [
            'template_id'  => $template->id,
            'values_count' => count($resolvedValues),
        ]);

        $html = $template->html_content ?? '';
        $css  = $template->css_content;

        if (empty($html)) {
            static::logDebug('Template has no HTML content');

            return ['html' => '', 'css' => $css];
        }

        // Replace data-var-* attributes with resolved values
        $renderedHtml = $this->replaceVariables($html, $resolvedValues);

        static::logDebug('HTML rendering complete', [
            'original_length' => strlen($html),
            'rendered_length' => strlen($renderedHtml),
            'variables_count' => count($resolvedValues),
        ]);

        return [
            'html' => $renderedHtml,
            'css'  => $css,
        ];
    }

    /**
     * Replace data-var-* attribute content with resolved values using DOM manipulation
     */
    protected function replaceVariables(string $html, array $resolvedValues): string
    {
        if (empty($resolvedValues)) {
            return $html;
        }

        // Use DOMDocument to properly parse and manipulate HTML
        $doc = new DOMDocument();

        // Suppress warnings for HTML5 tags and load HTML
        // Wrap in a container to handle fragments
        $wrappedHtml = '<!DOCTYPE html><html><body>' . $html . '</body></html>';

        // Use libxml error handling to suppress warnings
        libxml_use_internal_errors(true);
        $doc->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Find all elements with data-var-* attributes
        foreach ($resolvedValues as $varName => $value) {
            $attributeName = 'data-var-' . $varName;

            // Find all elements with this specific data-var attribute
            $nodes = $xpath->query("//*[@{$attributeName}]");

            foreach ($nodes as $node) {
                // Replace the innerHTML with the resolved value
                $this->setInnerHtml($node, (string)$value);

                static::logDebug('Replaced variable in element', [
                    'variable'     => $varName,
                    'tag'          => $node->nodeName,
                    'value_length' => strlen((string)$value),
                ]);
            }
        }

        // Extract the body content (our original HTML)
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return $html;
        }

        // Get innerHTML of body
        $renderedHtml = '';
        foreach ($body->childNodes as $child) {
            $renderedHtml .= $doc->saveHTML($child);
        }

        return $renderedHtml;
    }

    /**
     * Set the innerHTML of a DOM element
     */
    protected function setInnerHtml(\DOMElement $element, string $html): void
    {
        // Remove all existing child nodes
        while ($element->hasChildNodes()) {
            $element->removeChild($element->firstChild);
        }

        // If the value contains HTML, parse it and append nodes
        if (str_contains($html, '<') && str_contains($html, '>')) {
            // Create a temporary document to parse the HTML fragment
            $tempDoc = new DOMDocument();
            libxml_use_internal_errors(true);
            $tempDoc->loadHTML(
                '<!DOCTYPE html><html><body>' . $html . '</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
            libxml_clear_errors();

            $tempBody = $tempDoc->getElementsByTagName('body')->item(0);
            if ($tempBody) {
                foreach ($tempBody->childNodes as $child) {
                    $importedNode = $element->ownerDocument->importNode($child, true);
                    $element->appendChild($importedNode);
                }
            }
        } else {
            // Plain text - create a text node
            $textNode = $element->ownerDocument->createTextNode($html);
            $element->appendChild($textNode);
        }
    }
}
