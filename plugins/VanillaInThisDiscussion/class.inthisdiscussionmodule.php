<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

/**
 * Renders a list of users who are taking part in a particular discussion.
 */
class InThisDiscussionModule extends Module {
   
   protected $_UserData;
   
   public function __construct(&$Sender = '') {
      $this->_UserData = FALSE;
      parent::__construct($Sender);
   }
   
   public function GetData($DiscussionID, $Limit = 50) {
      $SQL = Gdn::SQL();
      $this->_UserData = $SQL
         ->Select('u.UserID, u.Name')
         ->Select('p.Name', '', 'Photo')
         ->Select('c.DateInserted', 'max', 'DateLastActive')
         ->From('User u')
         ->Join('Photo p', 'u.PhotoID = p.PhotoID', 'left')
         ->Join('Comment c', 'u.UserID = c.InsertUserID')
         ->Where('c.DiscussionID', $DiscussionID)
         ->GroupBy('u.UserID, u.Name, p.Name')
         ->OrderBy('u.Name', 'asc')
         ->Get();
   }

   public function AssetTarget() {
      return 'Panel';
   }

   public function ToString() {
      $String = '';
      ob_start();
      ?>
      <div class="Box">
         <h4><?php echo Gdn::Translate('In this Discussion'); ?></h4>
         <ul class="PanelInfo">
         <?php
         foreach ($this->_UserData->Result() as $User) {
            ?>
            <li>
               <h2><?php
                  echo Anchor($User->Name, '/profile/'.urlencode($User->Name), 'UserLink');
               ?></h2>
               <?php
                  echo Format::Date($User->DateLastActive);
               ?>
            </li>
            <?php
         }
         ?>
         </ul>
      </div>
      <?php
      $String = ob_get_contents();
      @ob_end_clean();
      return $String;
   }
}