<?php

namespace Support\Docs;

use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

/** Makes an explicit Scribe response-field type authoritative when its example value is null. */
final class DeclaredResponseFieldTypesGenerator extends OpenApiGenerator
{
    /**
     * @param  array<string, mixed>  $pathItem
     * @param  array<int, array{description: string, name: string, endpoints: OutputEndpointData[]}>  $groupedEndpoints
     * @return array<string, mixed>
     */
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        if (isset($pathItem['responses']) && is_array($pathItem['responses'])) {
            foreach ($pathItem['responses'] as &$response) {
                if (! isset($response['content']) || ! is_array($response['content'])) {
                    continue;
                }

                foreach ($response['content'] as &$content) {
                    if (isset($content['schema']) && is_array($content['schema'])) {
                        $content['schema'] = $this->applyDeclaredTypes($content['schema'], $endpoint);
                    }
                }
            }
        }

        return $pathItem;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function applyDeclaredTypes(array $schema, OutputEndpointData $endpoint, string $path = ''): array
    {
        $field = $path === '' ? null : ($endpoint->responseFields[$path] ?? null);
        if ($field !== null && $field->type !== '') {
            $schema['type'] = match (strtolower($field->type)) {
                'int', 'integer' => 'integer',
                'bool', 'boolean' => 'boolean',
                'double', 'float', 'number' => 'number',
                'array', 'list' => 'array',
                'object' => 'object',
                default => 'string',
            };
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $property) {
                if (is_array($property)) {
                    $childPath = $path === '' ? (string) $name : $path.'.'.$name;
                    $schema['properties'][$name] = $this->applyDeclaredTypes($property, $endpoint, $childPath);
                }
            }
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->applyDeclaredTypes($schema['items'], $endpoint, $path);
        }

        return $schema;
    }
}
