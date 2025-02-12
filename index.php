<?php
session_start();

// Iniciar la base de datos SQLite
$db = new SQLite3('database.sqlite');

// Crear tablas si no existen
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS funnels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        campaign_cost REAL NOT NULL,
        reach INTEGER NOT NULL,
        clicks INTEGER NOT NULL,
        leads INTEGER NOT NULL,
        units_sold INTEGER NOT NULL,
        unit_price REAL NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users (id)
    )
");

// Agregar usuario por defecto (jocarsa / jocarsa) si no existe
$passwordHash = password_hash('jocarsa', PASSWORD_DEFAULT);
$db->exec("INSERT OR IGNORE INTO users (username, password) VALUES ('jocarsa', '$passwordHash')");

// Lógica de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, password FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Usuario o contraseña inválidos.";
    }
}

// Lógica de cierre de sesión
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Operaciones CRUD
$editFunnel = null; // Guardaremos datos del embudo si estamos editando
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Verificar si el usuario quiere editar un embudo existente
    if (isset($_GET['edit_funnel'])) {
        $funnelId = (int)$_GET['edit_funnel'];
        $stmt = $db->prepare("SELECT * FROM funnels WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $funnelId, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $editFunnel = $result->fetchArray(SQLITE3_ASSOC);
    }

    // Crear embudo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_funnel'])) {
        $name = $_POST['name'];
        $campaign_cost = $_POST['campaign_cost'];
        $reach = $_POST['reach'];
        $clicks = $_POST['clicks'];
        $leads = $_POST['leads'];
        $units_sold = $_POST['units_sold'];
        $unit_price = $_POST['unit_price'];

        $stmt = $db->prepare("
            INSERT INTO funnels (user_id, name, campaign_cost, reach, clicks, leads, units_sold, unit_price)
            VALUES (:user_id, :name, :campaign_cost, :reach, :clicks, :leads, :units_sold, :unit_price)
        ");
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':campaign_cost', $campaign_cost, SQLITE3_FLOAT);
        $stmt->bindValue(':reach', $reach, SQLITE3_INTEGER);
        $stmt->bindValue(':clicks', $clicks, SQLITE3_INTEGER);
        $stmt->bindValue(':leads', $leads, SQLITE3_INTEGER);
        $stmt->bindValue(':units_sold', $units_sold, SQLITE3_INTEGER);
        $stmt->bindValue(':unit_price', $unit_price, SQLITE3_FLOAT);
        $stmt->execute();

        // Redirigir para evitar reenvío de formulario
        header('Location: index.php');
        exit;
    }

    // Actualizar embudo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_funnel'])) {
        $funnel_id = (int)$_POST['funnel_id'];
        $name = $_POST['name'];
        $campaign_cost = $_POST['campaign_cost'];
        $reach = $_POST['reach'];
        $clicks = $_POST['clicks'];
        $leads = $_POST['leads'];
        $units_sold = $_POST['units_sold'];
        $unit_price = $_POST['unit_price'];

        $stmt = $db->prepare("
            UPDATE funnels
            SET name = :name,
                campaign_cost = :campaign_cost,
                reach = :reach,
                clicks = :clicks,
                leads = :leads,
                units_sold = :units_sold,
                unit_price = :unit_price
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':campaign_cost', $campaign_cost, SQLITE3_FLOAT);
        $stmt->bindValue(':reach', $reach, SQLITE3_INTEGER);
        $stmt->bindValue(':clicks', $clicks, SQLITE3_INTEGER);
        $stmt->bindValue(':leads', $leads, SQLITE3_INTEGER);
        $stmt->bindValue(':units_sold', $units_sold, SQLITE3_INTEGER);
        $stmt->bindValue(':unit_price', $unit_price, SQLITE3_FLOAT);
        $stmt->bindValue(':id', $funnel_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Redirigir al panel principal
        header('Location: index.php');
        exit;
    }

    // Eliminar embudo
    if (isset($_GET['delete_funnel'])) {
        $funnel_id = (int)$_GET['delete_funnel'];
        $stmt = $db->prepare("DELETE FROM funnels WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $funnel_id, SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();

        header('Location: index.php');
        exit;
    }

    // Obtener los embudos del usuario
    $stmt = $db->prepare("SELECT * FROM funnels WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $funnels = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $funnels[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jocarsa | beige</title>
    <link rel="icon" type="image/svg+xml" href="beige.png" />
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,300;1,400;1,500;1,700&display=swap');

        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Ubuntu, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }

        /* Contenedor de Login */
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-container h1 {
            margin-bottom: 20px;
        }

        .login-container input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .login-container button {
            width: 100%;
            padding: 10px;
            background-color: #d2b48c; /* Beige */
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .login-container button:hover {
            background-color: #c0a37a;
        }

        .error {
            color: red;
            margin-bottom: 10px;
        }

        /* Dashboard */
        .dashboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        header {
            background-color: #d2b48c; /* Beige */
            color: #fff;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        header h1 {
            margin: 0;
        }

        .logout {
            color: #fff;
            text-decoration: none;
            position: absolute;
            right: 20px;
            top: 20px;
        }

        main {
            flex: 1;
            padding: 20px;
            background-color: #f9f9f9;
        }

        /* Sección del Embudo */
        .funnel-section {
            display: flex;
            flex-direction: row;
            gap: 20px;
            margin-bottom: 20px;
        }

        .funnel-inputs {
            flex: 1;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .funnel-inputs h2 {
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: row;
            margin-bottom: 10px;
        }
        .input-group label:first-child{
        	flex: 0 0 200px;
        }

        .input-group label {
            margin-bottom: 5px;
            font-weight: bold;
        }

        .funnel-inputs form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .funnel-inputs form button {
            padding: 10px 20px;
            background-color: #d2b48c; /* Beige */
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .funnel-inputs form button:hover {
            background-color: #c0a37a;
        }

        .funnel-visual {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .funnel-visual h2 {
            margin-bottom: 20px;
        }

        /* Representación visual del embudo */
        .funnel-steps {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .funnel-step {
            color: #fff;
            padding: 10px;
            font-weight: bold;
            text-align: center;
            transition: width 0.3s;
            border-radius: 4px;
        }

        /* Colores para cada paso */
        #funnel-reach {
            background-color: #ff6347; /* tomate */
        }
        #funnel-clicks {
            background-color: #f0ad4e; /* naranja */
        }
        #funnel-leads {
            background-color: #5bc0de; /* celeste */
        }
        #funnel-units-sold {
            background-color: #5cb85c; /* verde */
        }

        /* Área de cálculos */
        .funnel-calculations {
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }

        .calculation-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        /* Lista de Embudos (Tabla) */
        .funnel-list {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .funnel-list h2 {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        table th {
            background-color: #d2b48c; /* Beige */
            color: #fff;
        }

        .actions a {
            margin-right: 10px;
            text-decoration: none;
            color: #000;
        }

        .delete {
            color: red;
        }

        .delete:hover {
            text-decoration: underline;
        }
        
        .edit {
            color: blue;
        }

        .edit:hover {
            text-decoration: underline;
        }
        h1{
        	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	justify-content: center;
	align-items: center;
	align-content: stretch;
        }
        h1 img{
        	width:50px;
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- Formulario de Iniciar Sesión -->
        <div class="login-container">
            <h1><img src="beige.png">jocarsa | beige</h1>
            <?php if (isset($error)): ?>
                <p class="error"><?= $error ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" name="login">Entrar</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Panel (Dashboard) -->
        <div class="dashboard">
            <header>
                <h1><img src="beige.png">jocarsa | beige</h1>
                <a href="?logout" class="logout">Cerrar Sesión</a>
            </header>
            <main>

                <div class="funnel-section">
                    <!-- Izquierda: Formulario de Embudo -->
                    <div class="funnel-inputs">
                        <?php if ($editFunnel): ?>
                            <h2>Editar Embudo</h2>
                            <form method="POST">
                                <input type="hidden" name="funnel_id" value="<?= $editFunnel['id'] ?>">

                                <div class="input-group">
                                    <label for="name">Nombre del Embudo:</label>
                                    <input type="text" id="name" name="name" 
                                           value="<?= htmlspecialchars($editFunnel['name']) ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="campaign-cost">Costo de Campaña:</label>
                                    <input type="number" step="0.01" id="campaign-cost" name="campaign_cost" 
                                           value="<?= $editFunnel['campaign_cost'] ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="reach">Alcance:</label>
                                    <input type="number" id="reach" name="reach" 
                                           value="<?= $editFunnel['reach'] ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="clicks">Clics:</label>
                                    <input type="number" id="clicks" name="clicks" 
                                           value="<?= $editFunnel['clicks'] ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="leads">Prospectos:</label>
                                    <input type="number" id="leads" name="leads" 
                                           value="<?= $editFunnel['leads'] ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="units-sold">Ventas:</label>
                                    <input type="number" id="units-sold" name="units_sold" 
                                           value="<?= $editFunnel['units_sold'] ?>" required>
                                </div>

                                <div class="input-group">
                                    <label for="unit-price">Precio Unitario:</label>
                                    <input type="number" step="0.01" id="unit-price" name="unit_price" 
                                           value="<?= $editFunnel['unit_price'] ?>" required>
                                </div>

                                <button type="submit" name="update_funnel">Actualizar Embudo</button>
                            </form>
                        <?php else: ?>
                            <h2>Crear Nuevo Embudo</h2>
                            <form method="POST">
                                <div class="input-group">
                                    <label for="name">Nombre del Embudo:</label>
                                    <input type="text" id="name" name="name" placeholder="Ej: Campaña 2025" required>
                                </div>

                                <div class="input-group">
                                    <label for="campaign-cost">Costo de Campaña:</label>
                                    <input type="number" step="0.01" id="campaign-cost" 
                                           name="campaign_cost" placeholder="Ej: 1500.00" required>
                                </div>

                                <div class="input-group">
                                    <label for="reach">Alcance:</label>
                                    <input type="number" id="reach" name="reach" placeholder="Ej: 10000" required>
                                </div>

                                <div class="input-group">
                                    <label for="clicks">Clics:</label>
                                    <input type="number" id="clicks" name="clicks" placeholder="Ej: 500" required>
                                </div>

                                <div class="input-group">
                                    <label for="leads">Prospectos:</label>
                                    <input type="number" id="leads" name="leads" placeholder="Ej: 50" required>
                                </div>

                                <div class="input-group">
                                    <label for="units-sold">Ventas:</label>
                                    <input type="number" id="units-sold" name="units_sold" placeholder="Ej: 20" required>
                                </div>

                                <div class="input-group">
                                    <label for="unit-price">Precio Unitario:</label>
                                    <input type="number" step="0.01" id="unit-price" 
                                           name="unit_price" placeholder="Ej: 25.00" required>
                                </div>

                                <button type="submit" name="create_funnel">Crear Embudo</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Derecha: Visualización del Embudo y Cálculos -->
                    <div class="funnel-visual">
                        <h2>Visualización del Embudo</h2>
                        <div class="funnel-steps">
                            <div class="funnel-step" id="funnel-reach">Alcance</div>
                            <div class="funnel-step" id="funnel-clicks">Clics</div>
                            <div class="funnel-step" id="funnel-leads">Prospectos</div>
                            <div class="funnel-step" id="funnel-units-sold">Ventas</div>
                        </div>

                        <div class="funnel-calculations">
                            <div class="calculation-row">
                                <span>Costo por Alcance:</span>
                                <span id="cost-per-reach">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Costo por Clic:</span>
                                <span id="cost-per-click">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Costo por Prospecto:</span>
                                <span id="cost-per-lead">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Ingreso por Venta:</span>
                                <span id="income-per-sale">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Ingreso Total de Campaña:</span>
                                <span id="campaign-income">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>ROI:</span>
                                <span id="roi">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Conv. Clics (Clics/Alcance):</span>
                                <span id="ctr">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Conv. Prospectos (Prospectos/Clics):</span>
                                <span id="lead-conv">N/A</span>
                            </div>
                            <div class="calculation-row">
                                <span>Conv. Ventas (Ventas/Prospectos):</span>
                                <span id="sale-conv">N/A</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla con la Lista de Embudos -->
                <div class="funnel-list">
                    <h2>Tus Embudos</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Costo</th>
                                <th>Alcance</th>
                                <th>Clics</th>
                                <th>Prospectos</th>
                                <th>Ventas</th>
                                <th>Precio Unit.</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($funnels)): ?>
                                <?php foreach ($funnels as $funnel): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($funnel['name']) ?></td>
                                        <td><?= $funnel['campaign_cost'] ?></td>
                                        <td><?= $funnel['reach'] ?></td>
                                        <td><?= $funnel['clicks'] ?></td>
                                        <td><?= $funnel['leads'] ?></td>
                                        <td><?= $funnel['units_sold'] ?></td>
                                        <td><?= $funnel['unit_price'] ?></td>
                                        <td class="actions">
                                            <a href="?edit_funnel=<?= $funnel['id'] ?>" class="edit">Editar</a>
                                            <a href="?delete_funnel=<?= $funnel['id'] ?>" class="delete">Borrar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">No hay embudos creados aún.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>

        <!-- Script para Cálculos en Tiempo Real -->
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Escuchar eventos de cambio para recalcular en tiempo real
                const ids = ['campaign-cost', 'reach', 'clicks', 'leads', 'units-sold', 'unit-price'];
                ids.forEach(id => {
                    const input = document.getElementById(id);
                    if (input) {
                        input.addEventListener('input', calculateFunnel);
                    }
                });

                // Cálculo inicial (por si ya hay valores, p.ej. al editar)
                calculateFunnel();
            });

            function calculateFunnel() {
                const campaignCost = parseFloat(document.getElementById('campaign-cost')?.value) || 0;
                const reach        = parseFloat(document.getElementById('reach')?.value) || 0;
                const clicks       = parseFloat(document.getElementById('clicks')?.value) || 0;
                const leads        = parseFloat(document.getElementById('leads')?.value) || 0;
                const unitsSold    = parseFloat(document.getElementById('units-sold')?.value) || 0;
                const unitPrice    = parseFloat(document.getElementById('unit-price')?.value) || 0;

                // Cálculos básicos
                const campaignIncome = unitsSold * unitPrice;
                const costPerReach = reach > 0 ? (campaignCost / reach).toFixed(2) : 'N/A';
                const costPerClick = clicks > 0 ? (campaignCost / clicks).toFixed(2) : 'N/A';
                const costPerLead  = leads > 0 ? (campaignCost / leads).toFixed(2) : 'N/A';
                const incomePerSale = unitsSold > 0 ? (campaignIncome / unitsSold).toFixed(2) : 'N/A';

                // ROI (en %)
                let roi = 'N/A';
                if (campaignCost > 0) {
                    roi = (((campaignIncome - campaignCost) / campaignCost) * 100).toFixed(2) + '%';
                }

                // Tasas de conversión
                // CTR: Clics / Alcance
                const ctr = (reach > 0) ? ((clicks / reach) * 100).toFixed(2) + '%' : 'N/A';
                // Lead Conversion: Leads / Clics
                const leadConv = (clicks > 0) ? ((leads / clicks) * 100).toFixed(2) + '%' : 'N/A';
                // Sales Conversion: Ventas / Prospectos
                const saleConv = (leads > 0) ? ((unitsSold / leads) * 100).toFixed(2) + '%' : 'N/A';

                // Actualizar DOM
                document.getElementById('cost-per-reach').textContent  = costPerReach;
                document.getElementById('cost-per-click').textContent  = costPerClick;
                document.getElementById('cost-per-lead').textContent   = costPerLead;
                document.getElementById('income-per-sale').textContent = incomePerSale;
                document.getElementById('campaign-income').textContent = 
                    isFinite(campaignIncome) ? campaignIncome.toFixed(2) : 'N/A';
                document.getElementById('roi').textContent = roi;
                document.getElementById('ctr').textContent = ctr;
                document.getElementById('lead-conv').textContent = leadConv;
                document.getElementById('sale-conv').textContent = saleConv;

                // Colorear fondo de porcentajes
                colorBackgroundPercentage(document.getElementById('ctr'), ctr);
                colorBackgroundPercentage(document.getElementById('lead-conv'), leadConv);
                colorBackgroundPercentage(document.getElementById('sale-conv'), saleConv);
                colorBackgroundPercentage(document.getElementById('roi'), roi);

                // Actualizar anchos del embudo
                const maxVal = Math.max(reach, clicks, leads, unitsSold);
                updateFunnelStep('funnel-reach', reach, maxVal);
                updateFunnelStep('funnel-clicks', clicks, maxVal);
                updateFunnelStep('funnel-leads', leads, maxVal);
                updateFunnelStep('funnel-units-sold', unitsSold, maxVal);
            }

            function updateFunnelStep(stepId, value, maxVal) {
                const stepEl = document.getElementById(stepId);
                if (!stepEl) return;
                if (maxVal === 0) {
                    // Si todo es cero, ancho mínimo
                    stepEl.style.width = '80px';
                    stepEl.textContent = stepEl.id.replace('funnel-', '').toUpperCase() + ' (0)';
                    return;
                }
                // Escala: 300px como ancho máximo
                const scaledWidth = (value / maxVal) * 300;
                stepEl.style.width = Math.max(scaledWidth, 50) + 'px';
                
                // Texto para la barra
                let label = '';
                switch(stepId) {
                    case 'funnel-reach': label = 'Alcance'; break;
                    case 'funnel-clicks': label = 'Clics'; break;
                    case 'funnel-leads': label = 'Prospectos'; break;
                    case 'funnel-units-sold': label = 'Ventas'; break;
                }
                stepEl.textContent = `${label} (${value})`;
            }

            // Función para colorear fondo de los porcentajes de conversión/ROI
            function colorBackgroundPercentage(element, valueStr) {
                if (!element || valueStr === 'N/A') {
                    element.style.backgroundColor = 'transparent';
                    return;
                }
                // Eliminar el signo % si existe
                const numericValue = parseFloat(valueStr.replace('%', ''));
                if (isNaN(numericValue)) {
                    element.style.backgroundColor = 'transparent';
                    return;
                }
                // Rango de colores: 0-3% (rojo), 3-6% (amarillo), >6% (verde)
                if (numericValue <= 3) {
                    element.style.backgroundColor = '#f8d7da'; // rojo claro
                } else if (numericValue <= 6) {
                    element.style.backgroundColor = '#fff3cd'; // amarillo claro
                } else {
                    element.style.backgroundColor = '#d4edda'; // verde claro
                }
            }
        </script>
    <?php endif; ?>
</body>
</html>

