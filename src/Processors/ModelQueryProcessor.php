<?php

namespace Superlog\Processors;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Superlog\Facades\Superlog;

class ModelQueryProcessor
{
    /**
     * Register the model query processor.
     *
     * @return void
     */
    public static function register(): void
    {
        // Listen for query events
        DB::listen(function (QueryExecuted $query) {
            self::processQuery($query);
        });

        // Listen for model events
        Event::listen('eloquent.*', function ($event, $models) {
            if (empty($models) || !isset($models[0]) || !($models[0] instanceof Model)) {
                return;
            }

            $eventName = $event;
            if (strpos($eventName, ':') !== false) {
                $eventName = substr($eventName, strpos($eventName, ':') + 1);
            }
            
            $model = $models[0];
            $modelName = class_basename($model);
            
            // Map event names to verbs
            $verbMap = [
                'created' => 'insert',
                'updated' => 'update',
                'deleted' => 'delete',
                'retrieved' => 'select',
                'saved' => 'save',
                'restored' => 'restore',
                'forceDeleted' => 'forceDelete',
            ];
            
            $verb = $verbMap[$eventName] ?? $eventName;
            
            // We don't log the actual query here as it will be captured by the DB::listen handler
            // This just ensures we have the model context for the upcoming query
            self::setCurrentModelContext($modelName, $verb);
        });
    }
    
    /**
     * Store the current model context for the next query.
     *
     * @param string $modelName
     * @param string $verb
     * @return void
     */
    protected static function setCurrentModelContext(string $modelName, string $verb): void
    {
        app()->instance('superlog.current_model_context', [
            'model' => $modelName,
            'verb' => $verb,
            'timestamp' => microtime(true)
        ]);
    }
    
    /**
     * Process a database query and log it with model context if available.
     *
     * @param QueryExecuted $query
     * @return void
     */
    protected static function processQuery(QueryExecuted $query): void
    {
        $sql = $query->sql;
        $bindings = $query->bindings;
        $time = $query->time;
        $isError = false;
        
        // Get the current model context if available
        $modelContext = app()->bound('superlog.current_model_context') 
            ? app('superlog.current_model_context') 
            : null;
            
        // If this query happened more than 100ms after the model event, it's probably not related
        if ($modelContext && (microtime(true) - $modelContext['timestamp']) > 0.1) {
            $modelContext = null;
        }
        
        if ($modelContext) {
            $modelName = $modelContext['model'];
            $verb = $modelContext['verb'];
            
            // Determine number of affected records
            $recordCount = 0;
            
            // For SELECT queries, we can estimate the number of records from the result
            if (stripos($sql, 'select') === 0) {
                // We can't get the exact count without re-running the query
                // This is just an estimation based on the query structure
                $recordCount = stripos($sql, 'limit 1') !== false ? 1 : '(multiple)';
            } 
            // For other queries, we can often get the count from the query itself
            else if (stripos($sql, 'insert') === 0) {
                $recordCount = 1; // Single insert
                if (stripos($sql, 'insert into') !== false && substr_count($sql, '(') > 1) {
                    // Bulk insert - count the number of value groups
                    $recordCount = substr_count($sql, '),(') + 1;
                }
            }
            else if (stripos($sql, 'update') === 0 || stripos($sql, 'delete') === 0) {
                $recordCount = '(affected)'; // We can't know without the result
            }
            
            // Format the SQL with bindings for better readability
            $formattedSql = self::formatSqlWithBindings($sql, $bindings);
            
            // Log the model action with the SQL query
            Superlog::log(
                $isError ? 'error' : 'debug',
                "MODEL/{$modelName}/{$verb}",
                "Model {$modelName} {$verb} operation",
                [
                    'sql' => $formattedSql,
                    'bindings' => $bindings,
                ],
                [
                    'duration_ms' => $time,
                    'record_count' => $recordCount,
                    'connection' => $query->connection->getName(),
                ]
            );
            
            // Clear the model context after using it
            app()->forgetInstance('superlog.current_model_context');
        }
    }
    
    /**
     * Format SQL query with bindings for better readability.
     *
     * @param string $sql
     * @param array $bindings
     * @return string
     */
    protected static function formatSqlWithBindings(string $sql, array $bindings): string
    {
        $sql = str_replace(['%', '?'], ['%%', '%s'], $sql);
        
        foreach ($bindings as $key => $binding) {
            // Convert binding to string representation
            if (is_null($binding)) {
                $bindings[$key] = 'NULL';
            } else if (is_bool($binding)) {
                $bindings[$key] = $binding ? 'TRUE' : 'FALSE';
            } else if (is_string($binding)) {
                $bindings[$key] = "'" . addslashes($binding) . "'";
            }
        }
        
        return vsprintf($sql, $bindings);
    }
}