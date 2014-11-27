<?php

class Tree extends \Gzero\EloquentTree\Model\Tree {

    protected $fillable = ['title'];

    /**
     * ONLY FOR TESTS!
     * Metod resets static::$booted
     */
    public static function __resetBootedStaticProperty()
    {
        static::$booted = [];
    }
} 
