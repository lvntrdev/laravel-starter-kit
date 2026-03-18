<?php

namespace App\Domain\Role\Queries;

use App\Models\Permission;

/**
 * Query: Group all permissions by category, then by resource prefix.
 */
class GroupedPermissionsQuery
{
    /**
     * @return array<string, array{label: string, resources: array<string, string[]>}>
     */
    public function get(): array
    {
        $permissionGroups = config('permission-resources.permission_groups', []);
        $groupDisplayNames = config('permission-resources.display_names.groups', []);
        $locale = app()->getLocale();

        // Build a resource → group lookup
        $resourceGroupMap = [];
        foreach ($permissionGroups as $group => $resources) {
            foreach ($resources as $resource) {
                $resourceGroupMap[$resource] = $group;
            }
        }

        // Group permissions by resource
        $byResource = Permission::all()
            ->pluck('name')
            ->groupBy(fn (string $name) => str_contains($name, '.')
                ? explode('.', $name, 2)[0]
                : '_general'
            );

        // Distribute resources into permission groups
        $result = [];
        foreach ($byResource as $resource => $permissions) {
            $group = $resourceGroupMap[$resource] ?? 'other';

            if (! isset($result[$group])) {
                $result[$group] = [
                    'label' => $groupDisplayNames[$group][$locale]
                        ?? $groupDisplayNames[$group]['en']
                        ?? ucfirst($group),
                    'resources' => [],
                ];
            }

            $result[$group]['resources'][$resource] = $permissions->values()->all();
        }

        // Sort by config order
        $ordered = [];
        foreach (array_keys($permissionGroups) as $group) {
            if (isset($result[$group])) {
                $ordered[$group] = $result[$group];
                unset($result[$group]);
            }
        }
        // Append any remaining groups
        foreach ($result as $group => $data) {
            $ordered[$group] = $data;
        }

        return $ordered;
    }
}
