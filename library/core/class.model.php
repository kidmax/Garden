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
 * Represents, enforces integrity, and aids in the management of: data. This
 * generic model can be instantiated (with the table name it is intended to
 * represent) and used directly, or it can be extended and overridden for more
 * complicated procedures related to different tables.
 *
 *
 * @author Mark O'Sullivan
 * @copyright 2009 Mark O'Sullivan
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Garden
 * @version @@GARDEN-VERSION@@
 * @namespace Lussumo.Garden.Core
 */


if (!defined('APPLICATION'))
   exit();


/**
 * Represents, enforces integrity, and aids in the management of: data. This
 * generic model can be instantiated (with the table name it is intended to
 * represent) and used directly, or it can be extended and overridden for more
 * complicated procedures related to different tables.
 *
 * @package Garden
 */
class Model extends Gdn_Pluggable {


   /**
    * An object representation of the current working dataset.
    *
    * @var object
    */
   public $Data;


   /**
    * Database object
    *
    * @var Gdn_Database The database object.
    */
   public $Database;


   /**
    * The name of the field that stores the insert date for a record. This
    * field will be automatically filled by the model if it exists.
    *
    * @var string
    */
   public $DateInserted = 'DateInserted';


   /**
    * The name of the field that stores the update date for a record. This
    * field will be automatically filled by the model if it exists.
    *
    * @var string
    */
   public $DateUpdated = 'DateUpdated';


   /**
    * The name of the field that stores the id of the user that inserted it.
    * This field will be automatically filled by the model if it exists and
    * @@Session::UserID is a valid integer.
    *
    * @var string
    */
   public $InsertUserID = 'InsertUserID';


   /**
    * The name of the table that this model is intended to represent. The
    * default value assigned to $this->Name will be the name that the
    * model was instantiated with (defined in $this->__construct()).
    *
    * @var string
    */
   public $Name;


   /**
    * The name of the primary key field of this model. The default is 'id'. If
    * $this->DefineSchema() is called, this value will be automatically changed
    * to any primary key discovered when examining the table schema.
    *
    * @var string
    */
   public $PrimaryKey = 'id';


   /**
    * An object that is used to store and examine database schema information
    * related to this model. This object is defined and populated with
    * $this->DefineSchema().
    *
    * @var object
    */
   public $Schema;
   
   /**
    * Contains the sql driver for the object.
    *
    * @var Gdn_SQLDriver
    */
   public $SQL;


   /**
    * The name of the field that stores the id of the user that updated it.
    * This field will be automatically filled by the model if it exists and
    * @@Session::UserID is a valid integer.
    *
    * @var string
    */
   public $UpdateUserID = 'UpdateUserID';


   /**
    * An object that is used to manage and execute data integrity rules on this
    * object. By default, this object only enforces maxlength, data types, and
    * required fields (defined when $this->DefineSchema() is called).
    *
    * @var object
    */
   public $Validation;


   /**
    * Class constructor. Defines the related database table name.
    *
    * @param string $Name An optional parameter that allows you to explicitly define the name of
    * the table that this model represents. You can also explicitly set this
    * value with $this->Name.
    */
   public function __construct($Name = '') {
      if ($Name == '')
         $Name = get_class($this);

      $this->Database = Gdn::Database();
      $this->SQL = $this->Database->SQL();
      $this->Validation = new Gdn_Validation();
      $this->Name = $Name;
      parent::__construct();
   }


   /**
    * Connects to the database and defines the schema associated with
    * $this->Name. Also instantiates and automatically defines
    * $this->Validation.
    *
    */
   public function DefineSchema() {
      if (!isset($this->Schema)) {
         $this->Schema = new Gdn_Schema($this->Name, $this->Database);
         $this->PrimaryKey = $this->Schema->PrimaryKey($this->Name, $this->Database);
         if (is_array($this->PrimaryKey)) {
            print_r($this->PrimaryKey);
            $this->PrimaryKey = $this->PrimaryKey[0];
         }

         $this->Validation->ApplyRulesBySchema($this->Schema);
      }
   }


   /**
    *  Takes a set of form data ($Form->_PostValues), validates them, and
    * inserts or updates them to the datatabase.
    *
    * @param array $FormPostValues An associative array of $Field => $Value pairs that represent data posted
    * from the form in the $_POST or $_GET collection.
    * @param array $Settings If a custom model needs special settings in order to perform a save, they
    * would be passed in using this variable as an associative array.
    * @return unknown
    */
   public function Save($FormPostValues, $Settings = FALSE) {
      // Define the primary key in this model's table.
      $this->DefineSchema();

      // See if a primary key value was posted and decide how to save
      $PrimaryKeyVal = ArrayValue($this->PrimaryKey, $FormPostValues);
      $Insert = $PrimaryKeyVal === FALSE ? TRUE : FALSE;
      if ($Insert) {
         $this->AddInsertFields($FormPostValues);
      } else {
         $this->AddUpdateFields($FormPostValues);
      }

      // Validate the form posted values
      if ($this->Validate($FormPostValues, $Insert) === TRUE) {
         $Fields = $this->Validation->ValidationFields();
         $Fields = RemoveKeyFromArray($Fields, $this->PrimaryKey); // Don't try to insert or update the primary key
         if ($Insert === FALSE) {
            $this->Update($Fields, array($this->PrimaryKey => $PrimaryKeyVal));
         } else {
            $PrimaryKeyVal = $this->Insert($Fields);
         }
      }
      return $PrimaryKeyVal;
   }


