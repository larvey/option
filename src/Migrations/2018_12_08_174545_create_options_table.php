<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOptionsTable extends Migration
{
	public $table = 'options';

	public function up()
	{
		if (Schema::hasTable($this->table)) {
			return;
		}

		Schema::create($this->table, function (Blueprint $table) {
			$table->increments('id');

			// Seçenek anahtarı
			$table->string('key', 200)->unique()->index();

			// Seçenek değeri
			$table->longText('value')->nullable();

			$table->unsignedTinyInteger('autoload')->default(1);

            $table->engine = 'InnoDB';
		});
	}

	public function down()
	{
		Schema::dropIfExists($this->table);
	}
}
