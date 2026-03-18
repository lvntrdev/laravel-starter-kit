<?php

namespace App\Domain\ApiRoute\Queries;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Query: Collect and format all registered API and Service routes.
 */
class ApiRouteListQuery
{
    /**
     * @return array{api: list<array<string, mixed>>, service: list<array<string, mixed>>}
     */
    public function get(): array
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes());

        $api = $routes
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'))
            ->map(fn (Route $route) => $this->formatRoute($route))
            ->values()
            ->all();

        $service = $routes
            ->filter(fn (Route $route) => str_contains($route->getActionName(), 'Controllers\\Service\\'))
            ->map(fn (Route $route) => $this->formatRoute($route))
            ->values()
            ->all();

        return [
            'api' => $api,
            'service' => $service,
        ];
    }

    /**
     * @return array{method: string, uri: string, name: string|null, action: string, middleware: list<string>}
     */
    private function formatRoute(Route $route): array
    {
        $methods = collect($route->methods())
            ->reject(fn (string $m) => $m === 'HEAD')
            ->values()
            ->all();

        $action = $route->getActionName();
        if (str_contains($action, '@')) {
            $parts = explode('@', class_basename(str_replace('\\', '/', $action)));
            $action = implode('@', $parts);
        }

        return [
            'method' => implode('|', $methods),
            'uri' => '/'.$route->uri(),
            'name' => $route->getName(),
            'action' => $action,
            'middleware' => array_values(array_map(
                fn ($m) => is_string($m) ? $m : class_basename($m),
                $route->gatherMiddleware(),
            )),
        ];
    }
}
