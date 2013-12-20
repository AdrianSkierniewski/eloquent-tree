eloquent-tree
=============

Eloquent Tree is a tree model for Laravel Eloquent ORM.

##Features

* Creating root, children and sybling nodes
* Getting children
* Getting descendants
* Getting ancestor
* Moving sub-tree
* Building tree on PHP side


## Simply migration

        Schema::create(
            'trees',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('path', 255)->nullable();
                $table->integer('parent_id')->unsigned()->nullable();
                $table->integer('level')->default(0);
                $table->timestamps();
                $table->index(array('path', 'parent_id'));
                $table->foreign('parent_id')->references('id')->on('trees')->on_delete('CASCADE');
            }
        );
