<?php

namespace ApiPlatform\Core\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Injects query extensions.
 *
 * @internal
 *
 * @author Samuel ROZE <samuel.roze@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class FosElasticaQueryExtensionPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!isset($bundles['FOSElasticaBundle'])) {
            return;
        }

        $collectionDataProviderDefinition = $container->getDefinition('api_platform.elastica.collection_data_provider');
        $collectionExtensions = $this->findAndSortTaggedServices('api_platform.elastica.query_extension.collection', $container);
        $collectionDataProviderDefinition->replaceArgument(2, $collectionExtensions);
    }
}
