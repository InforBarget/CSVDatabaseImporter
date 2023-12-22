<?php
// Connexion à la base de données
$host = 'localhost'; // ou adresse du serveur
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$errorMessages = [];

// Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file'])) {
    // Validation du fichier
    if ($_FILES['csv_file']['error'] == 0) {
        // Vérification du type de fichier
        if ($_FILES['csv_file']['type'] != 'text/csv' && pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION) != 'csv') {
            $errorMessages[] = "Le fichier doit être un fichier CSV.";
        } else {
            $fileName = $_FILES['csv_file']['tmp_name'];
            $file = fopen($fileName, 'r');

            // Récupération des paramètres de la table et des champs
            $table = $_POST['table'];
            $fields = array_map('trim', explode(',', $_POST['fields']));
            // Transforme la chaîne en tableau

            // Vérifier si la table existe
            try {
                $result = $pdo->query("DESCRIBE `$table`");
                $existingFields = $result->fetchAll(PDO::FETCH_COLUMN);

                // Vérifier si les champs spécifiés existent
                foreach ($fields as $field) {
                    if (!in_array(strtolower(trim($field)), array_map('strtolower', $existingFields))) {
                        $errorMessages[] = "Le champ '$field' n'existe pas dans la table '$table'.";
                    }
                }

                if (count($errorMessages) == 0) {
                    $pdo->beginTransaction();

                    // Lecture et insertion des données
                    while (($row = fgetcsv($file, 1000, ",")) !== FALSE) {
                        // Sauter la première ligne si elle contient des en-têtes
                        if ($row === array_filter($row, 'is_string') && !isset($headerSkipped)) {
                            $headerSkipped = true;
                            continue;
                        }

                        $data = [];
                        foreach ($fields as $index => $field) {
                            $data[$field] = $row[$index];
                        }

                        // Construction de la requête
                        $columns = implode(", ", array_keys($data));
                        $values = implode(", ", array_map(function($value) use ($pdo) {
                            return $pdo->quote($value);
                        }, array_values($data)));

                        $sql = "INSERT IGNORE INTO $table ($columns) VALUES ($values)";
                        $pdo->exec($sql);
                    }

                    $pdo->commit();
                    echo "Import réussi.";
                }
            } catch (PDOException $e) {
                $errorMessages[] = "La table spécifiée n'existe pas.";
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
    } else {
        $errorMessages[] = "Erreur de chargement du fichier.";
    }
}
// Après avoir récupéré les champs existants de la base de données
//var_dump($existingFields); // Afficher les champs existants

// Afficher les champs saisis par l'utilisateur
//var_dump($fields);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Import CSV</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
<body>
    <div class="container">
        <h2>Importer un fichier CSV</h2>
        <?php if (!empty($errorMessages)): ?>
    <div class="error-messages">
        <?php foreach ($errorMessages as $message): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
        <form action="" method="post" enctype="multipart/form-data">
            <label for="csv_file">Sélectionnez le fichier CSV :</label>
            <input type="file" name="csv_file" id="csv_file"><br>

            <label for="table">Table :</label>
            <input type="text" name="table" id="table"><br>

            <label for="fields">Champs (séparés par des virgules) :</label>
            <input type="text" name="fields" id="fields"><br>

            <input type="submit" value="Importer">
        </form>
    </div>
</body>
</html>