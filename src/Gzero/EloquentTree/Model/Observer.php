<?php namespace Gzero\EloquentTree\Model;

use DB;

class Observer {

    /**
     * When saving node we must set path and level
     *
     * @param Tree $model
     */
    public function saving(Tree $model)
    {
        if (!$model->exists and !in_array('path', $model->attributesToArray())) {
            $model->{$model->getTreeColumn('path')}   = '';
            $model->{$model->getTreeColumn('parent')} = NULL;
            $model->{$model->getTreeColumn('level')}  = 0;
        }
    }

    /**
     * After mode was saved we're building node path
     *
     * @param Tree $model
     */
    public function saved(Tree $model)
    {
        if ($model->{$model->getTreeColumn('path')} === '') { // If we just save() new node
            $model->{$model->getTreeColumn('path')} = $model->getKey() . '/';
        }
        DB::table($model->getTable())
            ->where($model->getKeyName(), '=', $model->getKey())
            ->update(
                array(
                    $model->getTreeColumn('path') => $model->{$model->getTreeColumn('path')}
                )
            );
    }

} 
