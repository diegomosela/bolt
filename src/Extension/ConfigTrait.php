<?php

namespace Bolt\Extension;

use Bolt\Filesystem\Exception\RuntimeException;
use Bolt\Filesystem\Handler\YamlFile;
use Bolt\Helpers\Arr;
use Pimple as Container;

/**
 * Config file handling for extensions.
 *
 * @author Carson Full <carsonfull@gmail.com>
 */
trait ConfigTrait
{
    /** @var array */
    private $config;

    /**
     * Override this to provide a default configuration,
     * which will be used in the absence of a config file.
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        return [];
    }

    /**
     * Returns the config for the extension.
     *
     * @return array
     */
    protected function getConfig()
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $this->config = $this->getDefaultConfig();

        $app = $this->getContainer();
        $filesystem = $app['filesystem'];

        $file = new YamlFile();
        $filesystem->getFile(sprintf('config://extensions/%s.%s.yml', strtolower($this->getName()), strtolower($this->getVendor())), $file);

        if (!$file->exists()) {
            $this->copyDistFile($file);
        }

        $this->addConfig($file);

        $localFile = new YamlFile();
        $file->getParent()->getFile($file->getFilename('.yml') . '_local.yml', $localFile);
        if ($localFile->exists()) {
            $this->addConfig($localFile);
        }

        return $this->config;
    }

    /**
     * Merge in a yaml file to the config.
     *
     * @param YamlFile $file
     */
    private function addConfig(YamlFile $file)
    {
        $app = $this->getContainer();

        try {
            $newConfig = $file->parse();
        } catch (RuntimeException $e) {
            $app['logger.flash']->error($e->getMessage());
            $app['logger.system']->error($e->getMessage(), ['event' => 'exception', 'exception' => $e]);
            throw $e;
        }

        if (is_array($newConfig)) {
            $this->config = Arr::mergeRecursiveDistinct($this->config, $newConfig);
        }
    }

    /**
     * Copy config.yml.dist to config/extensions.
     *
     * @param YamlFile $file
     */
    private function copyDistFile(YamlFile $file)
    {
        $app = $this->getContainer();
        $filesystem = $app['filesystem']->getFilesystem('extensions');
        $relativePath = $filesystem->getAdapter()->removePathPrefix($this->getPath());

        /** @var YamlFile $distFile */
        $distFile = $filesystem->get(sprintf('%s/config.yml.dist', $relativePath), new YamlFile());
        if (!$distFile->exists()) {
            return;
        }
        $file->write($distFile->read());
        $app['logger.system']->info(
            sprintf('Copied %s to %s', $distFile->getFullPath(), $file->getFullPath()),
            ['event' => 'extensions']
        );
    }

    /** @return string */
    abstract public function getName();

    /** @return string */
    abstract public function getVendor();

    /** @return string */
    abstract protected function getPath();

    /** @return Container */
    abstract protected function getContainer();
}
