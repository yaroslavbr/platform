<?php

namespace Oro\Bundle\NavigationBundle\ContentProvider;

use Oro\Bundle\NavigationBundle\Configuration\ConfigurationProvider;
use Oro\Bundle\UIBundle\ContentProvider\AbstractContentProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The content provider that return navigation elements.
 */
class NavigationElementsContentProvider extends AbstractContentProvider
{
    /** @var ConfigurationProvider */
    private $configurationProvider;

    /** @var RequestStack */
    protected $requestStack;

    /**
     * @param ConfigurationProvider $configurationProvider
     * @param RequestStack          $requestStack
     */
    public function __construct(ConfigurationProvider $configurationProvider, RequestStack $requestStack)
    {
        $this->configurationProvider = $configurationProvider;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        $navigationElements = $this->configurationProvider->getNavigationElements();

        $elements = array_keys($navigationElements);
        $defaultValues = $values = array_map(
            function ($item) {
                return $item['default'];
            },
            $navigationElements
        );

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $attributes = $request->attributes;

            $routeName  = $attributes->get('_route');
            if (!$routeName) {
                $routeName = $attributes->get('_master_request_route') ?: '' ;
            }

            $hasErrors  = $attributes->get('exception');

            foreach ($elements as $elementName) {
                $value = $defaultValues[$elementName] && (!$hasErrors);
                if ($this->hasConfigValue($elementName, $routeName)) {
                    $value = $this->getConfigValue($elementName, $routeName) && (!$hasErrors);
                }

                $values[$elementName] = $value;
            }
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'navigationElements';
    }

    /**
     * @param string $element
     * @param string $route
     *
     * @return bool
     */
    protected function hasConfigValue($element, $route)
    {
        $navigationElements = $this->configurationProvider->getNavigationElements();

        return isset($navigationElements[$element]['routes'][$route]);
    }

    /**
     * @param string $element
     * @param string $route
     *
     * @return null|bool
     */
    protected function getConfigValue($element, $route)
    {
        $navigationElements = $this->configurationProvider->getNavigationElements();

        return isset($navigationElements[$element]['routes'][$route])
            ? (bool)$navigationElements[$element]['routes'][$route]
            : null;
    }
}
