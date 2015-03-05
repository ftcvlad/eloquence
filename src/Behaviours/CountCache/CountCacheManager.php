<?php
namespace Eloquence\Behaviours\CountCache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CountCacheManager
{
    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    private $model;

    /**
     * Stores the original data that was set when the model was loaded.
     *
     * @var array
     */
    private $original = [];

    /**
     * @param array $original
     */
    public function setOriginal(array $original)
    {
        $this->original = $original;
    }

    /**
     * Increase a model's related count cache by 1.
     *
     * @param Model $model
     */
    public function increment(Model $model)
    {
        $this->model = $model;

        $this->applyToCountCache(function($config) {
            $this->update($config, '+', $this->model->{$config['foreignKey']});
        });
    }

    /**
     * Decrease a model's related count cache by 1.
     *
     * @param Model $model
     */
    public function decrement(Model $model)
    {
        $this->model = $model;

        $this->applyToCountCache(function($config) {
            $this->update($config, '-', $this->model->{$config['foreignKey']});
        });
    }
    
    /**
     * Applies the provided function to the count cache setup/configuration.
     *\
     * @param callable $function
     */
    protected function applyToCountCache(\Closure $function)
    {
        foreach ($this->model->countCaches() as $key => $cache) {
            $function($this->countCacheConfig($key, $cache));
        }
    }

    /**
     * Update the cache for all operations.
     *
     * @param Model $model
     */
    public function updateCache(Model $model)
     {
         $this->model = $model;

         $this->applyToCountCache(function($config) {
             if (isset($this->original[$config['foreignKey']]) && $this->model->{$config['foreignKey']} != $this->original[$config['foreignKey']]) {
                 $this->update($config, '-', $this->original[$config['foreignKey']]);
                 $this->update($config, '+', $this->model->{$config['foreignKey']});
             }
         });
     }

    /**
     * Updates a table's record based on the query information provided in the $config variable.
     *
     * @param array $config
     * @param string $operation Whether to increment or decrement a value. Valid values: +/-
     * @param $value
     * @return
     */
    protected function update(array $config, $operation, $value)
    {
        $params = [
            $this->getTable($config['model']),
            $config['countField'],
            $config['countField'],
            $operation,
            $config['key'],
            $value
        ];

        return DB::statement('UPDATE ? SET ? = ? ? 1 WHERE ? = ?', $params);
    }

    /**
     * Takes a registered counter cache, and setups up defaults.
     *
     * @param string $cacheKey
     * @param array $cacheOptions
     * @return array
     */
    protected function countCacheConfig($cacheKey, $cacheOptions)
    {
        $opts = [];

        // Smallest number of options provided, figure out the rest
        if (is_numeric($cacheKey)) {
            $relatedModel = $cacheOptions;
        }
        else {
            $relatedModel = $cacheOptions;
            $opts['countField'] = $cacheKey;

            if (is_array($cacheOptions)) {
                if (isset($cacheOptions[2])) {
                    $opts['key'] = $cacheOptions[2];
                }
                if (isset($cacheOptions[1])) {
                    $opts['foreignKey'] = $cacheOptions[1];
                }
                if (isset($cacheOptions[0])) {
                    $relatedModel = $cacheOptions[0];
                }
            }
        }

        return $this->defaults($opts, $relatedModel);
    }

    /**
     * Returns the table for a given model. Model can be an Eloquent model object, or a full namespaced
     * class string.
     *
     * @param string|object $model
     * @return mixed
     */
    public function getTable($model)
    {
        if (! is_object($model)) {
            $model = new $model;
        }

        return $model->getTable();
    }

    /**
     * Returns necessary defaults, overwritten by provided options.
     *
     * @param array $options
     * @param string $relatedModel
     * @return array
     */
    protected function defaults($options, $relatedModel)
    {
        $class = strtolower(class_basename($this->model));
        $relatedClass = strtolower(class_basename($relatedModel));

        $defaults = [
            'model' => $relatedModel,
            'countField' => $class.'_count',
            'foreignKey' => $relatedClass.'_id',
            'key' => 'id'
        ];

        return array_merge($defaults, $options);
    }
}