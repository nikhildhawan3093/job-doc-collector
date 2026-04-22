<?php
$host = "localhost";
$port = "5432";
$dbname = "jdc";
$user = "postgres";
$password = "flowtrade"; // update this

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Database connection failed.");
}
