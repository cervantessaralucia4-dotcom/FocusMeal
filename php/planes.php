<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Planes - FocusMeal</title>
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<h1>Planes Disponibles</h1>

<div class="container">

<?php
$sql = "SELECT * FROM planes_disponibles";
$resultado = $conn->query($sql);

if (!$resultado) {
    die("Error en la consulta: " . $conn->error);
}

if ($resultado->num_rows > 0) {

    while ($plan = $resultado->fetch_assoc()) {
        ?>

        <div class="card">
        <h3><?= $plan['nombre_plan'] ?></h3>
        <p><?= $plan['descripcion'] ?></p>
        <p class="price"><?= $plan['calorias_diarias'] ?> calorías diarias</p>

            <form action="guardar_plan.php" method="POST">
                <input type="hidden" name="id_plan" value="<?= $plan['id_plan'] ?>">
                <button type="submit" class="btn-plan">Seleccionar</button>
            </form>
        </div>
        <?php 
    }

} else {
    echo "<p>No hay planes disponibles en la base de datos.</p>";
}
?>

</div>

</body>
</html>