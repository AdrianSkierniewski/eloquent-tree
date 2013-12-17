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
        if (!$model->exists) {
            $model->{$model->getTreeColumn('path')}  = '';
            $model->{$model->getTreeColumn('level')} = 0;
        }
    }

    /**
     * After mode was saved we're building node path
     *
     * @param Tree $model
     */
    public function saved(Tree $model)
    {
        $model->{$model->getTreeColumn('path')} = $model->{$model->getTreeColumn('path')} . $model->getKey() . '/';
        DB::table($model->getTable())
            ->where($model->getKeyName(), '=', $model->getKey())
            ->update(
                array(
                    $model->getTreeColumn('path') => $model->{$model->getTreeColumn('path')}
                )
            );
    }

} 
