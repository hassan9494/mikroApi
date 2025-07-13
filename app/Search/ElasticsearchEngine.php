<?php

namespace App\Search;

use Elastic\Elasticsearch\ClientBuilder;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Illuminate\Database\Eloquent\Collection;

class ElasticsearchEngine extends Engine
{
    protected $client;

    public function __construct()
    {
        $this->client = app('elasticsearch'); // Use the bound instance
    }

    // app/Search/ElasticsearchEngine.php

    public function update($models)
    {

        try {
            // Process each model individually instead of bulk
            $models->each(function ($model) {
                $params = [
                    'index' => $model->searchableAs(),
                    'id' => $model->getScoutKey(),
                    'body' => $model->toSearchableArray()
                ];

                $this->client->index($params);
            });

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function delete($models)
    {
        $params = ['body' => []];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey(),
                ]
            ];
        });

        $this->client->bulk($params);
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage
        ]);
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $params = [
            'index' => $builder->model->searchableAs(),
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $builder->query,
                        'fields' => ['name^3', 'meta_title^2', 'meta_keywords', 'meta_description']
                    ]
                ]
            ]
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        return $this->client->search(array_merge($params, $options));
    }

    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return [];
        }

        return collect($builder->orders)->map(function($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }

    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total']['value'] === 0) {
            return $model->newCollection();
        }

        $ids = $this->mapIds($results)->all();
        $idPositions = array_flip($ids);

        return $model->getScoutModelsByIds(
            $builder, $ids
        )->filter(function ($model) use ($ids) {
            return in_array($model->getScoutKey(), $ids);
        })->sortBy(function ($model) use ($idPositions) {
            return $idPositions[$model->getScoutKey()];
        })->values();
    }

    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
    }

    public function flush($model)
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }

    public function createIndex($name, array $options = [])
    {
        // We already handle this separately
    }

    public function deleteIndex($name)
    {
        $this->client->indices()->delete(['index' => $name]);
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        // TODO: Implement lazyMap() method.
    }
}
