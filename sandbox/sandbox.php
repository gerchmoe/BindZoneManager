<?php

  // Initial setup
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  $start_time = microtime(true);

  // Initializing the class
  require "BindZoneManager.php";
  $zone = new BindZoneManager;

  





  // Output execution time
  $end_time = microtime(true);
  $execution_time = ($end_time - $start_time);
  echo "\n-------\nExecution time = ".$execution_time."s\n";

?>
