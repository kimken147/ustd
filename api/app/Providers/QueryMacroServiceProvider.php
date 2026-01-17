<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryMacroServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Builder::macro('explainWithSql', function () {
            $getEloquentSqlWithBindings = function ($query) {
                return vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
                    $binding = addslashes($binding);
                    return is_numeric($binding) ? $binding : "'{$binding}'";
                })->toArray());
            };

            $sql = $getEloquentSqlWithBindings($this);
            $explanation = DB::select('EXPLAIN ' . $sql);

            Log::info("SQL Query: " . $sql);
            Log::info("EXPLAIN: " . json_encode($explanation));

            return [
                'sql' => $sql,
                'explanation' => $explanation
            ];
        });
    }

    public function register()
    {
        //
    }
}
