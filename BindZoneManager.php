<?php


  class BindZoneManager{

    var $e = false;
    var $error;
    var $warning;

    var $domainFile; // Path to the bind zone file
    var $saveOrig = true;

    var $soa = []; // Will contain all data from SOA record

    var $nameservers = []; // Will contain data about the nameservers
    var $markerNSStart; // Starting marker for the nameservers
    var $markerNSEnd; // End marker for the usual nameservers

    var $records = []; // Will contain all other records that won't be able to break the zone
    var $markerRecordsStart; // Starting marker for the usual records
    var $markerRecordsEnd; // End marker for the usual records

    var $fileContents; // Will contain raw file contents

    function __construct($file = ''){

      if(!empty($file)){

        $this->domainFile = $file;

        $this->markerRecordsStart = '; ----- BindPHP Records Start -----';
        $this->markerRecordsEnd = '; ----- BindPHP Records End -----';

        $this->markerNSStart = '; ----- BindPHP Nameservers Start -----';
        $this->markerNSEnd = '; ----- BindPHP Nameservers End -----';

        $this->Read();

      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Filename argument is required for class initialization. Use: new BindZoneManager('example.com.db')";
      }

    }

    function Read(){
      // NOTE: We can and should add lock, so file won't be changed from two editors at the same time
      if(file_exists($this->domainFile)){
        $file = @file_get_contents($this->domainFile);

        if(!$file){
          $this->e = true;
          $this->error = __FUNCTION__."(): Unable to open file '$this->domainFile'.\n".$this->error;
          error_log($this->error);
          return false;
        }

        $this->fileContents = $file;

        $success = true;
        $newSoa = $this->ReadSOA() or $success = false;
        $newNameservers = $this->ReadNS() or $success = false;
        $newRecords = $this->ReadRecords() or $success = false;

        if($success){
          $this->soa = $newSoa;
          $this->nameservers = $newNameservers;
          $this->records = $newRecords;
        }else{
          $this->e = true;
          $error_message = __FUNCTION__."(): Fatal error: Unable to parse some sections. More details:\n".$this->error;
          $this->error = $error_message;
          error_log($error_message);
          return false;
        }

      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Unable to read file $this->domainFile because it does not exist.".$this->error;
        return false;
      }
    }
    function ReadSOA(){
      $file = $this->fileContents;

      preg_match("/[\n](.*)( SOA )(.|\n)+[)]/", $file, $matches);

      if(!empty($matches) and !empty($matches[0])){

        $soaRaw = $matches[0];
        $soaRaw = preg_replace("/^[\n]+/", '', $soaRaw); // Remove phantom line at the beginning
        $soaRaw = preg_replace("/(;)(.*)[\n]/", '', $soaRaw); // Remove comments for the values in brackets
        $soaRaw = preg_replace("/( )+/", ' ', $soaRaw); // Strip multiple spaces into one
        $soaRaw = preg_replace("/[\n]+/", '', $soaRaw); // Remove all newlines for easier processing
        $soaRaw = preg_replace("/( )(\)|\()/", '', $soaRaw); // Remove brackets to make explode easier


        $values = explode(' ', $soaRaw);

        // variables to retrieve from file

        $soa = [];
        $soa['domain'] = $values[0];
        $soa['ttl'] = $values[1];
        $soa['primaryNS'] = $values[4];
        $soa['mail'] = $values[5];
        $soa['serial'] = $values[6];
        $soa['refresh'] = $values[7];
        $soa['retry'] = $values[8];
        $soa['expire'] = $values[9];
        $soa['minimum'] = $values[10];

        return $soa;

      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Unable to identify SOA record.".$this->error;
        return false;
      }


    }
    function ReadNS(){

      $file = $this->fileContents;

      // Reading NS records
      if(strpos($file, $this->markerNSStart) and strpos($file, $this->markerNSEnd)){

        $area = explode($this->markerNSStart, $file)[1];
        $area = explode($this->markerNSEnd, $area)[0];

        $area = preg_replace("/[\n|\r\n]+/", "\n", $area);
        $area = preg_replace("/^[\n]/", '', $area);
        $area = preg_replace("/[\n]$/", '', $area);
        $area = preg_replace("/[ ]+/", " ", $area);

        $nsLines = explode("\n", $area);

        $nameservers = [];
        foreach($nsLines as $line){

          if(preg_match("/^(.*)( )[0-9]+( IN NS )(.*)$/", $line)){

            $items = explode(' ', $line);

            $domain = $items[0];
            $ttl = $items[1];
            $address = $items[4];

            array_push($nameservers, [
              'domain' => $domain,
              'ttl' => $ttl,
              'address' => $address
            ]);

          }else{

            $error_message = __FUNCTION__."(): Warning: Unable to parse line '$line', therefore it is skipped.";
            $this->warning = $error_message;
            error_log($error_message);

          }
        }

        if(count($nsLines) > 0 and count($nameservers) == 0){
          $this->e = true;
          $error_message = __FUNCTION__."(): Fatal error: Unable to parse a single line from NS section.";
          $this->error = $error_message;
          error_log($error_message);
          return false;
        }else{
          return $nameservers;
        }


      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Unable to read nameservers due to lack of NS zone markers.".$this->error;
        return false;
      }
    }
    function ReadRecords(){

      $file = $this->fileContents;

      // Reading usual records
      if(strpos($file, $this->markerRecordsStart) and strpos($file, $this->markerRecordsEnd)){

        $area = explode($this->markerRecordsStart, $file)[1];
        $area = explode($this->markerRecordsEnd, $area)[0];

        if(!empty(preg_replace("/[\n]/", '', $area))){

          $area = preg_replace("/[\n|\r\n]+/", "\n", $area);
          $area = preg_replace("/[ ]+/", " ", $area);
          $area = preg_replace("/^[\n]/", '', $area);
          $area = preg_replace("/[\n]$/", '', $area);

          $lines = explode("\n", $area);

          // print_r($lines);

          foreach($lines as $i => $line){

            if(preg_match("/^[; ]/", $line)){
              $records[$i]['enabled'] = false;
              $line = preg_replace("/^[;][ ]/", '', $line);
            }else{
              $records[$i]['enabled'] = true;
            }

            $lineArray = explode(' ', $line);

            $type = $lineArray[3];

            switch($type){
              case 'A':
              case 'NS':
              case 'CNAME':
              case 'TXT':
                $records[$i]['domain'] = $lineArray[0];
                $records[$i]['ttl'] = $lineArray[1];
                $records[$i]['type'] = $lineArray[3];
                $records[$i]['address'] = $lineArray[4];

                if(preg_match("/(;RID_)[0-9a-zA-Z-_.]+$/", $line)){
                  $records[$i]['id'] = explode(';RID_', $line)[1];
                }else{
                  $records[$i]['id'] = null;
                }
              break;

              case 'MX':
                $records[$i]['domain'] = $lineArray[0];
                $records[$i]['ttl'] = $lineArray[1];
                $records[$i]['type'] = $lineArray[3];
                $records[$i]['priority'] = $lineArray[4];
                $records[$i]['address'] = $lineArray[5];

                if(preg_match("/(;RID_)[0-9a-zA-Z-_.]+$/", $line)){
                  $records[$i]['id'] = explode(';RID_', $line)[1];
                }else{
                  $records[$i]['id'] = null;
                }
              break;
            }

          }

        }else{
          $records = [];
        }

        return $records;

      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Unable to read regular records due to lack of zone markers.".$this->error;
        return false;
      }
    }

    function Render(){

      $file = $this->fileContents;

      $success = true;

      $updated = $this->RenderSOA($file) or $success = false;
      $updated = $this->RenderNS($updated) or $success = false;
      $updated = $this->RenderRecords($updated) or $success = false;

      return $success ? $updated : false;

    }
    function RenderSOA($file){

      preg_match("/[\n](.*)( SOA )(.|\n)+[)]/", $file, $matches);

      $oldRawSOA = $matches[0];
      $oldRawSOA = preg_replace("/^[\n]+/", '', $oldRawSOA); // Remove phantom line at the beginning

      $domain = $this->soa['domain'];
      $ttl = $this->soa['ttl'];
      $primaryNS = $this->soa['primaryNS'];
      $mail = $this->soa['mail'];
      $serial = $this->soa['serial'];
      $refresh = $this->soa['refresh'];
      $retry = $this->soa['retry'];
      $expire = $this->soa['expire'];
      $minimum = $this->soa['minimum'];

      $newRawSOA = "$domain $ttl IN SOA $primaryNS $mail (\n $serial ;Serial\n $refresh ;Refresh\n $retry ;Retry\n $expire ;Expire\n $minimum ;Minimum\n)";

      // echo $newRawSOA;
      $updated = str_replace($oldRawSOA, $newRawSOA, $file);

      return $updated;

    }
    function RenderNS($file){
      $updated = "";

      foreach($this->nameservers as $ns){
        $domain = $ns['domain'];
        $ttl = $ns['ttl'];
        $address = $ns['address'];

        $updated .= "$domain $ttl IN NS $address\n";
      }

      $updatedFile = preg_replace(
        "/($this->markerNSStart)(.|\n|\r\n)+($this->markerNSEnd)/",
        "$this->markerNSStart\n$updated$this->markerNSEnd", $file);

      return $updatedFile;
    }
    function RenderRecords($file){

      $updated = '';

      foreach($this->records as $record){
        if(!$record['enabled']) $updated .= '; ';

        $domain = $record['domain'];
        $ttl = $record['ttl'];
        $type = $record['type'];
        $address = $record['address'];
        $id = $record['id'];

        if($type == 'MX'){
          $priority = $record['priority'];
          $updated .= "$domain $ttl IN $type $priority $address";
        }else{
          $updated .= "$domain $ttl IN $type $address";
        }

        if(!empty($id)){
          $updated .= " ;RID_$id\n";
        }else{
          $updated .= "\n";
        }
      }

      $updated = preg_replace("/[\n]$/", '', $updated);

      $updatedFull = preg_replace(
        "/($this->markerRecordsStart)(.|\n|\r\n)+($this->markerRecordsEnd)/",
        "$this->markerRecordsStart\n$updated\n$this->markerRecordsEnd", $file);

      return $updatedFull;
    }

    function UpdateSOA(array $newParams){
      $newSoa = [];
      if(isset($newParams['domain'])){
        $newSoa['domain'] = $newParams['domain'];
      }
      if(isset($newParams['ttl']) and preg_match("/^[0-9]+$/", $newParams['ttl'])){
        $newSoa['ttl'] = $newParams['ttl'];
      }
      if(isset($newParams['primaryNS'])){
        $newSoa['primaryNS'] = $newParams['primaryNS'];
      }
      if(isset($newParams['mail'])){
        $newSoa['mail'] = $newParams['mail'];
      }
      if(isset($newParams['serial']) and preg_match("/^[0-9]+$/", $newParams['serial'])){
        $newSoa['serial'] = $newParams['serial'];
      }
      if(isset($newParams['refresh']) and preg_match("/^[0-9]+$/", $newParams['refresh'])){
        $newSoa['refresh'] = $newParams['refresh'];
      }
      if(isset($newParams['retry']) and preg_match("/^[0-9]+$/", $newParams['retry'])){
        $newSoa['retry'] = $newParams['retry'];
      }
      if(isset($newParams['expire']) and preg_match("/^[0-9]+$/", $newParams['expire'])){
        $newSoa['expire'] = $newParams['expire'];
      }
      if(isset($newParams['minimum']) and preg_match("/^[0-9]+$/", $newParams['minimum'])){
        $newSoa['minimum'] = $newParams['minimum'];
      }
      if($newSoa == $newParams){
        foreach ($newSoa as $param => $value) {
          $this->soa[$param] = $newSoa[$param];
        }
        return true;
      }else{
        $this->e = true;
        $error_message = __FUNCTION__."(): Fatal error: Invalid params were passed.\n".$this->error;
        $this->error = $error_message;
        error_log($error_message);
        return false;
      }
    }

    function ValidateRecord($record){
      $valid = true;
      if(empty($record['domain'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'domain' element is missing in passed record.\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(!(!empty($record['ttl']) and preg_match("/[0-9]+/", $record['ttl']))){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'ttl' element is missing or invalid (should be a number).\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(empty($record['type']) or !preg_match("/^(A|NS|CNAME|TXT|MX)$/", $record['type'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'type' element is missing or not supported (Supported: A, NS, CNAME, TXT, MX).\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(!empty($record['priority']) and !preg_match("/[0-9]+/", $record['priority'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'priority' element is not valid (should be a number).\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(empty($record['address'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'address' element is missing.\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      return $valid;
    }
    function AddRecord($recordNew){
      // validate each field (different for different record types)
      // check whether same record already exists (same domain and value)

      $valid = $this->ValidateRecord($recordNew);

      if(empty($recordNew['priority']) and $recordNew['type'] == 'MX'){
        $recordNew['priority'] = 0;
        $this->warning = __FUNCTION__."(): Warning: Priority of MX record was not passed, so it was set to default value (0).";
        error_log($this->warning);
      }

      if($valid){

        // existence check
        $numId = null;
        foreach($this->records as $id => $record){
          if($record['id'] == $recordNew['id']){
            $numId = $id;
            break;
          }elseif($record['domain'] == $recordNew['domain'] and $record['type'] == $recordNew['type']){
            $numId = $id;
            break;
          }
        }

        if($numId == null and $numId !== 0){
          array_push($this->records, $recordNew);
          return true;
        }else{
          $this->e = true;
          $this->error = __FUNCTION__."(): Unable to add record '".$recordNew['id']."'. Either record with this domain or id ".$recordNew['id']." already exists.".$this->error;
          return false;
        }

      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Passed record array is not valid.";
        error_log($this->error);
        return false;
      }


    }
    function RemoveRecordById($textId){
      $numId = -1;
      foreach($this->records as $id => $record){
        // print_r($record);
        if($record['id'] == $textId){
          $numId = $id;
          break;
        }
      }
      if($numId !== -1){
        unset($this->records[$numId]);
        return true;
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Record with id $textId does not exist, therefore cannot be removed. \n".$this->error;
        error_log($this->error);
        return false;
      }
    }
    function RemoveRecord($recordToRemove){
      $numId = null;
      foreach($this->records as $id => $record){
        if($record['domain'] == $recordToRemove['domain'] and $record['address'] == $recordToRemove['address'] and $record['type'] == $recordToRemove['type']){
          $numId = $id;
          break;
        }
      }
      if(!empty($numId) or $numId == 0){
        unset($this->records[$numId]);
        return true;
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Record with selected parameters does not exist, therefore cannot be removed. \n".$this->error;
        error_log($this->error);
        return false;
      }
    }
    function UpdateRecordById($textId, $recordNew){
      $numId = -1;
      foreach($this->records as $id => $record){
        // print_r($record);
        if($record['id'] == $textId){
          $numId = $id;
          break;
        }
      }
      if($numId !== -1){
        $recordNewFull = $this->records[$numId];
        foreach($recordNew as $param => $value){
          $recordNewFull[$param] = $value;
        }
        if($this->ValidateRecord($recordNewFull)){
          $this->records[$numId] = $recordNewFull;
          return true;
        }else{
          $this->e = true;
          $this->error = __FUNCTION__."(): Record update error: Record with given parameters is not valid. \n".$this->error;
          error_log($this->error);
          return false;
        }
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Record update error: Record with id '$textId' was not found. \n".$this->error;
        error_log($this->error);
        return false; // no record with such id
      }
    }
    function UpdateRecord($recordOld, $recordNew){
      $numId = -1;
      foreach($this->records as $id => $record){
        if($record['domain'] == $recordOld['domain'] and $record['address'] == $recordOld['address'] and $record['type'] == $recordOld['type']){
          $numId = $id;
          break;
        }
      }
      if($numId !== -1){
        $recordNewFull = $this->records[$numId];
        foreach($recordNew as $param => $value){
          $recordNewFull[$param] = $value;
        }
        if($this->ValidateRecord($recordNewFull)){
          $this->records[$numId] = $recordNewFull;
          return true;
        }else{
          $this->e = true;
          $this->error = __FUNCTION__."(): Record update error: Record with given parameters is not valid. \n".$this->error;
          error_log($this->error);
          return false;
        }
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Record update error: Record with given data was not found. \n".$this->error;
        error_log($this->error);
        return false; // no record with such data
      }
    }

    function ValidateNameserver($newNS){
      $valid = true;
      if(!isset($newNS['domain'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'domain' element is missing in passed record.\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(!isset($newNS['ttl'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'ttl' element is missing in passed record.\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }elseif(!preg_match("/^[0-9]+$/", $newNS['ttl'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'ttl' element is not valid (should be a number).\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      if(!isset($newNS['address'])){
        $this->e = true;
        $this->error = __FUNCTION__."(): Error: 'address' element is missing in passed record.\n".$this->error;
        error_log($this->error);
        return false;
        $valid = false;
      }
      return $valid;
    }
    function UpdateNameserver(int $i, array $newParams){
      $newFullNS = $this->nameservers[$i];
      foreach($newParams as $param => $value){
        $newFullNS[$param] = $value;
      }
      if($this->ValidateNameserver($newFullNS)){
        $this->nameservers[$i] = $newFullNS;
        return true;
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Nameserver update error: NS with given parameters is not valid. \n".$this->error;
        error_log($this->error);
        return false;
      }
    }
    function UpdateNameservers(array $newNameservers){
      $newNS = [];
      foreach($newNameservers as $i => $newParams){

        $newFullNS = $this->nameservers[$i];
        foreach($newParams as $param => $value){
          $newFullNS[$param] = $value;
        }
        if($this->ValidateNameserver($newFullNS)){
          $newNS[$i] = $newFullNS;
        }else{
          $this->e = true;
          $this->error = __FUNCTION__."(): Nameserver validation error: NS with given parameters is not valid. \n".$this->error;
          error_log($this->error);
          return false;
        }
        // if(isset($newParams['domain'])){
        //   $this->nameservers[$i]['domain'] = $newParams['domain'];
        // }
        // if(isset($newParams['ttl']) and preg_match("/^[0-9]+$/", $newParams['ttl'])){
        //   $this->nameservers[$i]['ttl'] = $newParams['ttl'];
        // }
        // if(isset($newParams['address'])){
        //   $this->nameservers[$i]['address'] = $newParams['address'];
        // }
      }

      if(count($newNS) == count($newNameservers)){
        // $this->nameservers = $newNS;
        foreach($newNS as $id => $nameserver){
          $this->nameservers[$id] = $nameserver;
        }
        return true;
      }else{
        $this->e = true;
        $this->error = __FUNCTION__."(): Nameserver update error: Some of given NS's are not valid. \n".$this->error;
        error_log($this->error);
        return false;
      }

    }

    function Save($forceUpdateSerial = false){

      if($forceUpdateSerial or $this->Render() !== $this->fileContents){
        $this->soa['serial']++;
      }

      // $jsonSaved = $this->SaveToJson();
      // return $jsonSaved and $fileSaved ? true : false;
      $saveSuccessful = $this->SaveToFile();

      if($saveSuccessful){
        return true;
      }else{
        $this->e = true;
        $error_message = __FUNCTION__."(): Zone file saving failed. More Details:\n".$this->error;
        $this->error = $error_message;
        error_log($error_message);
        return false;
      }

    }
    private function SaveToFile(){

      if($this->saveOrig){
        $origFile = @file_get_contents($this->domainFile);
        if(!$origFile){
          $this->e = true;
          $error_message = __FUNCTION__."(): Attempt to save original file failed. Unable to retrieve zone file.\n".$this->error;
          $this->error = $error_message;
          error_log($error_message);
          return false;
        }else{
          $origSaved = @file_put_contents($this->domainFile.'.orig', $origFile);
          if(!$origSaved){
            $this->e = true;
            $error_message = __FUNCTION__."(): Attempt to save original file failed. Unable to save original zone file.\n".$this->error;
            $this->error = $error_message;
            error_log($error_message);
            return false;
          }
        }
      }

      $newContent = $this->Render();
      $save = @file_put_contents($this->domainFile, $newContent);

      if($save){
        return true;
      }else{
        $this->e = true;
        $error_message = __FUNCTION__."(): Unable to save updated file.\n".$this->error;
        $this->error = $error_message;
        error_log($error_message);
        return false;
      }

    }
    private function SaveToJson(){
      $json = json_encode([
        'soa' => $this->soa,
        'nameservers' => $this->nameservers,
        'records' => $this->records
      ], JSON_PRETTY_PRINT);
      return file_put_contents('data.json', $json);
    }

    function Reload(){
      // NOTE: This function should be executed inside the docker container with the server.
      // Change this function if this class and bind server will be in different environments.
      // shell_exec('rndc reload');
      if(function_exists('zone_reload')){
        if(zone_reload()){ // zone_reload - predefined function outside of this class
          return true;
        }else{
          // reverting changes and reloading again
          $this->e = true;
          $this->error = __FUNCTION__."(): Zone reload failed.\n".$this->error;
          error_log($this->error);
          $origFile = @file_get_contents($this->domainFile.'.orig');
          if(!$origFile){
            $this->error = __FUNCTION__."(): Attempt to revert changes failed due to inability to load .orig file.\n".$this->error;
            error_log($this->error);
            return false;
          }else{
            $revert = @file_put_contents($this->domainFile, $origFile);
            if($revert){
              if(zone_reload()){
                $this->error = __FUNCTION__."(): Zone file was reverted successfully.\n".$this->error;
                error_log($this->error);
                return false;
              }else{
                $this->error = __FUNCTION__."(): Zone file was reverted, but it is still invalid. Review required.\n".$this->error;
                error_log($this->error);
                return false;
              }
            }else{
              $this->error = __FUNCTION__."(): Attempt to revert changes failed due to inability to save zone file.\n".$this->error;
              error_log($this->error);
              return false;
            }
          }
        }
      }
    }

  }

?>
