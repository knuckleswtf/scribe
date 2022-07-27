<?php

namespace Knuckles\Scribe\Attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ResponseFromFile
{
    public function __construct(
        public int $status = 200,
        public ?string $file = null,
        public array $merge = [],
        public ?string $description = '',
    ) {
    }

    public function toArray()
    {
        return  [
            "status" => $this->status,
            "description" => $this->description,
            "content" => $this->getFileResponse($this->file, $this->merge)
        ];
    }

    protected function getFileResponse($filePath, array $merge): string
    {
        if (!file_exists($filePath)) {
            if (!file_exists(storage_path($filePath))) {
                throw new \InvalidArgumentException("@responseFile {$filePath} does not exist");
            }

            $filePath = storage_path($filePath);
        }

        $content = file_get_contents($filePath, true);
        if (!empty($merge)) {
            $content = json_encode(array_merge(json_decode($content, true), $merge));
        }
        return $content;
    }
}
