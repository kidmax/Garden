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
 * Validating, Setting, and Retrieving session data in cookies.
 * @author Mark O'Sullivan
 * @copyright 2009 Mark O'Sullivan
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Garden
 * @version @@GARDEN-VERSION@@
 * @namespace Lussumo.Garden.Core
 */




/**
 * Validating, Setting, and Retrieving session data in cookies. The HMAC
 * Hashing method used here was inspired by Wordpress 2.5 and this document in
 * particular: http://www.cse.msu.edu/~alexliu/publications/Cookie/cookie.pdf
 *
 * @package Garden
 */
class Gdn_PasswordAuthenticator implements Gdn_IAuthenticator {

   /**
    * @var Gdn_IIdentity
    */
   private $_Identity = null;


   /**
    * @var UserModel
    */
   private $_UserModel = null;


   /**
    * @var PermissionModel
    */
   private $_PermissionModel = null;
   
   
   public function __construct() {
      $this->_Identity = Gdn::Factory('Identity');
      $this->_Identity->Init();
   }


   /**
    * @return PermissionModel
    */
   public function GetPermissionModel() {
      if ($this->_PermissionModel === null) {
         $this->_PermissionModel = Gdn::PermissionModel();
      }
      return $this->_PermissionModel;
   }


   /**
    * @return UserModel
    */
   public function GetUserModel() {
      if ($this->_UserModel === null) {
         $this->_UserModel = Gdn::UserModel();
      }
      return $this->_UserModel;
   }


   /**
    * @param PermissionModel $PermissionModel
    */
   public function SetPermissionModel($PermissionModel) {
      $this->_PermissionModel = $PermissionModel;
   }


   /**
    * @param Gdn_UserModel $UserModel
    */
   public function SetUserModel($UserModel) {
      $this->_UserModel = $UserModel;
   }

   public function Authenticate($Data) {
      // Backwards compatibility.
      if(func_num_args() >= 3) {
         $Args = func_get_args();
         $this->_Authenticate($Args[0], $Args[1], $Args[2]);
         return;
      }
      
      return $this->_Authenticate(
         ArrayValue('Name', $Data),
         ArrayValue('Password', $Data),
         ArrayValue('RememberMe', $Data, FALSE),
         ArrayValue('ClientHour', $Data, ''));
   }

   /**
    * Returns the unique id assigned to the user in the database, 0 if the
    * username/password combination weren't found, or -1 if the user does not
    * have permission to sign in.
    *
    * @param string $Username The unique name assigned to the user in the database.
    * @param string $Password The password assigned to the user in the database.
    * @param boolean $PersistentSession Should the user's session remain persistent across visits?
    * @param int $ClientHour The current hour (24 hour format) of the client.
    */
   protected function _Authenticate($Username, $Password, $PersistentSession, $ClientHour = '') {
      $UserID = 0;

      // Retrieve matching username/password values
      $UserModel = $this->GetUserModel();
      $UserData = $UserModel->ValidateCredentials($Username, 0, $Password);
      if ($UserData !== False) {
         // Get ID
         $UserID = $UserData->UserID;

         // Get Sign-in permission
         $SignInPermission = $UserData->Admin == '1' ? TRUE : FALSE;
         if ($SignInPermission === FALSE) {
            $PermissionModel = $this->GetPermissionModel();
            foreach($PermissionModel->GetUserPermissions($UserID) as $Permissions) {
               $SignInPermission |= ArrayValue('Garden.SignIn.Allow', $Permissions, FALSE);
            }
         }

         // Update users Information
         $UserID = $SignInPermission ? $UserID : -1;
         if ($UserID > 0) {
            // Create the session cookie
            $this->_Identity->SetIdentity($UserID, $PersistentSession);

            // Update some information about the user...
            $UserModel->UpdateLastVisit($UserID, $UserData->Attributes, $ClientHour);
         }
      }
      return $UserID;
   }


   /**
    * Destroys the user's session cookie - essentially de-authenticating them.
    */
   public function DeAuthenticate() {
      $this->_Identity->SetIdentity(NULL);
   }


   /**
    * Returns the unique id assigned to the user in the database (retrieved
    * from the session cookie if the cookie authenticates) or FALSE if not
    * found or authentication fails.
    *
    * @return int
    */
   public function GetIdentity() {
      $Result = $this->_Identity->GetIdentity();
      if($Result < 0)
         $Result = 0;
      return $Result;
   }
   
   public function RegisterUrl($Redirect = '/') {
      return $this->SignInUrl($Redirect);
	}
   
   public function SignInUrl($Redirect = '/') {
		$Url = '/entry/?Target='.urlencode($Redirect);
		return $Url;
   }
}