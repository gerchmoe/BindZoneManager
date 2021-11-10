<?php

  // Initial setup
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  $start_time = microtime(true);

  // Initializing the class
  require "../BindZoneManager.php";
  $zone = new BindZoneManager('aurora-pay.space.db');

  // test zone_reload function
  function zone_reload(){
    $a = mt_rand(0,1);
    echo "returning $a!";
    return $a;
  }

  if($zone->e){
    echo "Zone initialization failed.".PHP_EOL;
    echo $zone->error;
    die();
  }else{

    // Freeroam
    // if($zone->UpdateSOA([
    //   'refresh' => '100000'
    // ])){
    //
    //   echo $zone->Render();
    // }


    // echo $zone->warning;

    // $zone->AddRecord([
    //   'enabled' => true,
    //   'domain' => 'testdomain.io.'.time(),
    //   'ttl' => 60,
    //   'type' => 'A',
    //   'priority' => '123',
    //   'address' => 'mail.aurora-pay.space',
    //   'id' => 'RootMXRecord3'.time()
    // ]) or die($zone->error);

    // $zone->UpdateRecordById('Node', [
    //   'enabled' => false,
    //   'ttl' => 123,
    //   'domain' => 'something.test.io',
    //   'address' => 'mail.aurora-pay.space',
    //   // 'id' => 'RootMXRecord3'
    // ]) or die($zone->error);

    // $zone->UpdateRecord([
    //   'domain' => 'aurora.',
    //   'type' => 'A',
    //   'address' => '111.222.333.444'
    // ], [
    //   // 'enabled' => false,
    //   'ttl' => 300,
    //   'domain' => 'something.test.io',
    //   'address' => 'mail.aurora-pay.space',
    //   // 'id' => 'RootMXRecord3'
    // ]) or die($zone->error);

    // $zone->UpdateNameserver(1, [
    //   // 'enabled' => false,
    //   'ttl' => 6000,
    //   'domain' => 'something.test.io',
    //   'address' => 'mail.aurora-pay.space',
    //   // 'id' => 'RootMXRecord3'
    // ]) or die($zone->error);

    // $zone->UpdateNameservers([
    //   0 => [
    //     // 'enabled' => false,
    //     'ttl' => 600,
    //     'domain' => 'something.test.io2',
    //     'address' => 'mail.aurora-pay.space',
    //     // 'id' => 'RootMXRecord3'
    //   ]
    // ]) or die($zone->error);

    // echo $zone->warning;

    // $zone->AddRecord([
    //   'enabled' => true,
    //   'domain' => 'testdomain.io.',
    //   'ttl' => 60,
    //   'type' => 'A',
    //   // 'priority' => '0',
    //   'address' => 'mail.aurora-pay.space',
    //   'id' => 'RootMXRecord4'
    // ], true) or die($zone->error);
    //
    // $zone->AddRecord([
    //   'enabled' => true,
    //   'domain' => 'testdomain.io.',
    //   'ttl' => 60,
    //   'type' => 'A',
    //   // 'priority' => '0',
    //   'address' => 'home.test.com',
    //   'id' => 'RootMXRecord5'
    // ], true) or die($zone->error);

    // echo $zone->Render();
    // $zone->Save() or die($zone->error);

    $zone->Reload() or die($zone->error);



    // $zone->RemoveRecordById('Node123');
    // echo $zone->RemoveRecordById('RootMXRecord2');
    // unset($zone->records[0]);

    // $zone->RemoveRecord([
    //   'domain' => 'testdomain.io.',
    //   'type' => 'A',
    //   'address' => '1.2.3.4'
    // ]);
    // $zone->RemoveRecord([
    //   'domain' => 'testdomain.io.',
    //   'type' => 'A',
    //   'address' => '1.2.3.4'
    // ]);
    // print_r($zone->records);
    // $zone->RemoveRecord([
    //   'domain' => 'aurora-pay.space.',
    //   'type' => 'MX',
    //   'address' => 'mail.aurora-pay.space.'
    // ]);
    // $zone->RemoveRecord([
    //   'domain' => 'testdomain.io.',
    //   'type' => 'MX',
    //   'address' => 'mail.aurora-pay.space'
    // ]);

    // echo $zone->Render();


    // $zone->Save();

    // print_r($zone->records);

  }






  // Output execution time
  $end_time = microtime(true);
  $execution_time = ($end_time - $start_time);
  echo "\n-------\nExecution time = ".$execution_time."s\n";

?>
