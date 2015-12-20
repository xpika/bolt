<?php

namespace Bolt\Composer;

use Bolt\Extension\ExtensionInterface;
use Bolt\Extension\ResolvedExtension;
use Bolt\Filesystem\Exception\FileNotFoundException;
use Bolt\Filesystem\Exception\IncludeFileException;
use Bolt\Filesystem\FilesystemInterface;
use Bolt\Filesystem\Handler\File;
use Bolt\Filesystem\Handler\JsonFile;

/**
 * Class to manage autoloading functionality for extensions.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class ExtensionFinder
{
    /** @var array */
    protected $extensions;

    /** @var FilesystemInterface */
    private $filesystem;

    /**
     * Constructor.
     *
     * @param FilesystemInterface $filesystem
     */
    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Load a collection of extension classes.
     *
     * @return \Bolt\Extension\ResolvedExtension[]
     */
    public function load()
    {
        $classes = [];

        /** @var JsonFile $autoloadJson */
        $autoloadJson = $this->filesystem->get('autoload.json');
        if (!$autoloadJson->exists()) {
            return $classes;
        }

        try {
            $this->filesystem->includeFile('vendor/autoload.php');
        } catch (IncludeFileException $e) {
            return $classes;
        }

        foreach ($autoloadJson->parse() as $loader) {
            if (class_exists($loader['class'])) {
                /** @var ExtensionInterface $class */
                $class = new $loader['class']();
                if ($class instanceof ExtensionInterface) {
                    $name = $class->getName();
                    $classes[$name] = new ResolvedExtension($class);
                }
            }
        }

        return $classes;
    }

    /**
     * Build the autoload data for all extensions.
     */
    public function build()
    {
        /** @var File $file */
        foreach ($this->getComposerJson() as $file) {
            /** @var JsonFile $jsonFile */
            $jsonFile = $this->filesystem->get($file->getPath());
            $this->parseComposerJson($jsonFile);
        }

        $this->filesystem
            ->get('autoload.json', new JsonFile())
            ->dump($this->extensions)
        ;
    }

    /**
     * Load the extensions meta data from the composer.json file.
     *
     * @param JsonFile $jsonFile
     */
    protected function parseComposerJson(JsonFile $jsonFile)
    {
        $jsonData = $jsonFile->parse();
        $key = $jsonData['name'];
        $this->extensions[$key] = [
            'name'  => $jsonData['name'],
            'class' => $jsonData['extra']['bolt-class'],
            'path'  => $jsonFile->getDirname(),
        ];
    }

    /**
     * Return the collective composer.json files for all installed extensions.
     *
     * @return \Bolt\Filesystem\Finder
     */
    protected function getComposerJson()
    {
        $finder = $this->filesystem
            ->find()
            ->files()
            ->notPath('vendor/composer')
            ->depth(2)
            ->name('composer.json')
            ->contains('"bolt-class"')
        ;
        try {
            $finder->in('vendor');
        } catch (FileNotFoundException $e) {
            // Composer has not had autoloader dumped
        }
        try {
            $finder->in('local');
        } catch (FileNotFoundException $e) {
            // No local extensions are installed
        }

        return $finder;
    }
}
