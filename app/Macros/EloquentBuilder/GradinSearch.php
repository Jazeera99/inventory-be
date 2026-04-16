<?php

namespace App\Macros\EloquentBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Usage:
 *
 * Project belongsToMany User
 * Relevance calculation:
 *  - matches whole word, then by partial word
 *  - matches column priorities: projects.name, users.name, projects.description
 *  - not typo tolerant yet
 *
 * Project::query()
 *   ->search([
 *       'projects.name',
 *       'users.name' => fn ($query) => $query
 *          ->leftJoin('project_user', 'projects.id', '=', 'project_user.project_id')
 *          ->leftJoin('users', 'users.id', '=', 'project_user.user_id'),
 *       'projects.description',
 *   ])
 *   ->get();
 */
Builder::macro('gradinSearch', function (array $columns, ?string $keyword = null) {
    // get search text from request
    $searchText = $keyword ?: request()->query('search') ?: '';

    /** @var Builder<Model> $this */

    // early return when search text is empty
    if (empty($searchText)) {
        return $this;
    }

    // call closure, e.g for join or some other query modification
    foreach ($columns as $column => $closure) {
        if (is_callable($closure)) {
            $closure($this);
        }
    }

    // filter result using LIKE
    $this->where(function ($query) use ($columns, $searchText): void {
        foreach (explode(' ', $searchText) as $word) {
            $query->where(function ($query) use ($word, $columns): void {
                foreach ($columns as $key => $relation) {
                    /** @var string */
                    $column = is_callable($relation) ? $key : $relation;
                    $query->orWhere($column, 'like', '%'.$word.'%');
                }
            });
        }
    });

    // sort by relevance
    // $transliteratedSearch = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', urldecode($searchText));
    // $regexSafeSearch = preg_quote($transliteratedSearch);
    // $regexSafeSearch = urldecode($searchText); // error when the search keyword contains `[`
    $regexSafeSearch = preg_quote($searchText, '&'); // https://stackoverflow.com/a/53986553/3671954
    $relevance = pow(2, count($columns) * 2 - 1);

    $selectRaw = [];
    $arguments = [];
    // matches whole word
    foreach ($columns as $key => $relation) {
        $column = is_callable($relation) ? $key : $relation;
        $selectRaw[] = 'IF ('.$column.' REGEXP ?, '.$relevance.', 0)';
        // $arguments[] = '[[:<:]]'.$regexSafeSearch.'[[:>:]]';
        $arguments[] = '\\b'.$regexSafeSearch.'\\b';
        $relevance /= 2;
    }
    // matches partial word
    foreach ($columns as $key => $relation) {
        $column = is_callable($relation) ? $key : $relation;
        $selectRaw[] = 'IF ('.$column.' LIKE ?, '.$relevance.', 0)';
        $arguments[] = '%'.$searchText.'%';
        $relevance /= 2;
    }
    $selectRaw = implode(' + ', $selectRaw);

    return $this->orderByRaw($selectRaw.' DESC', $arguments);
});
