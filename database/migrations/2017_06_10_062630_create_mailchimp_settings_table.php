<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateMailchimpSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mailchimp_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('api_key');
            $table->string('list_id');
            $table->string('subscribe_status')->default('subscribed');
            $table->string('group_id_products', 255)->nullable();
            $table->string('group_id_is_paid', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('mailchimp_settings');
    }
}
