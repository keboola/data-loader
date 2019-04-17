<?php

declare(strict_types=1);

namespace Keboola\DataLoader\ScriptProcessor;

class RTemplateAdapter implements TemplateAdapter
{
    public function getCommonTemplatePath(): string
    {
        return $templatePath = __DIR__ . '/../../res/script.R';
    }

    public function getDestinationFile(string $dataDir): string
    {
        // this name must match the one expected in https://github.com/keboola/docker-rstudio
        return $dataDir . '/main.R';
    }

    public function processTemplate(string $template, string $script): string
    {
        return $template . "\n\n" . $script;
    }
}
