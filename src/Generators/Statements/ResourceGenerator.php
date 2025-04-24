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

        $yesToAll = false; // Flag to track "yes to all"
        $noToAll = false; // Flag to track "no to all"


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

                    // If the file exists, prompt the user unless "yes to all" or "no to all" is already set
                    if ($this->filesystem->exists($path)) {
                        if ($yesToAll) {
                            // Overwrite all files
                            $this->create($path, $this->populateStub($stub, $controller, $statement));
                            continue; // Skip to the next iteration of the outer loop
                        }

                        if ($noToAll) {
                            // Skip all files
                            continue; // Skip to the next iteration of the outer loop
                        }

                        // Ask the user for input
                        echo "The file at '$path' already exists. Overwrite? (y = yes, n = no, a = all, na = not all): ";
                        $input = strtolower(trim(readline()));

                        switch ($input) {
                            case 'y': // Overwrite this file
                                echo "Overwriting '$path'...\n";
                                $this->create($path, $this->populateStub($stub, $controller, $statement));
                                break; // Exit the switch block
                            case 'n': // Skip this file
                                echo "Skipping '$path'...\n";
                                continue 2; // Skip to the next iteration of the outer loop
                            case 'a': // Overwrite all files from now on
                                echo "Overwriting all files from now on...\n";
                                $yesToAll = true;
                                $this->create($path, $this->populateStub($stub, $controller, $statement));
                                break; // Exit the switch block
                            case 'na': // Skip all files from now on
                                echo "Skipping all files from now on...\n";
                                $noToAll = true;
                                continue 2; // Skip to the next iteration of the outer loop
                            default: // Invalid input
                                echo "Invalid input. Skipping '$path'...\n";
                                continue 2; // Skip to the next iteration of the outer loop
                        }
                    } else {
                        // Create a new file if it doesn't exist
                        $this->create($path, $this->populateStub($stub, $controller, $statement));
                    }
                    // if ($this->filesystem->exists($path)) {
                    //     continue;
                    // }

                    // $this->create($path, $this->populateStub($stub, $controller, $statement));
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
