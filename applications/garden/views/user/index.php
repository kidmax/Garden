<?php if (!defined('APPLICATION')) exit();
$Session = Gdn::Session();
$EditUser = $Session->CheckPermission('Garden.Users.Edit');
echo $this->Form->Open(array('action' => Url('/user/browse')));
?>
<h1><?php echo Gdn::Translate('Manage Users'); ?></h1>
<ul>
   <li><?php
      echo $this->Form->Errors();
      echo $this->Form->TextBox('Keywords');
      echo $this->Form->Button('Go');
      printf(Gdn::Translate('%s user(s) found.'), $this->Pager->TotalRecords);
      echo Anchor('Add User', 'garden/user/add', 'Button Popup');
   ?></li>
</ul>
<table id="Users" class="AltColumns">
   <thead>
      <tr>
         <th>Username</th>
         <th class="Alt">Email</th>
         <th>First Visit</th>
         <th class="Alt">Last Visit</th>
         <?php if ($EditUser) { ?>
            <th>Options</th>
         <?php } ?>
      </tr>
   </thead>
   <tbody>
      <?php
      echo $this->Pager->ToString('less');
      include($this->FetchViewLocation('users'));
      echo $this->Pager->ToString('more');
      ?>
   </tbody>
</table>
<?php
echo $this->Form->Close();