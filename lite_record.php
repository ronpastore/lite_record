<?php

 /*  
            Lite Record, version 1.0
            
            The MIT License
           
            Copyright (c) 2007 Ron Pastore <vacorama@gmail.com>
            http://www.100w.com
            
            Permission is hereby granted, free of charge, to any person obtaining a copy
            of this software and associated documentation files (the "Software"), to deal
            in the Software without restriction, including without limitation the rights
            to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
            copies of the Software, and to permit persons to whom the Software is
            furnished to do so, subject to the following conditions:
            
            The above copyright notice and this permission notice shall be included in
            all copies or substantial portions of the Software.
            
            THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
            IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
            FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
            AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
            LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
            OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
            THE SOFTWARE.
 */



class LiteRecord {
    
    function __construct($mysqli) { 
        $this->db = $mysqli;
    }
    
    /**
     * LiteRecord considers all protected properties to be database fields
     * Use reflection to return all protected properties...
     */
    public function getModelAttributes() {
        $reflection_class = new ReflectionClass(get_class($this));
        $protected_properties = array();
        foreach ($reflection_class->getDefaultProperties() as $property_key=>$propery_value  )  {
            $reflection_property = new ReflectionProperty(get_class($this), $property_key);
            if ($reflection_property->isProtected()) {
                $protected_properties[] = $property_key;    
            }
        }
        return $protected_properties;
    }
    
    public function loadAssociations() {
        if (!isset($this->associations)) {
            return true;
        }
        foreach ($this->associations as $association_info_array)  {
            if ($association_info_array['association_type'] == 'has_many') {
                $this->setHasManyAssociation($association_info_array);   
            } elseif ($association_info_array['association_type'] == 'belongs_to') {
                $this->setBelongsToAssociation($association_info_array); 
            } elseif ($association_info_array['association_type'] == 'has_one') {
                $this->setHasOneAssociation($association_info_array);
            } elseif ($association_info_array['association_type'] == 'has_many_through') { 
                $this->setHasManyThrough($association_info_array);
            } else {
                throw new Exception ('unsupported assoiciation type: ' . $association_type);
            }
        }
    }
    
    public function loadAssociationFor($model_name) {
        foreach ($this->associations as $association_info_array) {
            if ($association_info_array['model_name'] == $model_name ){
                if ( $association_info_array['association_type'] == 'has_many') {
                    $this->setHasManyAssociation($association_info_array);
                } elseif( $association_info_array['association_type'] == 'has_one') {
                    $this->setHasOneAssociation($association_info_array);
                } elseif ( $association_info_array['association_type'] == 'belongs_to') {
                    $this->setBelongsToAssociation($association_info_array);
                } else {
                    throw new Exception("unknown association type", 9283472);
                }
            }
        }
    }
    
    private function setHasOneAssociation($association_info_array) {
        $finder = new $association_info_array['model_name'];
        $foreign_key_name = get_class($this) . "_id";
        $foreign_id = $this->id;
        $name  = $association_info_array['model_name'];

        if (isset($association_info_array['order_sql'])) {
            $set = $finder->getResourceSet("WHERE $foreign_key_name={$this->id}", $association_info_array['order_sql']);
        } else {
            $set = $finder->getResourceSet("WHERE $foreign_key_name={$this->id}");  
        }
            
        if (count($set) > 0) {
            $this->$name = $set[0];
        } else {
            $this->$name = null;
        }
    }
    
    private function setBelongsToAssociation($association_info_array) {
        $finder = new $association_info_array['model_name'];
        $foreign_key_name = $association_info_array['model_name'] . "_id";
        $name  = $association_info_array['model_name'];
        if (isset($association_info_array['additional_where_sql'])) {
            $AND = $association_info_array['additional_where_sql'];
        } else {
            $AND = '';
        }
        $set = $finder->getResourceSet("WHERE id={$this->$foreign_key_name} $AND");
        if (count($set) > 0) {
            $this->$name = $set[0];
        } else {
            $this->$name = null;
        }
        
    }
    
