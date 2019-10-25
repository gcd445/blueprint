<?php

namespace Blueprint\Generators;

use Blueprint\Model;

class FactoryGenerator
{
    public function output(array $tree)
    {
        // TODO: what if changing an existing model
        $stub = file_get_contents('stubs/factory.stub');

        /** @var \Blueprint\Model $model */
        foreach ($tree['models'] as $model) {
            file_put_contents(
                $this->getPath($model),
                $this->populateStub($stub, $model)
            );
        }
    }

    protected function getPath(Model $model)
    {
        return 'build/' . $model->name() . 'Factory.php';
    }

    protected function populateStub(string $stub, Model $model)
    {
        $stub = str_replace('DummyNamespace', 'App', $stub);
        $stub = str_replace('DummyClass', $model->name(), $stub);
        $stub = str_replace('// definition...', $this->buildDefinition($model), $stub);

        return $stub;
    }

    protected function buildDefinition(Model $model)
    {
        $definition = '';

        /** @var \Blueprint\Column $column */
        foreach ($model->columns() as $column) {
            if ($column->name() === 'id') {
                continue;
            }

            $definition .= "'{$column->name()}' => ";
            $faker = $this->fakerData($column->name()) ?? $this->fakerDataType($column->dataType());
            $definition .= '$faker->' . $faker;
            $definition .= ',' . PHP_EOL;
        }

        return trim($definition);
    }

    protected function fakerData(string $name)
    {
        static $fakeableNames = [
            'city' => 'city',
            'company' => 'company',
            'country' => 'country',
            'description' => 'text',
            'email' => 'safeEmail',
            'first_name' => 'firstName',
            'firstname' => 'firstName',
            'guid' => 'uuid',
            'last_name' => 'lastName',
            'lastname' => 'lastName',
            'lat' => 'latitude',
            'latitude' => 'latitude',
            'lng' => 'longitude',
            'longitude' => 'longitude',
            'name' => 'name',
            'password' => 'password',
            'phone' => 'phoneNumber',
            'phone_number' => 'phoneNumber',
            'postcode' => 'postcode',
            'postal_code' => 'postcode',
            'slug' => 'slug',
            'street' => 'streetName',
            'address1' => 'streetAddress',
            'address2' => 'secondaryAddress',
            'summary' => 'text',
            'title' => 'sentence(4)',
            'url' => 'url',
            'user_name' => 'userName',
            'username' => 'userName',
            'uuid' => 'uuid',
            'zip' => 'postcode',
        ];

        return $fakeableNames[$name] ?? null;
    }

    protected function fakerDataType(string $type)
    {
        $fakeableTypes = [
            'string' => 'word',
            'text' => 'text',
            'date' => 'date()',
            'time' => 'time()',
            'guid' => 'word',
            'datetimetz' => 'dateTime()',
            'datetime' => 'dateTime()',
            'timestamp' => 'dateTime()',
            'integer' => 'randomNumber()',
            'bigint' => 'randomNumber()',
            'smallint' => 'randomNumber()',
            'decimal' => 'randomFloat()',
            'float' => 'randomFloat()',
            'boolean' => 'boolean'
        ];

        return $fakeableTypes[$type] ?? null;
    }
}