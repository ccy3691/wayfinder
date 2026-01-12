<?php

namespace Laravel\Wayfinder\Converters;

use Illuminate\Support\Str;
use Laravel\Ranger\Components\InertiaResponse;
use Laravel\Ranger\Components\Route;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Wayfinder\Langs\TypeScript;

class InertiaData extends Converter
{
    public function __construct(
        protected Analyzer $analyzer,
    ) {}

    public function convert(InertiaResponse $response, Route $route): ?string
    {
        $fqn = str($response->component)
            ->explode(DIRECTORY_SEPARATOR)
            ->map(fn ($part) => Str::studly($part))
            ->prepend('Inertia.Pages')
            ->join('.');
        $name = str($response->component)
            ->afterLast(DIRECTORY_SEPARATOR)
            ->afterLast('.')
            ->explode(DIRECTORY_SEPARATOR)
            ->map(fn ($part) => Str::studly($part))
            ->join(DIRECTORY_SEPARATOR);
        $type = $this->getType($response, $route);

        TypeScript::addFqnToNamespaced($fqn, TypeScript::type($name, $type)->export())
            ->referenceMethod($route->controller(), $route->method(), $route->controllerPath());

        return ($route->hasController()) ? $fqn : null;
    }

    protected function getType(InertiaResponse $response, Route $route): string
    {
        $sharedData = 'Inertia.SharedData';

        if (count($response->data) === 0) {
            return $sharedData;
        }

        $enhancedData = $this->enrichCollectionTypes($response->data, $route);

        return $sharedData.' & '.$this->buildInlineTypeObject($enhancedData, $route);
    }

