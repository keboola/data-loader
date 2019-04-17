<?php

declare(strict_types=1);

namespace Keboola\DataLoader\ScriptProcessor;

class PythonTemplateAdapter implements TemplateAdapter
{
    public function getCommonTemplatePath(): string
    {
        return $templatePath = __DIR__ . '/../../res/notebook.ipynb';
    }

    public function getDestinationFile(string $dataDir): string
    {
        // this name must match the one expected in https://github.com/keboola/docker-jupyter
        return $dataDir . '/notebook.ipynb';
    }

    public function processTemplate(string $template, string $script): string
    {
        $templateData = json_decode($template, false, 512, JSON_THROW_ON_ERROR);
        $templateData->cells[] = [
            'cell_type' => 'code',
            'execution_count' => null,
            'metadata' => new \stdClass(),
            'outputs' => [],
            'source' => explode("\n", $script),
        ];
        $template = json_encode($templateData, JSON_PRETTY_PRINT + JSON_THROW_ON_ERROR);
        return $template;
    }
}