   /**
    * @param unknown_type $Fields
    * @return unknown
    * @todo add doc
    */
   public function Insert($Fields) {
      $Result = FALSE;
      if ($this->Validate($Fields, TRUE)) {
         $this->AddInsertFields($Fields);
         $Result = $this->SQL->Insert($this->Name, $Fields);
      }
      return $Result;
   }


   /**
    * @param unknown_type $Fields
    * @param unknown_type $Where
    * @param unknown_type $Limit
    * @todo add doc
    */
   public function Update($Fields, $Where = FALSE, $Limit = FALSE) {
      $Result = FALSE;
      if ($this->Validate($Fields)) {
         $this->AddUpdateFields($Fields);
         $Result = $this->SQL->Put($this->Name, $Fields, $Where, $Limit);
      }
      return $Result;
   }


   /**
    * @param unknown_type $Where
    * @param unknown_type $Limit
    * @param unknown_type $ResetData
    * @todo add doc
    */
   public function Delete($Where = '', $Limit = FALSE, $ResetData = FALSE) {
      if($ResetData) {
         $this->SQL->Delete($this->Name, $Where, $Limit);
      } else {
         $this->SQL->NoReset()->Delete($this->Name, $Where, $Limit);
      }
   }


   /**
    * @param unknown_type $OrderFields
    * @param unknown_type $OrderDirection
    * @param unknown_type $Limit
    * @param unknown_type $Offset
    * @return unknown
    * @todo add doc
    */
   public function Get($OrderFields = '', $OrderDirection = 'asc', $Limit = FALSE, $Offset = FALSE) {
      return $this->SQL->Get($this->Name, $OrderFields, $OrderDirection, $Limit, $Offset);
   }
   
   /**
    * Returns a count of the # of records in the table
    * @param array $Wheres
    */
   public function GetCount($Wheres = '') {
      $this->SQL
         ->Select('*', 'count', 'Count')
         ->From($this->Name);

      if (is_array($Wheres))
         $this->SQL->Where($Wheres);

      $Data = $this->SQL
         ->Get()
         ->FirstRow();

      return $Data === FALSE ? 0 : $Data->Count;
   }

   /**
    * @param unknown_type $Where
    * @param unknown_type $OrderFields
    * @param unknown_type $OrderDirection
    * @param unknown_type $Limit
    * @param unknown_type $Offset
    * @return unknown
    * @todo add doc
    */
   public function GetWhere($Where = FALSE, $OrderFields = '', $OrderDirection = 'asc', $Limit = FALSE, $Offset = FALSE) {
      return $this->SQL->GetWhere($this->Name, $Where, $OrderFields, $OrderDirection, $Limit, $Offset);
   }


   /**
    * Returns the $this->Validation->ValidationResults() array.
    *
    * @return unknown
    * @todo add return type
    */
   public function ValidationResults() {
      return $this->Validation->Results();
   }


   /**
    * @param unknown_type $FormPostValues
    * @param unknown_type $Insert
    * @return unknown
    * @todo add doc
    */
   public function Validate($FormPostValues, $Insert = FALSE) {
      return $this->Validation->Validate($FormPostValues, $Insert);
   }


   /**
    * Adds $this->InsertUserID and $this->DateInserted fields to an associative
    * array of fieldname/values if those fields exist on the table being
    * inserted.
    *
    * @param array $Fields The array of fields to add the values to.
    */
   protected function AddInsertFields(&$Fields) {
      $this->DefineSchema();
      if ($this->Schema->FieldExists($this->Name, $this->DateInserted)) {
         $Fields[$this->DateInserted] = Format::ToDateTime();
      }

      $Session = Gdn::Session();
      if ($Session->UserID > 0 && $this->Schema->FieldExists($this->Name, $this->InsertUserID))
         $Fields[$this->InsertUserID] = $Session->UserID;
   }


   /**
    * Adds $this->UpdateUserID and $this->DateUpdated fields to an associative
    * array of fieldname/values if those fields exist on the table being
    * updated.
    *
    * @param array $Fields The array of fields to add the values to.
    */
   protected function AddUpdateFields(&$Fields) {
      $this->DefineSchema();
      if ($this->Schema->FieldExists($this->Name, $this->DateUpdated)) {
         $Fields[$this->DateUpdated] = Format::ToDateTime();
      }

      $Session = Gdn::Session();
      if ($Session->UserID > 0 && $this->Schema->FieldExists($this->Name, $this->UpdateUserID))
         $Fields[$this->UpdateUserID] = $Session->UserID;
   }

}