    private function setHasManyThrough($association_info_array) {
        $foreign_field_name = strtolower(get_class($this) . "_id");
        
        $sql = "SELECT {$association_info_array['table_name']}.* FROM {$association_info_array['table_name']}
                JOIN {$association_info_array['join_table']} ON ( {$association_info_array['table_name']}.id = {$association_info_array['join_table']}.{$association_info_array['join_table_foreign_id']} )
                WHERE {$association_info_array['join_table']}.$foreign_field_name = $this->id {$association_info_array['additional_where_sql']}";
        
        $query = $this->db->query($sql);
        $misc_object_set = array();     
        
        foreach ($query->result() as $row ) {
            $class =$association_info_array['model_name'];
            $misc_object = new $class();
            
            foreach ($misc_object->getModelAttributes() as $attribute)  {
                $misc_object->set($attribute, $row->$attribute);                
            }
            $misc_object_set[] = $misc_object;
        }
        $set_name  = $association_info_array['model_name'] . "_set";
        $this->$set_name = $misc_object_set;
    }
    
    private function setHasManyAssociation($association_info_array) {
        $finder = new $association_info_array['model_name'];
        $foreign_key_name = get_class($this) . "_id";
        $foreign_id = $this->id;
        $set_name  = $association_info_array['model_name'] . "_set";
        if (!empty($association_info_array['order_sql'])) {
            $this->$set_name = $finder->getResourceSet("WHERE $foreign_key_name={$this->id}", $association_info_array['order_sql']);
        } else {
            $this->$set_name = $finder->getResourceSet("WHERE $foreign_key_name={$this->id}");
        }
    }
    
    public function save() {
        $set_attributes = $this->getAttributesThatHaveBeenSet();
        if (!isset($this->id)) {
            $this->id =  $this->createFromArray($set_attributes);
            return $this->id;
        } else {
            return $this->updateFromArray($set_attributes);
        }
    }
    
    private function getAttributesThatHaveBeenSet() {
        $set_attributes = array();
        foreach($this->getModelAttributes() as $attribute) {
                if (isset($this->$attribute)) {
                    $set_attributes[$attribute] = $this->$attribute;
                }
        }
        return $set_attributes;
    }
    
    public function get($attribute) {
        if (!empty($this->$attribute)) {
            //needs some formatting here
            //if we have single or double quotes, should probably
            // user htmlspecialchars with ENT_QUOTES
            
            return $this->$attribute;   
        } else {
            return false;
        }
        
    }
    
    public function set($attribute, $value) {
        $this->$attribute = $value;
    }
    
    public function setfromArray($array) {
        foreach ($array as $possible_attribute=>$value) {
            if ($this->isAttribute($possible_attribute)) {
                $this->set($possible_attribute, $value);
            }
        }
    }
    
    public function isAttribute($attribute) {
        if (!in_array ($attribute, $this->getModelAttributes() ) ) {
            return false;
        } else {
            return true;
        }
    }
        
    public function createFromArray($values) {
        
        
        $sql = "INSERT INTO {$this->table_name} SET ";
        
        $values['created_on'] = date("Y-m-d H:i:s");

        foreach ($values as $key=>$value) {
            if (!$this->isAttribute($key) || is_array($value)) {
                continue; 
            }
            $value = mysql_real_escape_string($value);
            $sql .= "$key='$value',";
        }
        
        $sql .= '|'; //just helps remove last comma 
        $sql = str_replace (",|", "", $sql);
        $this->db->query($sql); 
        $this->id = $this->db->insert_id();
        return $this->id;
    }
    
    public function updateFromArray($values) {
        
        $values['updated_on'] = date("Y-m-d H:i:s");
        
        $sql = "UPDATE {$this->table_name} SET ";
        foreach ($values as $key=>$value) {
            if (!$this->isAttribute($key) || is_array($value)) {
                continue; 
            }
            $value = mysql_real_escape_string($value);
            $sql .= "$key='$value',";
        }

        $sql .= '|';
        $sql = str_replace (",|", "", $sql);
        $sql .= "WHERE id={$this->id}";
        return $this->db->query($sql);
    }
    
    public function getResourceSet($where = '', $order='', $limit='', $load_associations=false) {
        $sql = "SELECT * FROM {$this->table_name} $where $order $limit";  
        $query = $this->db->query($sql);

        $misc_object_set = array();     
        
        foreach ($query->result() as $row ) {
            $class = get_class($this);
            $misc_object = new $class();
            
            foreach ($this->getModelAttributes() as $attribute)  {
                $misc_object->set($attribute, $row->$attribute);                
            }
            
            if ($load_associations) {
                $misc_object->loadAssociations();   
            }
            $misc_object_set[] = $misc_object;
        }
        
        return $misc_object_set;
    }
    
