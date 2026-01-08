<?php

namespace App\Services\Task\DataExtraction;

use Illuminate\Support\Str;

/**
 * Centralized service for traversing and extracting data from fragment_selector structures.
 * Fragment selectors define hierarchical paths through extraction schemas.
 */
class FragmentSelectorService
{
    /**
     * Get all nesting keys from fragment_selector in traversal order.
     * For: provider > care_summary > professional > {fields}
     * Returns: ['provider', 'care_summary', 'professional']
     */
    public function getNestingKeys(array $fragmentSelector): array
    {
        $keys     = [];
        $children = $fragmentSelector['children'] ?? [];

        while (!empty($children)) {
            $key   = array_key_first($children);
            $child = $children[$key];

            // Only include keys that represent nested structures (object/array)
            // Stop if this child is a scalar type
            $childType = $child['type'] ?? null;
            if ($childType !== null && !in_array($childType, ['object', 'array'], true)) {
                break;
            }

            $keys[] = $key;

            // Check if children exist and contain nested objects/arrays
            $childChildren = $child['children'] ?? [];
            if (empty($childChildren)) {
                break;
            }

            // Check if any children are nested structures (not all scalars)
            $hasNestedStructure = false;
            foreach ($childChildren as $grandchild) {
                $grandchildType = $grandchild['type'] ?? null;
                if ($grandchildType === null || in_array($grandchildType, ['object', 'array'], true)) {
                    $hasNestedStructure = true;
                    break;
                }
            }

            if (!$hasNestedStructure) {
                break;
            }

            $children = $childChildren;
        }

        return $keys;
    }

    /**
     * Get the leaf (deepest) schema key from fragment_selector.
     * Returns the key whose children are all scalar types.
     *
     * For flat structures (where ALL children at root are scalar types),
     * returns the fallback object_type as snake_case since the root IS the leaf.
     */
    public function getLeafKey(array $fragmentSelector, ?string $fallbackObjectType = null): string
    {
        $children = $fragmentSelector['children'] ?? [];

        // If no children, use fallback
        if (empty($children)) {
            return Str::snake($fallbackObjectType ?? '');
        }

        // Check if ALL children at root level are scalar types (flat structure)
        // If so, the root IS the leaf - no nested hierarchy to traverse
        if ($this->hasOnlyScalarChildren($children)) {
            return Str::snake($fallbackObjectType ?? '');
        }

        // Standard traversal for nested structures
        $lastKey = null;

        while (!empty($children)) {
            $key     = array_key_first($children);
            $lastKey = $key;
            $child   = $children[$key];

            if (!isset($child['children']) || !is_array($child['children'])) {
                break;
            }

            $hasNestedObject = false;
            foreach ($child['children'] as $grandchild) {
                if (isset($grandchild['type']) && in_array($grandchild['type'], ['object', 'array'], true)) {
                    $hasNestedObject = true;
                    break;
                }
            }

            if (!$hasNestedObject) {
                break;
            }

            $children = $child['children'];
        }

        return $lastKey ?? Str::snake($fallbackObjectType ?? '');
    }

    /**
     * Check if all children are scalar types (string, number, boolean, etc.).
     * Returns true if no children have type 'object' or 'array'.
     */
    public function hasOnlyScalarChildren(array $children): bool
    {
        foreach ($children as $child) {
            $childType = $child['type'] ?? null;
            // If type is null, object, or array - it's a nested structure
            if ($childType === null || in_array($childType, ['object', 'array'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the parent type (second-to-last key) from fragment_selector.
     * For: provider > care_summary > professional > {fields}
     * Returns: "Care Summary" (title case)
     */
    public function getParentType(array $fragmentSelector): ?string
    {
        $path = $this->getNestingKeys($fragmentSelector);

        if (count($path) < 2) {
            return null;
        }

        // Parent is second-to-last key, converted to title case
        return Str::title(str_replace('_', ' ', $path[count($path) - 2]));
    }

    /**
     * Check if a specific key represents an array type in the fragment_selector.
     *
     * For flat structures where the key is not found in children (because children
     * contain scalar fields, not the object type itself), returns false since
     * flat structures produce single objects, not arrays.
     */
    public function isArrayType(array $fragmentSelector, string $schemaKey): bool
    {
        $children = $fragmentSelector['children'] ?? [];

        while (!empty($children)) {
            if (isset($children[$schemaKey])) {
                return ($children[$schemaKey]['type'] ?? 'array') === 'array';
            }

            $key      = array_key_first($children);
            $children = $children[$key]['children'] ?? [];
        }

        // Key not found - this happens for flat structures where children are scalar fields
        // (e.g., name, accident_date) and the schemaKey (e.g., "demand") doesn't appear in
        // the hierarchy. Flat structures produce single objects, not arrays.
        return false;
    }

    /**
     * Check if the relationship at a specific nesting level is an array type.
     *
     * Returns true if level not found because hierarchical artifact building
     * (ExtractionArtifactBuilder::nestDataUnderAncestors) typically wraps data in arrays.
     * This differs from isArrayType() which returns false for flat structures.
     */
    public function isArrayTypeAtLevel(array $fragmentSelector, int $level): bool
    {
        $children     = $fragmentSelector['children'] ?? [];
        $currentLevel = 0;

        while (!empty($children)) {
            $key   = array_key_first($children);
            $child = $children[$key];

            if ($currentLevel === $level) {
                return ($child['type'] ?? 'array') === 'array';
            }

            $children = $child['children'] ?? [];
            $currentLevel++;
        }

        // Level not found - for hierarchical artifact building, default to array wrapping
        // since nested structures typically use arrays at each level
        return true;
    }

    /**
     * Check if the leaf level in a group's fragment_selector is an array type.
     */
    public function isLeafArrayType(array $group): bool
    {
        $fragmentSelector = $group['fragment_selector'] ?? [];
        $schemaKey        = $this->getLeafKey($fragmentSelector, $group['object_type'] ?? null);

        return $this->isArrayType($fragmentSelector, $schemaKey);
    }

    /**
     * Unwrap extracted data through fragment_selector hierarchy to get leaf data.
     * Follows the nested path until reaching scalar fields.
     *
     * @param  bool  $preserveLeafArray  If true, preserve array at leaf level instead of taking first element
     */
    public function unwrapData(array $extractedData, array $fragmentSelector, bool $preserveLeafArray = false): array
    {
        $children = $fragmentSelector['children'] ?? [];

        if (empty($children)) {
            return $extractedData;
        }

        $key = array_key_first($children);

        if (!isset($extractedData[$key])) {
            return $extractedData;
        }

        $child     = $children[$key];
        $childData = $extractedData[$key];

        // Check if this child has more nested object/array children
        $childChildren      = $child['children'] ?? [];
        $hasNestedStructure = false;

        foreach ($childChildren as $grandchild) {
            if (isset($grandchild['type']) && in_array($grandchild['type'], ['object', 'array'], true)) {
                $hasNestedStructure = true;
                break;
            }
        }

        // If it's an array, only take first element if NOT at leaf level OR NOT preserving
        if (is_array($childData) && isset($childData[0])) {
            $atLeaf = !$hasNestedStructure;
            if (!$preserveLeafArray || !$atLeaf) {
                $childData = $childData[0];
            }
        }

        if ($hasNestedStructure && !empty($childChildren)) {
            return $this->unwrapData($childData, $child, $preserveLeafArray);
        }

        return is_array($childData) ? $childData : $extractedData;
    }
}
