<?php

namespace Blueprint\Generators\Statements;

use Blueprint\Blueprint;
use Blueprint\Contracts\Generator;
use Blueprint\Generators\StatementGenerator;
use Blueprint\Models\Controller;
use Blueprint\Models\Model;
use Blueprint\Models\Statements\ResourceStatement;
use Blueprint\Tree;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ResourceGenerator extends StatementGenerator implements Generator
{
    const INDENT = '            ';

    protected array $types = ['controllers', 'resources'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        $stub = $this->filesystem->stub('resource.stub');

        /**
         * @var \Blueprint\Models\Controller $controller
         */
        foreach ($tree->controllers() as $controller) {
            foreach ($controller->methods() as $statements) {
                foreach ($statements as $statement) {
                    if (!$statement instanceof ResourceStatement) {
                        continue;
                    }

                    $path = $this->getStatementPath(($controller->namespace() ? $controller->namespace() . '/' : '') . $statement->name());

                    if ($this->filesystem->exists($path)) {
                        continue;
                    }

                    $this->create($path, $this->populateStub($stub, $controller, $statement));
                }
            }
        }

        return $this->output;
    }

    protected function getStatementPath(string $name): string
    {
        return Blueprint::appPath() . '/Http/Resources/' . $name . '.php';
    }

    protected function populateStub(string $stub, Controller $controller, ResourceStatement $resource): string
    {
        $namespace = config('blueprint.namespace')
            . '\\Http\\Resources'
            . ($controller->namespace() ? '\\' . $controller->namespace() : '');

        $imports = ['use Illuminate\\Http\\Request;'];
        $imports[] = $resource->collection() && $resource->generateCollectionClass() ? 'use Illuminate\\Http\\Resources\\Json\\ResourceCollection;' : 'use Illuminate\\Http\\Resources\\Json\\JsonResource;';

        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ imports }}', implode(PHP_EOL, $imports), $stub);
        $stub = str_replace('{{ parentClass }}', $resource->collection() && $resource->generateCollectionClass() ? 'ResourceCollection' : 'JsonResource', $stub);
        $stub = str_replace('{{ class }}', $resource->name(), $stub);
        $stub = str_replace('{{ parentClass }}', $resource->collection() ? 'ResourceCollection' : 'JsonResource', $stub);
        $stub = str_replace('{{ collectionWrap }}', $resource->collection() ? 'public static $wrap = null;' : '', $stub); // TODO: make this configurable in config to be "data",plural model name,or null
        $stub = str_replace('{{ resource }}', $resource->collection() ? 'resource collection' : 'resource', $stub);
        $stub = str_replace('{{ body }}', $this->buildData($resource), $stub);

        return $stub;
    }

    protected function buildData(ResourceStatement $resource): string
    {
        $context = Str::singular($resource->reference());

        /**
         * @var \Blueprint\Models\Model $model
         */
        $model = $this->tree->modelForContext($context, true);

        $data = [];
        if ($resource->collection()) {
            $data[] = 'return $this->collection->toArray();';
            $data[] = '//      return [';
            $data[] = '//' . self::INDENT . '\'' . $resource->reference() . '\' => $this->collection,'; // TODO: make this configurable in config to be "data",plural model name,or null
            $data[] = '//      ];';

            return implode(PHP_EOL, $data);
        }

        $data[] = 'return [';
        foreach ($this->visibleColumns($model) as $column) {
            $data[] = self::INDENT . '\'' . $column . '\' => ' . (config('blueprint.when_not_null') ? '$this->whenNotNull($this->' . $column . ')' : '$this->' . $column) . ',';
        }

        foreach ($model->relationships() as $type => $relationships) {
            foreach ($relationships as $relationship) {
                // $method_name = lcfirst(Str::afterLast(Arr::last($relationship), '\\'));
                $method_name = lcfirst(Str::afterLast($relationship, '\\'));

                $relation_model = $this->tree->modelForContext($method_name);

                if ($relation_model === null) {
                    continue;
                }

                if (in_array($type, ['hasMany', 'belongsToMany', 'morphMany'])) {
                    $relation_resource_name = $relation_model->name() . 'Collection';
                    $method_name = Str::plural($method_name);
                } else {
                    $relation_resource_name = $relation_model->name() . 'Resource';
                }

                $data[] = self::INDENT . '\'' . $method_name . '\' => ' . $relation_resource_name . '::make($this->whenLoaded(\'' . $method_name . '\')),';
            }
        }
        // 'created_at' => $this->whenNotNull($this->created_at),

        $data[] = self::INDENT . '\'' . 'updated_at' . '\' => ' . (config('blueprint.when_not_null') ? '$this->whenNotNull($this->' . 'updated_at' . ')' : '$this->' . 'updated_at') . ',';
        $data[] = self::INDENT . '\'' . 'created_at' . '\' => ' . (config('blueprint.when_not_null') ? '$this->whenNotNull($this->' . 'created_at' . ')' : '$this->' . 'created_at') . ',';
        if ($model->usesSoftDeletes()) {
            $data[] = self::INDENT . '\'' . 'deleted_at' . '\' => ' . (config('blueprint.when_not_null') ? '$this->whenNotNull($this->' . 'deleted_at' . ')' : '$this->' . 'deleted_at') . ',';
        }

        $data[] = '        ];';

        return implode(PHP_EOL, $data);
    }

    protected function visibleColumns(Model $model): array
    {
        return array_diff(
            array_keys($model->columns()),
            [
                'password',
                'remember_token',
            ]
        );
    }
}
