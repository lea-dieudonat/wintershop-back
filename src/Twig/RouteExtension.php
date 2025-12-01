<?php

namespace App\Twig;

use App\Constant\Route;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RouteExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('route', [$this, 'getRoute']),
            new TwigFunction('r', [$this, 'getRoute']), // Short alias
        ];
    }

    /**
     * Get a Route enum case by name
     * Usage in Twig: {{ path(route('PRODUCT').show) }}
     * Or shorter: {{ path(r('PRODUCT').show) }}
     */
    public function getRoute(string $name): Route
    {
        return Route::{$name};
    }
}
