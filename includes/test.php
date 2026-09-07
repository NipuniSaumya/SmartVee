<?php

$conn = mysqli_connect("localhost", "root", "", "smart_vehicle_parts");

if($conn){
    echo "Database Connected";
}else{
    echo mysqli_connect_error();
}

?>