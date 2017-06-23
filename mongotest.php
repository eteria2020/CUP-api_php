<?php

 $m = new MongoClient();
 
 $db = $m->csg;
 
 $coll = $db->crashes;
 
 $doc =  array("Hello" => "World");
 $coll->insert($doc);
 


?>