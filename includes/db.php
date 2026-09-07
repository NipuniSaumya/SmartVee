<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "smart_vehicle_parts";

$conn = mysqli_connect("localhost", "root", "", "smart_vehicle_parts");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
