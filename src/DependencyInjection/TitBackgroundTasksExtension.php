<?php

namespace Tit\BackgroundTasksBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class TitBackgroundTasksExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $env = $container->getParameter("kernel.environment");

        $loader = new XmlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );
        $loader->load('services.xml');

        $yamlLoader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );

        $yamlLoader->load('parameters.yaml');
    }
}