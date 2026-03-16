<#1>
<?php
if($ilDB->tableExists('settings')) {
    $ilDB->manipulate("DELETE FROM settings WHERE module = 'lfpm' and keyword = 'action';");
}
?>
