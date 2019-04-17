<?php

declare(strict_types=1);

namespace Keboola\DataLoader\ScriptProcessor;

interface TemplateAdapter
{
    public function getCommonTemplatePath(): string;

    public function getDestinationFile(string $dataDir): string;

    public function processTemplate(string $template, string $script): string;
}
