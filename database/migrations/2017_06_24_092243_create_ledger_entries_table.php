<?php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
class CreateLedgerEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->morphs('ledgerable');
            $table->string('money_to')->nullable();
            $table->string('money_from')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('credit')->default(0);
            $table->boolean('debit')->default(0);
            $table->integer('amount');
            $table->integer('current_balance');
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
        Schema::dropIfExists('ledger_entries');
    }
}