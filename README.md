lite_record
===========

A lite weight PHP ActiveRecord implementation on top of mysqli. 


Usage
------

Create a model class and inherit from LiteRecord

>class Car extends LiteRecord {
>	
>	# lite_record works by assuming a convention that all protected fields are meant to persist.	
>	# year and model will map to data mysql table fields
>	
>	protected $model;
>	protected $year;
>
>	# specify basic associations with an associative array
>	# supports has_many, has_one, belongs_to, has_many_through
>
>	$array = array(
>   	array("association_type"=>"has_many", 'model_name'=>'Wheel'),
>       array("association_type"=>"has_one", 'model_name'=>'SteeringWheel')                     
> 	);
>
>}


### save data
$car = new Car($mysqli);

$car->set("model");

$car->save();

### find data

$car = new Car($mysqli);

$car->populateById(123123);

### delete

$car = new Car($mysqli);

$car->populateById(123123);

$car->delete();


### models don't load associations until told to do so to keep db load light

//also most method that retrieve optionally can load associations on data retrieved

$car->loadAssociations();


### just load specific

$car->loadAssociationsFor("Wheel");


### get associated data

$car->SteeringWheel;

### or a set

$car->Wheel_set; #Array of Wheels


### encode to JSON

$car->toJSON();

### encode to Array

$car->toArray();


### get a list (supports additional sql params string $where, string $order, int $limit,  boolean $load_associations)

$car->getResourceSet();


### or get JSON set

$car->getResourceSetAsJSON();





  
