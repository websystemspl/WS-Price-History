<?php

namespace WsPriceHistory\App;

use WsPriceHistory\App\Assets;
use WsPriceHistory\App\Actions;
use WsPriceHistory\App\AdminPage;
use WsPriceHistory\App\Database\DatabaseOperation;
use WsPriceHistory\App\Database\DatabaseTable;


class PluginManager
{
  public function run()
  {
    $databaseTable = new DatabaseTable;
    $databaseTable->createTable();
    new AdminPage;
    new Actions;
    new Assets;
  }
}
