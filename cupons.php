<?php
require 'db.php';

// Inserir cupom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $codigo = trim($_POST['codigo'] ?? '');
    $desconto = $_POST['desconto'] ?? 0;
    $validade = $_POST['validade'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;

    if ($codigo && is_numeric($desconto) && $validade) {
        $sql = "INSERT INTO cupons (codigo, desconto, validade, ativo) VALUES (:codigo, :desconto, :validade, :ativo)";
        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([
                ':codigo' => $codigo,
                ':desconto' => $desconto,
                ':validade' => $validade,
                ':ativo' => $ativo,
            ]);
            header('Location: cupons.php');
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao inserir cupom: código duplicado ou dados inválidos.";
        }
    } else {
        $erro = "Preencha todos os campos corretamente.";
    }
}

// Excluir cupom
if (isset($_GET['excluir'])) {
    $idExcluir = intval($_GET['excluir']);
    $sql = "DELETE FROM cupons WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idExcluir]);
    header('Location: cupons.php');
    exit;
}

// Buscar cupons
$sql = "SELECT * FROM cupons ORDER BY id DESC";
$stmt = $pdo->query($sql);
$cupons = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>Cupons - Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="container py-4">

    <h1>Cupons</h1>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="mb-3">
            <label for="codigo" class="form-label">Código do Cupom</label>
            <input type="text" id="codigo" name="codigo" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="desconto" class="form-label">Desconto (ex: 10 para 10%)</label>
            <input type="number" step="0.01" id="desconto" name="desconto" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="validade" class="form-label">Validade</label>
            <input type="date" id="validade" name="validade" class="form-control" required />
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" id="ativo" name="ativo" class="form-check-input" checked />
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
        <button type="submit" name="adicionar" class="btn btn-primary">Adicionar Cupom</button>
    </form>

    <h2>Lista de Cupons</h2>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Desconto (%)</th>
                <th>Validade</th>
                <th>Ativo</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cupons as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['codigo']) ?></td>
                    <td><?= number_format($c['desconto'], 2, ',', '.') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['validade'])) ?></td>
                    <td><?= $c['ativo'] ? 'Sim' : 'Não' ?></td>
                    <td>
                        <a href="?excluir=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este cupom?')">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($cupons)): ?>
                <tr><td colspan="6" class="text-center">Nenhum cupom cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
