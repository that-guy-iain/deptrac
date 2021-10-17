<?php

declare(strict_types=1);

namespace Qossmic\Deptrac\Configuration;

use function dirname;
use Qossmic\Deptrac\Configuration\Loader\YmlFileLoader;
use Qossmic\Deptrac\Exception\Configuration\FileCannotBeParsedAsYamlException;
use Qossmic\Deptrac\Exception\Configuration\ParsedYamlIsNotAnArrayException;
use Qossmic\Deptrac\Exception\File\CouldNotReadFileException;
use Qossmic\Deptrac\File\FileHelper;
use Symfony\Component\Config\Definition\Processor;

class Loader
{
    private YmlFileLoader $fileLoader;
    private Processor $processor;
    private FileHelper $workingDirectoryFileHelper;
    private string $workingDirectory;

    public function __construct(YmlFileLoader $fileLoader, string $workingDirectory)
    {
        $this->fileLoader = $fileLoader;
        $this->workingDirectory = $workingDirectory;
        $this->workingDirectoryFileHelper = new FileHelper($workingDirectory);
        $this->processor = new Processor();
    }

    /**
     * @throws CouldNotReadFileException
     * @throws FileCannotBeParsedAsYamlException
     * @throws ParsedYamlIsNotAnArrayException
     */
    public function load(string $file): Configuration
    {
        $absolutePath = $this->workingDirectoryFileHelper->toAbsolutePath($file);
        $depfileDirectory = dirname($absolutePath);

        $configs = [];
        $configs[] = $mainConfig = $this->fileLoader->parseFile($absolutePath);

        $useRelativePathFromDepfile = (bool) ($mainConfig['use_relative_path_from_depfile'] ?? true);
        $fileHelper = $useRelativePathFromDepfile ? new FileHelper($depfileDirectory) : $this->workingDirectoryFileHelper;

        if (isset($mainConfig['baseline'])) {
            $configs[] = $this->fileLoader->parseFile($fileHelper->toAbsolutePath((string) $mainConfig['baseline']));
        }

        if (isset($mainConfig['imports'])) {
            /** @var string $importFile */
            foreach ((array) $mainConfig['imports'] as $importFile) {
                $configs[] = $this->fileLoader->parseFile($fileHelper->toAbsolutePath($importFile));
            }
        }

        $mergedConfig = $this->processor->processConfiguration(new Definition(), $configs);

        $mergedConfig['parameters']['currentWorkingDirectory'] = $this->workingDirectory;
        $mergedConfig['parameters']['depfileDirectory'] = $depfileDirectory;

        /** @var array $analyzer */
        $analyzer = $mergedConfig['analyzer'] ?? [];
        /** @var array $analyser */
        $analyser = $mergedConfig['analyser'] ?? [];

        return Configuration::fromArray([
            'parameters' => $mergedConfig['parameters'],
            'paths' => array_map([$fileHelper, 'toAbsolutePath'], $mergedConfig['paths']),
            'exclude_files' => $mergedConfig['exclude_files'],
            'layers' => $mergedConfig['layers'],
            'ruleset' => $mergedConfig['ruleset'],
            'skip_violations' => $mergedConfig['skip_violations'],
            'ignore_uncovered_internal_classes' => $mergedConfig['ignore_uncovered_internal_classes'],
            'formatters' => $mergedConfig['formatters'] ?? [],
            'analyser' => [] === $analyser ? $analyzer : $analyser,
        ]);
    }
}
