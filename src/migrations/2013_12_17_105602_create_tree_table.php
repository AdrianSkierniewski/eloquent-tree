<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;


class CreateTreeTable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'trees',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('path', 255)->nullable();
                $table->integer('parent_id')->unsigned()->nullable();
                $table->integer('level')->default(0);
                $table->timestamps();
                $table->index('path');
                $table->foreign('parent_id')->references('id')->on('trees')->on_delete('CASCADE');
            }
        );
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('trees');
    }

}
