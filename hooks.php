<?php
use Illuminate\Database\Capsule\Manager as Capsule;
add_hook('AdminAreaPage', 1, function($vars) {
    if($_REQUEST["action"]== "createconfig"){
        require_once 'cometbackup.php';       
       cometbackup_user_configoption_create($_REQUEST['id']);
      
        exit('yes');
    }

   
});
?>