    public function toArray() {
        $array = array();
        foreach($this->getModelAttributes() as $attribute) {
                if (isset($this->$attribute)) {
                    $array[$attribute] = $this->$attribute;
                } else {
                    $array[$attribute] = '';
                }
        }
        return $array;
    }
    
    public function toJSON() {
        return json_encode($this->toArray());
    }
    
    public function getResourceSetAsJSON($where = null) {
        $array_for_json = array();
        foreach ($this->getResourceSet($where) as $resource) {
            $array_for_json[] = $resource->toArray();   
        }
        return json_encode($array_for_json);
    }
    
    public function populateById ($id, $load_associations = false) {
        $sql = "SELECT * FROM {$this->table_name} WHERE id = $id LIMIT 1";  
        $query = $this->db->query($sql);
        if (!$query->row()) {
            throw new Exception ("no " . get_class($this) . " with id of " . $id);
        }
        foreach ($this->getModelAttributes() as $attribute)  {
            $this->$attribute = $query->row()->$attribute;
        }
        
        if ($load_associations) {
            $this->loadAssociations();
        }
        
        return true;
    }
    
    public function populateBy($key, $value,  $load_associations = false) {
        $sql = "SELECT * FROM {$this->table_name} WHERE $key = '$value' LIMIT 1";  
        $query = $this->db->query($sql);
        if (!$query->row()) {
            throw new Exception ("no " . get_class($this) . " with $key of " . $value);
        }
        foreach ($this->getModelAttributes() as $attribute)  {
            $this->$attribute = $query->row()->$attribute;
        }
        
        if ($load_associations) {
            $this->loadAssociations();
        }
        
        return true;
    }
    
    public function findBy($key, $value,  $load_associations = false) {
        $sql = "SELECT * FROM {$this->table_name} WHERE $key = '$value' LIMIT 1";  
        $query = $this->db->query($sql);
        if (!$query->row()) {
            return false;
        }
        foreach ($this->getModelAttributes() as $attribute)  {
            $this->$attribute = $query->row()->$attribute;
        }
        
        if ($load_associations) {
            $this->loadAssociations();
        }
        
        return true;
    }
    
    public function delete () {
        if (empty($this->id)) {
            throw new Exception ('id is empty (this object is not populated, can not delete');
        }
        $sql = "DELETE FROM {$this->table_name} WHERE id = {$this->id} LIMIT 1";  
        $query = $this->db->query($sql);    
    }
    
    public function getInputIdentifier($attribute = null) {
        
        if (!empty($this->id)) {
            $identifier = strtolower(get_class($this)) . '_info_' . $this->id;  
        } else {
            $identifier = strtolower(get_class($this)) . '_info';   
        }
        
        
        if($attribute !== null) {
            $identifier .= "[$attribute]";
        }
        return $identifier;  
    }
    
    public static function checkboxExistenceToInt($attribute, &$array) {
        if (array_key_exists($attribute, $array)) {
            $array[$attribute] = 1;
        } else {
            $array[$attribute] = 0;
        }
        return $array;
    }
    
    public static function dateSelectToMySQLDate($attribute, &$array) {
        
        //does php allow concat inside array indices?
        $month_index = $attribute . "_month";
        $day_index = $attribute . "_day";
        $year_index = $attribute . "_year";     
        
        $month = str_pad($array[$month_index],"2","0", STR_PAD_LEFT);
        $day = str_pad($array[$day_index],"2","0", STR_PAD_LEFT);
        
        $array[$attribute] = $array[$year_index] . "-" . $month . "-" . $day;
        if (strlen($array[$attribute]) !== 10 ) {
            throw new Exception ("dateSelectToMySQLDate return invalid date of: ". $array[$attribute] , 2342234);
        }
        //why return?  
        return $array;
    }
    
    public function createFromPostedFile($attribute_array, $posted_file_array, $resources_to_attach) {
        $this->setfromArray($attribute_array); 
        foreach ($resources_to_attach as $resource) {
            $foreign_key = strtolower(get_class($resource)) . "_id";
            if (!$this->isAttribute($foreign_key)) {
                throw new Exception("Trying to set an non-existent attribute: " . $foreign_key, 29348724 );
            }
            $this->set($foreign_key, $resource->get('id')); 
        }
        $this->set('filename', $posted_file_array['name']);
        $this->set('file_contents', file_get_contents($posted_file_array['tmp_name']));
        $this->save();
    }
    
}
