<?php

namespace App\Services\Template;

use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * DTO representing the result of template rendering.
 * Unified result structure for both Google Docs and HTML template types.
 */
readonly class TemplateRenderResult
{
    /**
     * @param  string  $type  Template type (google_docs or html)
     * @param  string  $title  Generated document title
     * @param  array<string, string>  $values  Resolved variable values
     * @param  string|null  $url  Google Docs URL (google_docs type only)
     * @param  string|null  $documentId  Google Docs document ID (google_docs type only)
     * @param  StoredFile|null  $storedFile  StoredFile for the generated doc (google_docs type only)
     * @param  string|null  $html  Rendered HTML content (html type only)
     * @param  string|null  $css  CSS content (html type only)
     */
    public function __construct(
        public string $type,
        public string $title,
        public array $values,
        public ?string $url = null,
        public ?string $documentId = null,
        public ?StoredFile $storedFile = null,
        public ?string $html = null,
        public ?string $css = null,
    ) {
    }

    /**
     * Create a result for Google Docs rendering
     */
    public static function googleDocs(
        string $title,
        array $values,
        string $url,
        string $documentId,
        StoredFile $storedFile,
    ): self {
        return new self(
            type: 'google_docs',
            title: $title,
            values: $values,
            url: $url,
            documentId: $documentId,
            storedFile: $storedFile,
        );
    }

    /**
     * Create a result for HTML rendering
     */
    public static function html(
        string $title,
        array $values,
        string $html,
        ?string $css = null,
    ): self {
        return new self(
            type: 'html',
            title: $title,
            values: $values,
            html: $html,
            css: $css,
        );
    }

    /**
     * Check if this is a Google Docs result
     */
    public function isGoogleDocs(): bool
    {
        return $this->type === 'google_docs';
    }

    /**
     * Check if this is an HTML result
     */
    public function isHtml(): bool
    {
        return $this->type === 'html';
    }
}
