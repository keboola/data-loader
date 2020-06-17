<?php

declare(strict_types=1);

namespace Keboola\DataLoader\ScriptProcessor;

use stdClass;

class PythonTemplateAdapter implements TemplateAdapter
{
    public function getCommonTemplatePath(): string
    {
        return $templatePath = __DIR__ . '/../../res/notebook-python.ipynb';
    }

    public function getDestinationFile(string $dataDir): string
    {
        // this name must match the one expected in https://github.com/keboola/docker-jupyter
        return $dataDir . '/notebook.ipynb';
    }

    public function processTemplate(string $template, string $script): string
    {
        $templateData = json_decode($template, false, 512, JSON_THROW_ON_ERROR);
        // the ipynb cells are saved as lines, but they still have to contain EOL
        $lines = explode("\n", $script);
        array_walk($lines, function (&$line): void {
            $line .= "\n";
        });
        $templateData->cells[] = [
            'cell_type' => 'code',
            'execution_count' => null,
            'metadata' => new stdClass(),
            'outputs' => [],
            'source' => $lines,
        ];
        $template = json_encode($templateData, JSON_PRETTY_PRINT + JSON_THROW_ON_ERROR);
        return $template;
    }
}
