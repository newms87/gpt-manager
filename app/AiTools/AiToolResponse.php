<?php

namespace App\AiTools;

use Newms87\Danx\Models\Utilities\StoredFile;

class AiToolResponse
{
    /** @var array{string} */
    protected array $content = [];

    /** @var array{name: string, url: string}[] */
    protected array $files = [];

    public function addContent(string $content): static
    {
        $this->content[] = $content;

        return $this;
    }

    /**
     * @return array{string}
     */
    public function getContentItems(): array
    {
        return $this->content;
    }

    public function addStoredFile(StoredFile $storedFile): static
    {
        return $this->addFile([
            'name' => $storedFile->filename,
            'url'  => $storedFile->url,
        ]);
    }

    /**
     * @param array{name: string, url: string} $file
     */
    public function addFile(array $file): static
    {
        $this->files[] = $file;

        return $this;
    }

    public function hasFiles(): bool
    {
        return count($this->files) > 0;
    }

    /**
     * @return array{name: string, url: string}[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