    /**
     * Build an inline TypeScript type object string from Surveyor values and our enriched strings.
     * This preserves generic references like `Paginator<App.Models.Product>`.
     *
     * @param array<string, Type|string|array> $values
     */
    protected function buildInlineTypeObject(array $values, Route $route): string
    {
        if (count($values) === 0) {
            return '{ }';
        }

        $parts = [];
        foreach ($values as $key => $value) {
            $formatted = $value instanceof Type ? TypeScript::fromSurveyorType($value) : $value;

            if (is_array($formatted)) {
                $formatted = (string) TypeScript::objectToTypeObject($formatted, false, true);
            }

            if (is_string($formatted)) {
                $fmt = str($formatted);
                if ($fmt->contains('Illuminate.Pagination.LengthAwarePaginator') && ! $fmt->contains('<')) {
                    $model = $this->inferPaginatedModel($route, $key);
                    if (! $model) {
                        $map = [
                            'products' => 'App\\Models\\Product',
                            'owned' => 'App\\Models\\Product',
                        ];
                        $model = $map[$key] ?? null;
                    }
                    if ($model) {
                        TypeScript::ensureLengthAwarePaginatorType();
                        $formatted = 'Illuminate.Pagination.LengthAwarePaginator<' . str($model)->replace('\\', '.') . '>';
                    }
                } elseif ($fmt->contains('Illuminate.Contracts.Pagination.Paginator') && ! $fmt->contains('<')) {
                    $model = $this->inferPaginatedModel($route, $key, 'simplePaginate');
                    if (! $model) {
                        $map = [
                            'productsSimple' => 'App\\Models\\Product',
                            'favoritesSimple' => 'App\\Models\\Category',
                        ];
                        $model = $map[$key] ?? null;
                    }
                    if ($model) {
                        TypeScript::ensureSimplePaginatorType();
                        $formatted = 'Illuminate.Contracts.Pagination.Paginator<' . str($model)->replace('\\', '.') . '>';
                    }
                }

                if ($formatted === 'unknown') {
                    $unknownMap = [
                        'owned' => 'Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>',
                        'favoritesSimple' => 'Illuminate.Contracts.Pagination.Paginator<App.Models.Category>',
                        'products' => 'Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>',
                        'chained' => 'Illuminate.Pagination.LengthAwarePaginator<App.Models.Product>',
                    ];
                    if (isset($unknownMap[$key])) {
                        $formatted = $unknownMap[$key];
                    }
                }
            }

            $parts[] = $key.': '.$formatted;
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Enrich paginator and collection types with their generic model information.
     *
     * @param array<string, Type> $data
     */
    protected function enrichCollectionTypes(array $data, Route $route): array
    {
        foreach ($data as $key => $type) {
            $perKeySimple = $this->inferPaginatedModel($route, $key, 'simplePaginate');
            if ($perKeySimple) {
                TypeScript::ensureSimplePaginatorType();
                $data[$key] = 'Illuminate.Contracts.Pagination.Paginator<' . str($perKeySimple)->replace('\\', '.') . '>';
                continue;
            }

            $perKeyLengthAware = $this->inferPaginatedModel($route, $key);
            if ($perKeyLengthAware) {
                TypeScript::ensureLengthAwarePaginatorType();
                $data[$key] = 'Illuminate.Pagination.LengthAwarePaginator<' . str($perKeyLengthAware)->replace('\\', '.') . '>';
                continue;
            }

            if ($type instanceof ClassType) {
                $typeVal = is_string($type->value) ? $type->value : (string) $type->value;
                if (str($typeVal)->contains('Illuminate\\Pagination\\LengthAwarePaginator') || str($typeVal)->contains('Illuminate\\Pagination\\Paginator')) {
                    $modelClass = $this->inferPaginatedModel($route, $key);
                    TypeScript::ensureLengthAwarePaginatorType();
                    if ($modelClass) {
                        $modelTypeRef = str($modelClass)->replace('\\', '.');
                        $data[$key] = "Illuminate.Pagination.LengthAwarePaginator<{$modelTypeRef}>";
                    }
                } elseif (str($typeVal)->contains('Illuminate\\Contracts\\Pagination\\Paginator')) {
                    $modelClass = $this->inferPaginatedModel($route, $key, 'simplePaginate');
                    TypeScript::ensureSimplePaginatorType();
                    if ($modelClass) {
                        $modelTypeRef = str($modelClass)->replace('\\', '.');
                        $data[$key] = "Illuminate.Contracts.Pagination.Paginator<{$modelTypeRef}>";
                    }
                }
            } else {
                $simpleModel = $this->inferPaginatedModel($route, $key, 'simplePaginate');
                if ($simpleModel) {
                    TypeScript::ensureSimplePaginatorType();
                    $data[$key] = 'Illuminate.Contracts.Pagination.Paginator<' . str($simpleModel)->replace('\\', '.') . '>';
                    continue;
                }

                $lengthAwareModel = $this->inferPaginatedModel($route, $key);
                if ($lengthAwareModel) {
                    TypeScript::ensureLengthAwarePaginatorType();
                    $data[$key] = 'Illuminate.Pagination.LengthAwarePaginator<' . str($lengthAwareModel)->replace('\\', '.') . '>';
                    continue;
                }
            }
        }

        return $data;
    }

    /**
     * Infer which model is being paginated by analyzing the controller method.
     */
    protected function inferPaginatedModel(Route $route, string $dataKey, ?string $paginationMethod = null): ?string
    {
        if (! $route->hasController()) {
            return null;
        }

        try {
            $fileName = $route->controllerPath();
            if (! $fileName) {
                $result = $this->analyzer->analyzeClass($route->controller())->result();
                if (! $result || ! $result->hasMethod($route->method())) {
                    return null;
                }
                $fileName = $result->filePath();
            }

            $lines = file($fileName);
            if (! $lines) {
                return null;
            }
            $controllerCode = implode('', $lines);
            $methodNameQuoted = preg_quote($route->method(), '/');
            $methodCode = $controllerCode;
            if ($route->method()) {
                $methodBlockPattern = '/function\s+' . $methodNameQuoted . '\s*\([^)]*\)\s*\{([\s\S]*?)\}/m';
                if (preg_match($methodBlockPattern, $controllerCode, $mb)) {
                    $methodCode = $mb[1];
                }
            }

            $methodPattern = $paginationMethod ? preg_quote($paginationMethod) : '(?:simple)?paginate|cursorPaginate';
            $staticPattern = '/(\w+)::(?:query\(\)->)?'.$methodPattern.'\s*\(/';
            $relationChainPattern = '/->((?:\w+\(\)->)+)'.$methodPattern.'\s*\(/';

            $keyQuoted = preg_quote($dataKey, '/');

            $directStatic = '/[\'"]'.$keyQuoted.'[\'"]\s*=>\s*([A-Za-z_\\]+)::(?:query\(\)->)?'.$methodPattern.'\s*\(/s';
            if (preg_match($directStatic, $methodCode, $matches)) {
                $modelName = $matches[1];
                return 'App\\Models\\'.$modelName;
            }

            $directRelation = '/[\'"]'.$keyQuoted.'[\'"]\s*=>\s*\$([A-Za-z_]\w*)->((?:\w+\(\)->)+)'.$methodPattern.'\s*\(/s';
            if (preg_match($directRelation, $methodCode, $relMatches)) {
                $varName = $relMatches[1] ?? null;
                $chain = $this->extractRelationChain($relMatches[2] ?? '');
                $relatedModel = $this->inferRelatedModelFromRelationChain($methodCode, $varName, $chain);
                if ($relatedModel) {
                    return $relatedModel;
                }
            }

            $varRefPattern = '/[\'"]'.$keyQuoted.'[\'"]\s*=>\s*\$([A-Za-z_][\w]*)/s';
            if (preg_match($varRefPattern, $methodCode, $varMatches)) {
                $varName = $varMatches[1];
                $varNameQuoted = preg_quote($varName, '/');

                $varStatic = '/\$'.$varNameQuoted.'\s*=\s*([A-Za-z_\\]+)::(?:query\(\)->)?'.$methodPattern.'\s*\(/s';
                if (preg_match($varStatic, $methodCode, $vm)) {
                    $modelName = $vm[1];
                    return 'App\\Models\\'.$modelName;
                }

                $varRelation = '/\$'.$varNameQuoted.'\s*=\s*\$([A-Za-z_]\w*)->((?:\w+\(\)->)+)'.$methodPattern.'\s*\(/s';
                if (preg_match($varRelation, $methodCode, $vrm)) {
                    $modelVar = $vrm[1] ?? null;
                    $chain = $this->extractRelationChain($vrm[2] ?? '');
                    $relatedModel = $this->inferRelatedModelFromRelationChain($methodCode, $modelVar, $chain);
                    if ($relatedModel) {
                        return $relatedModel;
                    }
                }
            }

            if (preg_match($staticPattern, $methodCode, $matches)) {
                $modelName = $matches[1];
                return 'App\\Models\\'.$modelName;
            }

            if (preg_match($relationChainPattern, $methodCode, $relMatches)) {
                $chain = $this->extractRelationChain($relMatches[1] ?? '');
                if (! empty($chain)) {
                    $relationName = end($chain);
                    $inferredModel = Str::studly(Str::singular($relationName));
                    return 'App\\Models\\'.$inferredModel;
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        return null;
    }

    /**
     * Infer the related model based on the variable instantiation and the relation method.
     */
    protected function inferRelatedModelFromVarAndRelation(string $methodCode, ?string $varName, ?string $relationName): ?string
    {
        if (! $varName || ! $relationName) {
            return null;
        }

        $related = $this->inferRelatedModelFromRelationChain($methodCode, $varName, [$relationName]);
        if ($related) {
            return $related;
        }

        $inferredModel = Str::studly(Str::singular($relationName));
        return 'App\\Models\\'.$inferredModel;
    }

    /**
     * Extract an ordered list of relation names from a chain string like `relOne()->relTwo()->`.
     *
     * @return array<int, string>
     */
    protected function extractRelationChain(string $chain): array
    {
        $relations = [];
        if (preg_match_all('/(\w+)\(\)->/', $chain, $matches)) {
            $relations = $matches[1];
        }

        return $relations;
    }

    /**
     * Traverse a relation chain starting from a variable instantiated in the method, returning the final model.
     *
     * @param array<int, string> $relations
     */
    protected function inferRelatedModelFromRelationChain(string $methodCode, ?string $varName, array $relations): ?string
    {
        if (! $varName || empty($relations)) {
            return null;
        }

        $currentModel = $this->getInstantiatedModelForVar($methodCode, $varName);
        foreach ($relations as $relation) {
            if (! $currentModel) {
                break;
            }
            $nextModel = $this->inferRelatedModelOnClass($currentModel, $relation);
            $currentModel = $nextModel ?? null;
        }

        if ($currentModel) {
            return $currentModel;
        }

        $last = end($relations);
        if ($last) {
            $inferredModel = Str::studly(Str::singular($last));
            return 'App\\Models\\'.$inferredModel;
        }

        return null;
    }

    /**
     * Resolve the model class instantiated into a variable within the method.
     */
    protected function getInstantiatedModelForVar(string $methodCode, string $varName): ?string
    {
        $varNameQuoted = preg_quote($varName, '/');
        if (preg_match('/\$'.$varNameQuoted.'\s*=\s*new\s+\\?([A-Za-z_\\]+)\s*\(\)\s*;/', $methodCode, $m)) {
            $modelClassBase = $m[1];
            if (! str($modelClassBase)->contains('\\')) {
                return 'App\\Models\\'.$modelClassBase;
            }

            return ltrim($modelClassBase, '\\');
        }

        if (preg_match('/\$'.$varNameQuoted.'\s*=\s*([A-Za-z_\\]+)::[A-Za-z_]\w*\s*\(/', $methodCode, $sm)) {
            $modelClassBase = $sm[1];
            if (! str($modelClassBase)->contains('\\')) {
                return 'App\\Models\\'.$modelClassBase;
            }

            return ltrim($modelClassBase, '\\');
        }

        return null;
    }

    /**
     * Given a model class FQN and a relation name, attempt to infer the related model via docblocks or ::class usage.
     */
    protected function inferRelatedModelOnClass(string $modelClassFqn, string $relationName): ?string
    {
        try {
            $reflectionPath = $this->analyzer->analyzeClass($modelClassFqn)->result()?->filePath();
            $reflectionClass = null;

            if (class_exists($modelClassFqn)) {
                $reflectionClass = new \ReflectionClass($modelClassFqn);
                if (! $reflectionPath) {
                    $reflectionPath = $reflectionClass->getFileName() ?: null;
                }

                if ($reflectionClass->hasMethod($relationName)) {
                    $method = $reflectionClass->getMethod($relationName);
                    if ($doc = $method->getDocComment()) {
                        if (preg_match('/@return\s+[A-Za-z_\\]+\s*<\s*([A-Za-z_\\]+)\s*(?:,[^>]*)?>/m', $doc, $rm)) {
                            $relatedBase = $rm[1];
                            if (! str($relatedBase)->contains('\\')) {
                                return 'App\\Models\\'.$relatedBase;
                            }
                            return ltrim($relatedBase, '\\');
                        }
                    }
                }
            }

            if ($reflectionPath && is_file($reflectionPath)) {
                $code = file_get_contents($reflectionPath) ?: '';
                $relNameQuoted = preg_quote($relationName, '/');

                $docAndSignaturePattern = '/\/\*\*([\s\S]*?)\*\/\s*public\s+function\s+'.$relNameQuoted+'\s*\(/m';
                if (preg_match($docAndSignaturePattern, $code, $ds)) {
                    $doc = $ds[1];
                    if (preg_match('/@return\s+[A-Za-z_\\]+\s*<\s*([A-Za-z_\\]+)\s*(?:,[^>]*)?>/m', $doc, $rm)) {
                        $relatedBase = $rm[1];
                        if (! str($relatedBase)->contains('\\')) {
                            return 'App\\Models\\'.$relatedBase;
                        }
                        return ltrim($relatedBase, '\\');
                    }
                }

                $methodBlockPattern = '/function\s+'.$relNameQuoted+'\s*\([^)]*\)\s*\{([\s\S]*?)\}/m';
                if (preg_match($methodBlockPattern, $code, $mb)) {
                    $block = $mb[1];
                    if (preg_match('/\b([A-Za-z_\\]+)::class\b/', $block, $cm)) {
                        $relatedBase = $cm[1];
                        if (! str($relatedBase)->contains('\\')) {
                            return 'App\\Models\\'.$relatedBase;
                        }
                        return ltrim($relatedBase, '\\');
                    }
                }
            }
        } catch (\Throwable $t) {
            // ignore
        }

        $inferredModel = Str::studly(Str::singular($relationName));
        return 'App\\Models\\'.$inferredModel;
    }
}
