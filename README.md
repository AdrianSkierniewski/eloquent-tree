eloquent-tree [![Latest Stable Version](https://poser.pugx.org/gzero/eloquent-tree/v/stable.png)](https://packagist.org/packages/gzero/eloquent-tree) [![Total Downloads](https://poser.pugx.org/gzero/eloquent-tree/downloads.png)](https://packagist.org/packages/gzero/eloquent-tree) [![Build Status](https://travis-ci.org/AdrianSkierniewski/eloquent-tree.png)](https://travis-ci.org/AdrianSkierniewski/eloquent-tree)
=============

Eloquent Tree is a tree model for Laravel Eloquent ORM.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Migration](#migration)
- [Example usage](#example-usage)
- [Events](#events)
- [Support](#support)

##Features

* Creating root, children and sibling nodes
* Getting children
* Getting descendants
* Getting ancestor
* Moving sub-tree
* Building tree on PHP side


## Installation

**Version 1.0 is not compatible with 0.***

**Version 2.0 - Laravel 5 support**

**Version 2.1 - Laravel 5.1 support**

**Version 3.0 - Laravel 5.3 support**

Begin by installing this package through Composer. Edit your project's composer.json file to require gzero/eloquent-tree.
```json
"require": {
    "laravel/framework": "5.3.*",
    "gzero/eloquent-tree": "v3.0.*"
},
"minimum-stability" : "stable"
```
Next, update Composer from the Terminal:
```
composer update
```
That's all now you can extend \Gzero\EloquentTree\Model\Tree in your project

## Migration
Simply migration with all required columns that you could extend by adding new fields
```php
Schema::create(
    'trees',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('path', 255)->nullable();
        $table->integer('parent_id')->unsigned()->nullable();
        $table->integer('level')->default(0);
        $table->timestamps();
        $table->index(array('path', 'parent_id', 'level'));
        $table->foreign('parent_id')->references('id')->on('contents')->onDelete('CASCADE');
    }
);
```

## Example usage

- [Inserting and Updating new nodes](#inserting-and-updating-new-nodes)
- [Getting tree nodes](#getting-tree-nodes)
- [Finding Leaf nodes](#getting-leaf-nodes)
- [Map from array](#map-from-array)
- [Rendering tree](#rendering-tree)

### Inserting and updating new nodes

```php
$root       = new Tree(); // New root
$root->setAsRoot();
$child      = with(new Tree())->setChildOf($root); // New child
$sibling    = new Tree();
$sibling->setSiblingOf($child); // New sibling
```

### Getting tree nodes

Leaf - returning root node
```php
$leaf->findRoot();
```

Children - returning flat collection of children. You can use Eloquent query builder.
```php
$collection = $root->children()->get();
$collection2 = $root->children()->where('url', '=', 'slug')->get();
```
Ancestors - returning flat collection of ancestors, first is root, last is current node. You can use Eloquent query builder.
            Of course there are no guarantees that the structure of the tree would be complete if you do the query with additional where
```php
$collection = $node->findAncestors()->get();
$collection2 = $node->findAncestors()->where('url', '=', 'slug')->get();
```

Descendants - returning flat collection of descendants, first is current node, last is leafs. You can use Eloquent query builder.
            Of course there are no guarantees that the structure of the tree would be complete if you do the query with additional where
```php
$collection = $node->findDescendants()->get();
$collection2 = $node->findDescendants()->where('url', '=', 'slug')->get();
```

Building tree structure on PHP side - if some nodes will be missing, these branches will not be built
```php
$treeRoot = $root->buildTree($root->findDescendants()->get())
```

### Getting leaf nodes
```php
Tree::getLeafs();
```

### Map from array

Three new roots, first with descendants
```php
 Tree::mapArray(
            array(
                array(
                    'children' => array(
                        array(
                            'children' => array(
                                array(
                                    'children' => array(
                                        array(
                                            'children' => array()
                                        ),
                                        array(
                                            'children' => array()
                                        )
                                    )
                                ),
                                array(
                                    'children' => array()
                                )
                            )
                        ),
                        array(
                            'children' => array()
                        )
                    )
                ),
                array(
                    'children' => array()
                ),
                array(
                    'children' => array()
                )
            )
 );
```

### Rendering tree

You can render tree built by the function buildTree
```php
 $html = $root->render(
        'ul',
        function ($node) {
            return '<li>' . $node->title . '{sub-tree}</li>';
        },
        TRUE
        );
 echo $html;
```

## Events

All tree models have additional events:
* updatingParent
* updatedParent
* updatedDescendants

You can use them for example to update additional tables

## Support

If you enjoy my work, please consider making a small donation, so I can continue to maintain and create new software to help
other users.

[![Build Status](https://www.paypalobjects.com/en_US/GB/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6YKG4RZRQF3GS)

