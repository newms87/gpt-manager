<?php

namespace App\AiTools;

use Newms87\Danx\Models\Utilities\StoredFile;

class AiToolResponse
{
    /** @var array{string} */
    protected array $content = [];

    /** @var StoredFile[] */
    protected array $storedFiles = [];

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
        $this->storedFiles[] = $storedFile;

        return $this;
    }

    /**
     * @param array{filename: string, url: string} $file
     */
    public function addFile(array $file): static
    {
        $storedFile = StoredFile::create($file);

        return $this->addStoredFile($storedFile);
    }

    public function hasFiles(): bool
    {
        return count($this->storedFiles) > 0;
    }

    /**
     * @return StoredFile[]
     */
    public function getFiles(): array
    {
        return $this->storedFiles;
    }
}
