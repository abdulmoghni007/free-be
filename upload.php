<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV File Upload</title>
</head>
<body>
<h1>Upload CSV File</h1>
<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="file" accept=".csv" required>
    <input type="submit" name="submit" value="Upload CSV">
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
    $databaseHost = 'localhost';
    $databaseUser = 'root';
    $databasePassword = '';
    $databaseName = 'molecular dump';

    try {
        $pdo = new PDO("mysql:host=$databaseHost;dbname=$databaseName", $databaseUser, $databasePassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the uploaded file name and use it for table name
        $uploadedFileName = basename($_FILES['file']['name']);
        $tableName = pathinfo($uploadedFileName, PATHINFO_FILENAME); // Use the filename without extension

        // Create the table if it doesn't exist
        $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `$tableName` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `Domain` varchar(100) DEFAULT NULL,
            `Species` varchar(100) DEFAULT NULL,
            `Taxa Id` varchar(100) DEFAULT NULL,
            `Read` varchar(100) DEFAULT NULL,
            `Abundance` varchar(100) DEFAULT NULL,
            `Activity Level` varchar(100) DEFAULT NULL,
            PRIMARY KEY (`id`)
        );";
        $pdo->exec($createTableQuery);

        $mimes = array('application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv');

        if (in_array($_FILES["file"]["type"], $mimes)) {
            $file = $_FILES["file"]["tmp_name"];
            $count = 0;
            $file_open = fopen($file, "r");

            while (($csv = fgetcsv($file_open)) !== FALSE) {
                $count++;
                if ($count == 1) continue; // Skip the header row

                // Ensure the array has at least 4 elements to avoid undefined array key warnings
                $csv = array_pad($csv, 4, '');

                $Domain = $csv[0];
                $Species = $csv[6];
                $Taxa_ID = $csv[7];
                $Reading = $csv[8];

                $stmt = $pdo->prepare("INSERT INTO `$tableName` (`Domain`, `Species`, `Taxa Id`, `Read`) VALUES (:Domain, :Species, :Taxa_ID, :Reading)");
                $stmt->bindParam(':Domain', $Domain);
                $stmt->bindParam(':Species', $Species);
                $stmt->bindParam(':Taxa_ID', $Taxa_ID);
                $stmt->bindParam(':Reading', $Reading);

                $stmt->execute();
            }

            echo "<br />Data Inserted into table '$tableName'";
            fclose($file_open);

            // Calculate Abundance and Update Activity Level
            $stmt = $pdo->prepare("UPDATE `$tableName`
                SET `Abundance` = CAST(`Read` AS DECIMAL(10,2)) * 100 / (SELECT SUM(CAST(`Read` AS DECIMAL(10,2)) ) FROM `$tableName`)");
            $stmt->execute();

            $stmt = $pdo->prepare("UPDATE `$tableName`
                SET `Activity Level` =
                    CASE
                        WHEN `Abundance` < 1 THEN 'Low'
                        WHEN `Abundance` >= 1 AND `Abundance` < 5 THEN 'Medium'
                        WHEN `Abundance` >= 5 THEN 'High'
                        ELSE NULL
                    END");
            $stmt->execute();

            // Example of deleting rows based on Taxa ID and Species
            $taxa_id_value = ""; // Replace with actual value
            $species_value = ""; // Replace with actual value
            $stmt = $pdo->prepare("DELETE FROM `$tableName` WHERE `Taxa Id` = :taxa_id AND `Species` = :species");
            $stmt->bindParam(':taxa_id', $taxa_id_value);
            $stmt->bindParam(':species', $species_value);
            $stmt->execute();

        } else {
            echo "<br />Sorry, File type Error. Only CSV file allowed.";
        }
    } catch (PDOException $e) {
        echo "Connection failed: " . $e->getMessage();
    }

    $pdo = null; // Close the PDO connection
} elseif (isset($_FILES['file'])) {
    echo "<br />Error: " . $_FILES['file']['error'];
}
?>
</body>
</html>
