<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname1 = "pathways";
$dbname2 = "molecular dump";
$dbname3 = "patient result";
$dbname4 = "oral cancer forms"; // Database where the table will be created

$crn = "";

// Check if a POST request is received
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $crn = isset($_POST["crn"]) ? $_POST["crn"] : "";
    $ref=$_POST['ref'];
}

// Create table name with prefix
$crn = "patient_" . $crn;
$conn3="";

try {
    $conn3 = new PDO("mysql:host=$servername;dbname=$dbname3", $username, $password);
    $conn3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create the table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `$crn` (
        `Pathway` VARCHAR(255) NOT NULL,
        `Inflammatory Level` VARCHAR(255) NOT NULL,
        `Microbial activity` VARCHAR(255) NOT NULL,
        `Symptom Activity` VARCHAR(255) NOT NULL,
        `Functional Activity Level` VARCHAR(255),
        `Score assignment` VARCHAR(255)
    );";

    $conn3->exec($createTableSQL);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function ammonia($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Ammonia Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc ammonia production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc ammonia production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}
function hydrogen($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Hydrogen Sulphide Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc hydrogen sulphide production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc hydrogen sulphide production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}
function methyl($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Methyl Mercapta Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc methyl mercapta production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc methyl mercapta production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}


function pathbointActivity1($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Pathboint Activity-1";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc pathboint activity-1` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc pathboint activity-1` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}




function pathbointActivity2($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Pathboint Activity-2";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc pathboint activity-2` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc pathboint activity-2` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}





function lps($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC LPS Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc lps production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc lps production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function biofilm($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Biofilm Formation";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc biofilm formation` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc biofilm formation` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}

function fungal($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Fungal Activity";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc fungal activity` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc fungal activity` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}

function tmao($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC TMAO Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc tmao production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc tmao production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}

function succinic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Succinic Acid";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc succinic acid` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc succinic acid` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}


function lactic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Lactic Acid Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc lactic acid production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc lactic acid production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}





function butyrate($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "Oral Butyrate Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oral butyrate production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oral butyrate production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}




function protease($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Protease Pathways";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc protease pathways` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc protease pathways` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function uric($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Uric Acid Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc uric acid production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc uric acid production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}


function glutamine($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Glutamine Metabolism";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc glutamine metabolism` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc glutamine metabolism` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function symbiosis($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Oral Symbiosis";
    $inflammatory_level = "AI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc oral symbiosis` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc oral symbiosis` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}


function pentose($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Pentose Phosphate Pathway";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc pentose phosphate pathway` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc pentose phosphate pathway` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function lactate($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Lactate Dehydrogenase levels(LDH)";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc lactate dehydrogenase levels(ldh)` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc lactate dehydrogenase levels(ldh)` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}




function tyrosine($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Protein Tyrosine and Serine/Threonine pathway";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc protein tyrosine and serine/threonine pathway` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc protein tyrosine and serine/threonine pathway` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}

function cd($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC CD 36 expression";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc cd 36 expression` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc cd 36 expression` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function low($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Low Molecular weight (LMV) thiols";
    $inflammatory_level = "AI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc low molecular weight (lmv) thiols` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc low molecular weight (lmv) thiols` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}






function microbial($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Microbial Glutathione mediated stress response";
    $inflammatory_level = "AI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc microbial glutathione mediated stress response` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc microbial glutathione mediated stress response` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}




function cat($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Catalase(CAT) pathway";
    $inflammatory_level = "AI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc catalase(cat) pathway` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc catalase(cat) pathway` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}





function reduced($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Reduced Microbial Nitrate Utilization";
    $inflammatory_level = "AI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc reduced microbial nitrate utilization` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc reduced microbial nitrate utilization` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}





function polyamines($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Polyamines Production";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc polyamines production` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc polyamines production` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}



function volatile($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Volatile Organic COmpounds";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc volatile organic compounds` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc volatile organic compounds` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}





function genotoxic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref)
{
    $microbialActivity = "";
    $symptomsActivity = "";
    $pathway = "OC Genotoxic Potential";
    $inflammatory_level = "PI";

    try {
        // Create PDO connection for pathways database
        $conn1 = new PDO("mysql:host=$servername;dbname=$dbname1", $username, $password);
        $conn1->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for molecular dump database
        $conn2 = new PDO("mysql:host=$servername;dbname=$dbname2", $username, $password);
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create PDO connection for oral cancer forms database
        $conn4 = new PDO("mysql:host=$servername;dbname=$dbname4", $username, $password);
        $conn4->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL query to calculate the total score for microbial activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`activity level` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`activity level` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`activity level` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`activity level` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) / 100 AS total_score
        FROM `pathways`.`oc genotoxic potential` ap
        JOIN `molecular dump`.`$ref` md
        ON ap.Code = md.`Taxa Id`
        WHERE ap.Type = 'FS-1';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_microbial = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Microbial Activity): " . $total_score_microbial . "<br>";

        // Determine microbial activity
        if ($total_score_microbial > -10 && $total_score_microbial < 3.01) {
            $microbialActivity = "Low";
        } elseif ($total_score_microbial > 3 && $total_score_microbial < 6.001) {
            $microbialActivity = "Medium";
        } else {
            $microbialActivity = "High";
        }

        // SQL query to calculate the total score for symptoms activity
        $sql = "
        SELECT 
            SUM(
                CASE
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'PI' THEN 3
                    WHEN md.`Score` = 'low' AND ap.`Inflammatory Level` = 'AI' THEN -3
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'PI' THEN 6
                    WHEN md.`Score` = 'medium' AND ap.`Inflammatory Level` = 'AI' THEN -6
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'PI' THEN 10
                    WHEN md.`Score` = 'High' AND ap.`Inflammatory Level` = 'AI' THEN -10
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'PI' THEN 0
                    WHEN md.`Score` = 'Do not apply' AND ap.`Inflammatory Level` = 'AI' THEN 0
                    ELSE 0
                END * ap.`Weights`
            ) AS total_score
        FROM `pathways`.`oc genotoxic potential` ap
        JOIN `$dbname4`.`$crn` md
        ON ap.Code = md.`UID`
        WHERE ap.Type = 'FS-2';
        ";

        // Execute the query
        $stmt = $conn1->prepare($sql);
        $stmt->execute();

        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Save total score to a variable
        $total_score_symptoms = $result ? $result["total_score"] : 0;

        // Output the total score
        echo "Total Score (Symptoms Activity): " . $total_score_symptoms . "<br>";

        // Determine symptoms activity
        if ($total_score_symptoms > -10 && $total_score_symptoms < 3.01) {
            $symptomsActivity = "Low";
        } elseif ($total_score_symptoms > 3 && $total_score_symptoms < 6.001) {
            $symptomsActivity = "Medium";
        } else {
            $symptomsActivity = "High";
        }

        // Insert or update data into the dynamically created table
        $sql = "INSERT INTO `$crn` (`Pathway`, `Inflammatory Level`, `Microbial activity`, `Symptom Activity`)
            VALUES (:pathway, :inflammatory_level, :microbial_activity, :symptom_activity)
            ON DUPLICATE KEY UPDATE
                `Pathway` = VALUES(`Pathway`),
                `Inflammatory Level` = VALUES(`Inflammatory Level`),
                `Microbial activity` = VALUES(`Microbial activity`),
                `Symptom Activity` = VALUES(`Symptom Activity`)";

        // Prepare the statement
        $stmt = $conn3->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':pathway', $pathway);
        $stmt->bindParam(':inflammatory_level', $inflammatory_level);
        $stmt->bindParam(':microbial_activity', $microbialActivity);
        $stmt->bindParam(':symptom_activity', $symptomsActivity);

        // Execute the statement
        $stmt->execute();

        echo "Data inserted successfully.";

    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    } finally {
        // Close connections
        $conn1 = null;
        $conn2 = null;
        $conn3 = null;
        $conn4 = null;
    }
}







function functionalActivity($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn){

    $sql = "
    UPDATE `$crn`
    SET `Functional Activity Level` = CASE
        WHEN `Microbial activity` = 'LOW' AND `Symptom Activity` = 'LOW' THEN 'LOW'
        WHEN `Microbial activity` = 'Low' AND `Symptom Activity` = 'Medium' THEN 'Medium'
        WHEN `Microbial activity` = 'Medium' AND `Symptom Activity` = 'Low' THEN 'Medium'
        WHEN `Microbial activity` = 'Medium' AND `Symptom Activity` = 'Medium' THEN 'Medium'
        WHEN `Microbial activity` = 'Medium' AND `Symptom Activity` = 'High' THEN 'High'
        WHEN `Microbial activity` = 'High' AND `Symptom Activity` = 'Medium' THEN 'High'
        WHEN `Microbial activity` = 'High' AND `Symptom Activity` = 'High' THEN 'High'
        WHEN `Microbial activity` = 'Low' AND `Symptom Activity` = 'High' THEN 'Medium'
        WHEN `Microbial activity` = 'High' AND `Symptom Activity` = 'Low' THEN 'Medium'
        ELSE `Functional Activity Level`
    END;
";

    $stmt = $conn3->prepare($sql);
    $stmt->execute();
}





function scoreAssignment($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn){

    $sql = "
    UPDATE `$crn`
    SET `Score assignment` = CASE
        WHEN `Inflammatory Level` = 'PI' AND `Functional Activity Level` = 'low' THEN '1'
        WHEN `Inflammatory Level` = 'AI' AND `Functional Activity Level` = 'low' THEN '-1'
        WHEN `Inflammatory Level` = 'PI' AND `Functional Activity Level` = 'Medium' THEN '2'
        WHEN `Inflammatory Level` = 'AI' AND `Functional Activity Level` = 'medium' THEN '-2'
        WHEN `Inflammatory Level` = 'PI' AND `Functional Activity Level` = 'High' THEN '3'
        WHEN `Inflammatory Level` = 'AI' AND `Functional Activity Level` = 'High' THEN '-3'
        ELSE `Score assignment`
    END;
";

    $stmt = $conn3->prepare($sql);
    $stmt->execute();
}












// Check if a POST request is received and call the function
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["crn"])) {
    $crn = $_POST["crn"];
    $crn = "patient_" . $crn;
    $ref=$_POST['ref'];
    ammonia($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    hydrogen($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    methyl($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    pathbointActivity1($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    pathbointActivity2($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    lps($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    biofilm($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    fungal($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    tmao($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    succinic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    lactic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    butyrate($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    protease($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    uric($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    glutamine($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    symbiosis($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    pentose($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    lactate($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    tyrosine($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);


    cd($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);


    low($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    microbial($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    cat($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);
    reduced($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    polyamines($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    volatile($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);

    genotoxic($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn,$ref);


    functionalActivity($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn);

    scoreAssignment($servername, $username, $password, $dbname1, $dbname2, $conn3, $dbname4, $crn);
}
?>
